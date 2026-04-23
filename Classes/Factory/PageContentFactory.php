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

namespace AutoDudes\AiSuite\Factory;

use AutoDudes\AiSuite\Exception\AiSuiteException;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\FileNameSanitizerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderReadPermissionsException;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageContentFactory
{
    /** @var array<string, mixed> */
    protected array $extConf;

    public function __construct(
        protected readonly StorageRepository $storageRepository,
        protected readonly Filesystem $filesystem,
        protected readonly LinkService $linkService,
        protected readonly SettingsFactory $settingsFactory,
        protected readonly BackendUserService $backendUserService,
        protected readonly LoggerInterface $logger,
    ) {
        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $contentElementImageData
     * @param array<string, mixed> $contentElementIrreFields
     * @param array<string, mixed> $contentElementTextData
     *
     * @throws AiSuiteException
     * @throws InsufficientFolderAccessPermissionsException|InsufficientFolderReadPermissionsException
     */
    public function createContentElementData(
        array $content,
        array $contentElementTextData,
        array $contentElementImageData,
        array $contentElementIrreFields
    ): void {
        $data = [];
        $newStrings = [];
        if (0 === count($contentElementTextData)) {
            if (array_key_exists('tt_content', $contentElementImageData)) {
                $newStrings['tt_content'] = $this->newStringPlaceholder('tt_content');
                $data['tt_content'][$newStrings['tt_content']]['colPos'] = $content['colPos'];
                $data['tt_content'][$newStrings['tt_content']]['CType'] = $content['CType'];
                $data['tt_content'][$newStrings['tt_content']]['pid'] = $content['uidPid'];
                $data['tt_content'][$newStrings['tt_content']]['sys_language_uid'] = $content['sysLanguageUid'];
                if (0 !== $content['containerParentUid']) {
                    $data['tt_content'][$newStrings['tt_content']]['tx_container_parent'] = $content['containerParentUid'];
                }
            } else {
                $newStrings['tx_news_domain_model_news'] = $this->newStringPlaceholder('tx_news_domain_model_news');
                $data['tx_news_domain_model_news'][$newStrings['tx_news_domain_model_news']]['pid'] = $content['uidPid'];
                $data['tx_news_domain_model_news'][$newStrings['tx_news_domain_model_news']]['sys_language_uid'] = $content['sysLanguageUid'];
            }
        }

        foreach ($contentElementTextData as $table => $fieldsArray) {
            $newStrings[$table] = [];
            foreach ($fieldsArray as $key => $fields) {
                if ('tt_content' === $table) {
                    $newStrings[$table] = $this->newStringPlaceholder($table);
                    $data[$table][$newStrings[$table]]['colPos'] = $content['colPos'];
                    $data[$table][$newStrings[$table]]['CType'] = $content['CType'];
                    $data[$table][$newStrings[$table]]['pid'] = $content['uidPid'];
                    $data[$table][$newStrings[$table]]['sys_language_uid'] = $content['sysLanguageUid'];
                    if (0 !== $content['containerParentUid']) {
                        $data[$table][$newStrings[$table]]['tx_container_parent'] = $content['containerParentUid'];
                    }
                    foreach ($fields as $fieldName => $fieldValue) {
                        $data[$table][$newStrings[$table]][$fieldName] = html_entity_decode($fieldValue);
                    }
                } elseif ('tx_news_domain_model_news' === $table) {
                    $newStrings[$table] = $this->newStringPlaceholder($table);
                    $data[$table][$newStrings[$table]]['pid'] = $content['uidPid'];
                    $data[$table][$newStrings[$table]]['sys_language_uid'] = $content['sysLanguageUid'];
                    $data[$table][$newStrings[$table]]['datetime'] = time();
                    foreach ($fields as $fieldName => $fieldValue) {
                        $data[$table][$newStrings[$table]][$fieldName] = html_entity_decode($fieldValue);
                    }
                } else {
                    $newStrings[$table][$key] = $this->newStringPlaceholder($table, $key);
                    $data[$table][$newStrings[$table][$key]]['pid'] = $content['pid'];
                    $data[$table][$newStrings[$table][$key]]['sys_language_uid'] = $content['sysLanguageUid'];
                    foreach ($fields as $fieldName => $fieldValue) {
                        $data[$table][$newStrings[$table][$key]][$fieldName] = html_entity_decode($fieldValue);
                    }
                }
            }
        }
        foreach ($contentElementIrreFields as $table => $parentTable) {
            if (!array_key_exists($table, $newStrings)) {
                continue;
            }
            $parentKey = is_string($newStrings[$parentTable]) ? $newStrings[$parentTable] : '';
            if (is_array($newStrings[$table])) {
                $data[$parentTable][$parentKey][$table] = implode(',', $newStrings[$table]);
            } else {
                $data[$parentTable][$parentKey][$table] = $newStrings[$table];
            }
        }
        foreach ($contentElementImageData as $table => $fieldsArray) {
            foreach ($fieldsArray as $key => $fields) {
                foreach ($fields as $fieldName => $fieldData) {
                    $newFileUid = $this->addImage($fieldData['newImageUrl'], $fieldData['imageTitle'] ?? '', $content['regenerateReturnUrl'] ?? '');
                    $newString = $this->newStringPlaceholder($table.'_sys_file_refrence', $key);
                    $data['sys_file_reference'][$newString] = [
                        'table_local' => 'sys_file',
                        'uid_local' => $newFileUid,
                        'tablenames' => $table,
                        'uid_foreign' => ('tt_content' === $table || 'tx_news_domain_model_news' === $table) ? $newStrings[$table] : $newStrings[$table][$key],
                        'fieldname' => $fieldName,
                        'pid' => $content['pid'],
                        'title' => $fieldData['imageTitle'] ?? '',
                        'alternative' => $fieldData['imageTitle'] ?? '',
                    ];
                    if ('tt_content' === $table || 'tx_news_domain_model_news' === $table) {
                        $tableKey = is_string($newStrings[$table]) ? $newStrings[$table] : '';
                        $data[$table][$tableKey][$fieldName] = $newString;
                    } else {
                        $data[$table][$newStrings[$table][$key]][$fieldName] = $newString;
                    }
                }
            }
        }

        try {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();
        } catch (\Exception $e) {
            throw new AiSuiteException('Content/SaveContent', '', '', $e->getMessage(), $content['regenerateReturnUrl'] ?? '');
        }
        if (count($dataHandler->errorLog) > 0) {
            throw new AiSuiteException('Content/SaveContent', '', '', $dataHandler->errorLog[0], $content['regenerateReturnUrl'] ?? '');
        }
    }

    /**
     * @throws AiSuiteException
     * @throws InsufficientFolderReadPermissionsException
     */
    public function addImage(string $imageUrl, string $imageTitle, string $regenerateReturnUrl = ''): int
    {
        $fileExtension = !empty(pathinfo($imageUrl, PATHINFO_EXTENSION)) ? pathinfo($imageUrl, PATHINFO_EXTENSION) : 'png';
        $title = empty($imageTitle) ? 'ai-generated-image-'.time() : $imageTitle;

        $mediaFolderSetting = !empty($this->extConf['mediaStorageFolder']) ? $this->extConf['mediaStorageFolder'] : 'ai-images';

        if (preg_match('/^\d+:/', $mediaFolderSetting)) {
            $aiImagesFolder = $this->resolveFolderFromCombinedIdentifier(
                $mediaFolderSetting,
                $regenerateReturnUrl
            );
        } else {
            $defaultFolder = $this->resolveDefaultFolder($regenerateReturnUrl);
            $mediaFolder = trim($mediaFolderSetting, '/');

            // Avoid path nesting if the default folder already ends with the target folder name
            if (rtrim($defaultFolder->getName(), '/') === $mediaFolder) {
                $aiImagesFolder = $defaultFolder;
            } elseif ($defaultFolder->hasFolder($mediaFolder)) {
                $aiImagesFolder = $defaultFolder->getSubfolder($mediaFolder);
            } else {
                $aiImagesFolder = $defaultFolder->getStorage()->createFolder(
                    $mediaFolder,
                    $defaultFolder
                );
            }
        }

        $destinationPath = Environment::getPublicPath().$aiImagesFolder->getPublicUrl();

        $title = FileNameSanitizerService::sanitize($title);
        $targetFile = $this->filesystem->exists($destinationPath.$title.'.'.$fileExtension) ? $title.'-'.time().'.'.$fileExtension : $title.'.'.$fileExtension;

        // Download to temp file, detect real MIME type, then add via FAL
        $tempBase = GeneralUtility::tempnam('ai_image_');
        $this->filesystem->copy($imageUrl, $tempBase);

        if (!file_exists($tempBase) || 0 === filesize($tempBase)) {
            @unlink($tempBase);

            throw new \RuntimeException(sprintf('Failed to download image from %s', $imageUrl));
        }

        // Detect actual file type — server may return JPEG data with .png URL
        $detectedMime = mime_content_type($tempBase);
        $realExtension = match ($detectedMime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => $fileExtension,
        };

        // Fix target filename if extension doesn't match actual content
        if ($realExtension !== $fileExtension) {
            $targetFile = str_replace('.'.$fileExtension, '.'.$realExtension, $targetFile);
        }

        // Rename temp file with correct extension for TYPO3 ResourceConsistencyService
        $tempFile = $tempBase.'.'.$realExtension;
        rename($tempBase, $tempFile);

        $storage = $aiImagesFolder->getStorage();
        $newFile = $storage->addFile($tempFile, $aiImagesFolder, $targetFile);

        return $newFile->getUid();
    }

    /**
     * @throws AiSuiteException
     */
    protected function resolveDefaultFolder(string $regenerateReturnUrl): Folder
    {
        if ($this->backendUserService->getBackendUser()?->isAdmin()) {
            $storage = $this->storageRepository->getDefaultStorage();
            if (null === $storage) {
                throw new AiSuiteException('Content/SaveContent', 'aiSuite.addImage.noDefaultStorage', '', '', $regenerateReturnUrl);
            }

            return $storage->getDefaultFolder();
        }

        $availableFileMounts = $this->backendUserService->getBackendUser()?->getFileMountRecords() ?? [];
        if (0 === count($availableFileMounts)) {
            throw new AiSuiteException('Content/SaveContent', 'aiSuite.addImage.noFileMountsAvailable', '', '', $regenerateReturnUrl);
        }
        foreach ($availableFileMounts as $fileMount) {
            $storage = $this->storageRepository->findByCombinedIdentifier($fileMount['identifier']);
            if (null === $storage) {
                continue;
            }
            foreach ($storage->getFileMounts() as $storageFileMount) {
                if ($storageFileMount['identifier'] === $fileMount['identifier']) {
                    return $storageFileMount['folder'];
                }
            }
        }

        throw new AiSuiteException('Content/SaveContent', 'aiSuite.addImage.noFolderAvailable', '', '', $regenerateReturnUrl);
    }

    /**
     * @param non-empty-string $combinedIdentifier
     *
     * @throws AiSuiteException
     */
    protected function resolveFolderFromCombinedIdentifier(
        string $combinedIdentifier,
        string $regenerateReturnUrl
    ): Folder {
        $storage = $this->storageRepository->findByCombinedIdentifier($combinedIdentifier);
        if (null === $storage) {
            throw new AiSuiteException(
                'Content/SaveContent',
                'aiSuite.addImage.invalidStorage',
                '',
                sprintf('Storage for combined identifier "%s" not found', $combinedIdentifier),
                $regenerateReturnUrl
            );
        }

        [, $folderPath] = explode(':', $combinedIdentifier, 2);
        $folderPath = '/'.ltrim($folderPath, '/');

        if ($storage->hasFolder($folderPath)) {
            return $storage->getFolder($folderPath);
        }

        // ResourceStorage::createFolder() only creates a single-segment folder,
        // so walk the path and create each missing parent.
        $segments = array_values(array_filter(explode('/', $folderPath), static fn (string $segment): bool => '' !== $segment));
        $currentFolder = $storage->getRootLevelFolder(false);
        foreach ($segments as $segment) {
            $currentFolder = $currentFolder->hasFolder($segment)
                ? $currentFolder->getSubfolder($segment)
                : $storage->createFolder($segment, $currentFolder);
        }

        return $currentFolder;
    }

    protected function newStringPlaceholder(string $table, int $key = 0): string
    {
        return 'NEW'
            .substr(
                md5(time().$table.$key.rand(0, 100000)),
                0,
                22
            );
    }
}
