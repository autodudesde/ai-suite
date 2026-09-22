<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\ProvenanceContext;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The one place a generated image enters FAL. The bytes are not touched here: the marking is written
 * upstream, and re-encoding would strip it.
 */
class AiImageStoreService
{
    public function __construct(
        protected readonly Filesystem $filesystem,
        protected readonly ProvenanceService $provenanceService,
    ) {}

    public function store(
        string $imageUrl,
        string $imageTitle,
        Folder $folder,
        string $model = '',
        string $targetFileName = '',
    ): File {
        $tempFile = $this->download($imageUrl);

        try {
            // Only the name is taken from the caller, never the extension. The wizard derives it in
            // the browser — from a blob type that can be empty, or from the tail of a signed URL,
            // where `...jpg?st=2026-09-04&sig=…` becomes the "extension". The core refuses a file
            // whose extension and mime type disagree, and the downloaded bytes are the only place
            // that knows the truth.
            $baseName = '' !== $targetFileName ? pathinfo($targetFileName, PATHINFO_FILENAME) : $imageTitle;

            $file = $folder->getStorage()->addFile(
                $tempFile,
                $folder,
                $this->targetFileName($baseName, $tempFile, $folder),
            );
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }

        if (!$file instanceof File) {
            throw new \RuntimeException('Storing the generated image returned an unexpected file type.');
        }

        if ('' !== trim($imageTitle)) {
            $metaData = $file->getMetaData();
            $metaData->offsetSet('title', $imageTitle);
            $metaData->offsetSet('alternative', $imageTitle);
            $metaData->save();

            // This write bypasses the DataHandler, so the hook never records it. After the save: the
            // fingerprint reads the stored row.
            $metaDataUid = (int) $metaData->offsetGet('uid');
            if ($metaDataUid > 0) {
                $this->provenanceService->record(
                    ProvenanceContext::generated(ProvenanceContext::FEATURE_METADATA, $model),
                    'sys_file_metadata',
                    $metaDataUid,
                    ['title', 'alternative'],
                );
            }
        }

        $this->provenanceService->record(
            ProvenanceContext::generated(ProvenanceContext::FEATURE_IMAGE, $model),
            'sys_file',
            $file->getUid(),
        );

        return $file;
    }

    /**
     * @throws \RuntimeException
     */
    protected function download(string $imageUrl): string
    {
        $tempBase = GeneralUtility::tempnam('ai_image_');
        $this->filesystem->copy($imageUrl, $tempBase);

        if (!file_exists($tempBase) || 0 === filesize($tempBase)) {
            @unlink($tempBase);

            throw new \RuntimeException(sprintf('Failed to download image from %s', $imageUrl));
        }

        $urlExtension = strtolower(pathinfo($imageUrl, PATHINFO_EXTENSION));
        $tempFile = $tempBase.'.'.self::extensionOf($tempBase, '' !== $urlExtension ? $urlExtension : 'png');
        rename($tempBase, $tempFile);

        return $tempFile;
    }

    protected function targetFileName(string $imageTitle, string $tempFile, Folder $folder): string
    {
        $baseName = '' !== trim($imageTitle) ? $imageTitle : 'ai-generated-image-'.time();
        $baseName = FileNameSanitizerService::sanitize($baseName);
        $extension = pathinfo($tempFile, PATHINFO_EXTENSION);

        $fileName = $baseName.'.'.$extension;
        if ($folder->hasFile($fileName)) {
            $fileName = $baseName.'-'.time().'.'.$extension;
        }

        return $fileName;
    }

    private static function extensionOf(string $file, string $fallback): string
    {
        return match (mime_content_type($file)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => $fallback,
        };
    }
}
