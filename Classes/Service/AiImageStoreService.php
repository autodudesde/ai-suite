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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Generated image bytes are stored untouched: re-encoding would strip the image marking.
 */
class AiImageStoreService
{
    private const MAX_IMAGE_BYTES = 50 * 1024 * 1024;
    private const CHUNK_SIZE = 8192;

    public function __construct(
        protected readonly RequestFactory $requestFactory,
        protected readonly ExtensionConfiguration $extensionConfiguration,
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
            // Only the name is taken from the caller; the extension comes from the downloaded bytes
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

            // Bypasses the DataHandler hook; must follow save(), the fingerprint reads the stored row
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
        $this->assertServedByAiSuiteServer($imageUrl);

        $response = $this->requestFactory->request($imageUrl, 'GET', [
            'allow_redirects' => false,
            'stream' => true,
            'timeout' => 60,
            'http_errors' => false,
        ]);
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf('The AI Suite Server answered HTTP %d for the generated image.', $response->getStatusCode()));
        }

        $tempBase = GeneralUtility::tempnam('ai_image_');
        $handle = fopen($tempBase, 'wb');
        if (false === $handle) {
            @unlink($tempBase);

            throw new \RuntimeException('Could not open a temporary file for the generated image.');
        }

        $written = 0;
        $body = $response->getBody();

        try {
            while (!$body->eof()) {
                $chunk = $body->read(self::CHUNK_SIZE);
                if ('' === $chunk) {
                    break;
                }
                $written += strlen($chunk);
                if ($written > self::MAX_IMAGE_BYTES) {
                    throw new \RuntimeException(sprintf('The generated image exceeds %d bytes.', self::MAX_IMAGE_BYTES));
                }
                fwrite($handle, $chunk);
            }
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($tempBase);

            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('Downloading the generated image failed.', 1761100000, $e);
        }
        fclose($handle);

        $extension = self::imageExtensionOf($tempBase);
        if (0 === $written || null === $extension) {
            @unlink($tempBase);

            throw new \RuntimeException('The AI Suite Server did not answer with an image.');
        }

        $tempFile = $tempBase.'.'.$extension;
        rename($tempBase, $tempFile);

        return $tempFile;
    }

    /**
     * @throws \RuntimeException
     */
    protected function assertServedByAiSuiteServer(string $imageUrl): void
    {
        try {
            $server = parse_url((string) $this->extensionConfiguration->get('ai_suite', 'aiSuiteServer'));
        } catch (\Throwable) {
            $server = false;
        }
        $image = parse_url($imageUrl);

        $serverHost = is_array($server) ? strtolower($server['host'] ?? '') : '';
        if ('' === $serverHost || !is_array($image)
            || 'https' !== strtolower($image['scheme'] ?? '')
            || strtolower($image['host'] ?? '') !== $serverHost
            || ($image['port'] ?? null) !== ($server['port'] ?? null)
            || isset($image['user']) || isset($image['pass'])
        ) {
            throw new \RuntimeException('Only images served by the configured AI Suite Server are stored.');
        }
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

    private static function imageExtensionOf(string $file): ?string
    {
        return match (mime_content_type($file)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };
    }
}
