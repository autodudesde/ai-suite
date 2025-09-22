<?php

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

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
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageContentFactory
{
    protected StorageRepository $storageRepository;
    protected Filesystem $filesystem;
    protected LinkService $linkService;
    protected array $extConf;
    protected SettingsFactory $settingsFactory;
    protected BackendUserService $backendUserService;
    protected LoggerInterface $logger;

    public function __construct(
        StorageRepository $storageRepository,
        Filesystem $filesystem,
        LinkService $linkService,
        SettingsFactory $settingsFactory,
        BackendUserService $backendUserService,
        LoggerInterface $logger
    ) {
        $this->storageRepository = $storageRepository;
        $this->filesystem = $filesystem;
        $this->linkService = $linkService;
        $this->settingsFactory = $settingsFactory;
        $this->backendUserService = $backendUserService;
        $this->logger = $logger;

        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
    }

    /**
     * @throws AiSuiteException
     * @throws InsufficientFolderReadPermissionsException|InsufficientFolderAccessPermissionsException
     */
    public function createContentElementData(
        array $content,
        array $contentElementTextData,
        array $contentElementImageData,
        array $contentElementIrreFields
    ): void {
        $data = [];
        $newStrings = [];
        if (count($contentElementTextData) === 0) {
            if (array_key_exists('tt_content', $contentElementImageData)) {
                $newStrings['tt_content'] = $this->newStringPlaceholder('tt_content');
                $data['tt_content'][$newStrings['tt_content']]["colPos"] = $content['colPos'];
                $data['tt_content'][$newStrings['tt_content']]["CType"] = $content['CType'];
                $data['tt_content'][$newStrings['tt_content']]["pid"] = $content['uidPid'];
                $data['tt_content'][$newStrings['tt_content']]["sys_language_uid"] = $content['sysLanguageUid'];
                if ($content['containerParentUid'] !== 0) {
                    $data['tt_content'][$newStrings['tt_content']]["tx_container_parent"] = $content['containerParentUid'];
                }
            } else {
                $newStrings['tx_news_domain_model_news'] = $this->newStringPlaceholder('tx_news_domain_model_news');
                $data['tx_news_domain_model_news'][$newStrings['tx_news_domain_model_news']]["pid"] = $content['uidPid'];
                $data['tx_news_domain_model_news'][$newStrings['tx_news_domain_model_news']]["sys_language_uid"] = $content['sysLanguageUid'];
            }
        }

        foreach ($contentElementTextData as $table => $fieldsArray) {
            $newStrings[$table] = [];
            foreach ($fieldsArray as $key => $fields) {
                if ($table === 'tt_content') {
                    $newStrings[$table] = $this->newStringPlaceholder($table);
                    $data[$table][$newStrings[$table]]["colPos"] = $content['colPos'];
                    $data[$table][$newStrings[$table]]["CType"] = $content['CType'];
                    $data[$table][$newStrings[$table]]["pid"] = $content['uidPid'];
                    $data[$table][$newStrings[$table]]["sys_language_uid"] = $content['sysLanguageUid'];
                    if ($content['containerParentUid'] !== 0) {
                        $data[$table][$newStrings[$table]]["tx_container_parent"] = $content['containerParentUid'];
                    }
                    foreach ($fields as $fieldName => $fieldValue) {
                        $data[$table][$newStrings[$table]][$fieldName] = html_entity_decode($fieldValue);
                    }
                } elseif ($table === 'tx_news_domain_model_news') {
                    $newStrings[$table] = $this->newStringPlaceholder($table);
                    $data[$table][$newStrings[$table]]["pid"] = $content['uidPid'];
                    $data[$table][$newStrings[$table]]["sys_language_uid"] = $content['sysLanguageUid'];
                    $data[$table][$newStrings[$table]]["datetime"] = time();
                    foreach ($fields as $fieldName => $fieldValue) {
                        $data[$table][$newStrings[$table]][$fieldName] = html_entity_decode($fieldValue);
                    }
                } else {
                    $newStrings[$table][$key] = $this->newStringPlaceholder($table, $key);
                    $data[$table][$newStrings[$table][$key]]["pid"] = $content['pid'];
                    $data[$table][$newStrings[$table][$key]]["sys_language_uid"] = $content['sysLanguageUid'];
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
            if (is_array($newStrings[$table])) {
                $data[$parentTable][$newStrings[$parentTable]][$table] = implode(',', $newStrings[$table]);
            } else {
                $data[$parentTable][$newStrings[$parentTable]][$table] = $newStrings[$table];
            }
        }
        foreach ($contentElementImageData as $table => $fieldsArray) {
            foreach ($fieldsArray as $key => $fields) {
                foreach ($fields as $fieldName => $fieldData) {
                    $newFileUid = $this->addImage($fieldData['newImageUrl'], $fieldData['imageTitle'] ?? '', $content['regenerateReturnUrl']);
                    $newString = $this->newStringPlaceholder($table.'_sys_file_refrence', $key);
                    $data['sys_file_reference'][$newString] = [
                        'table_local' => 'sys_file',
                        'uid_local' => $newFileUid,
                        'tablenames' => $table,
                        'uid_foreign' => ($table === 'tt_content' || $table === 'tx_news_domain_model_news') ? $newStrings[$table] : $newStrings[$table][$key],
                        'fieldname' => $fieldName,
                        'pid' => $content['uidPid'],
                        'title' => $fieldData['imageTitle'] ?? '',
                        'alternative' => $fieldData['imageTitle'] ?? '',
                    ];
                    if ($table === 'tt_content' || $table === 'tx_news_domain_model_news') {
                        $data[$table][$newStrings[$table]][$fieldName] = $newString;
                    } else {
                        $data[$table][$newStrings[$table][$key]][$fieldName] = $newString;
                    }
                }
            }
        }
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if (count($dataHandler->errorLog) > 0) {
            throw new AiSuiteException('Content/SaveContent', '', '', $dataHandler->errorLog[0], $content['regenerateReturnUrl']);
        }
    }

    /**
     * @throws AiSuiteException
     * @throws InsufficientFolderReadPermissionsException
     */
    public function addImage(string $imageUrl, string $imageTitle, string $regenerateReturnUrl = ''): int
    {
        $fileExtension = !empty(pathinfo($imageUrl, PATHINFO_EXTENSION)) ? pathinfo($imageUrl, PATHINFO_EXTENSION) : 'png';
        $title = empty($imageTitle) ? 'ai-generated-image-' . time() : $imageTitle;

        $defaultFolder = null;
        if ($this->backendUserService->getBackendUser()->isAdmin()) {
            $storage = $this->storageRepository->getDefaultStorage();
            $defaultFolder = $storage->getDefaultFolder();
        } else {
            $availableFileMounts = $this->backendUserService->getBackendUser()->getFileMountRecords();
            if (count($availableFileMounts) === 0) {
                throw new AiSuiteException('Content/SaveContent', 'aiSuite.addImage.noFileMountsAvailable', '', '', $regenerateReturnUrl);
            }
            foreach ($availableFileMounts as $fileMount) {
                $storage = $this->storageRepository->findByCombinedIdentifier($fileMount['identifier']);
                foreach ($storage->getFileMounts() as $storageFileMount) {
                    if ($storageFileMount['identifier'] === $fileMount['identifier']) {
                        $defaultFolder = $storageFileMount['folder'];
                        break;
                    }
                }
            }
            if ($defaultFolder === null) {
                throw new AiSuiteException('Content/SaveContent', 'aiSuite.addImage.noFolderAvailable', '', '', $regenerateReturnUrl);
            }
        }
        if (!empty($this->extConf['mediaStorageFolder'])) {
            try {
                $aiImagesFolder = $defaultFolder->getSubfolder($this->extConf['mediaStorageFolder']);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $defaultFolder->createFolder($this->extConf['mediaStorageFolder']);
                $aiImagesFolder = $defaultFolder->getSubfolder($this->extConf['mediaStorageFolder']);
            }
        } else {
            $defaultFolder->hasFolder('ai-images') ?: $defaultFolder->createFolder('ai-images');
            $aiImagesFolder = $defaultFolder->getSubfolder('ai-images');
        }

        $destinationPath = Environment::getPublicPath() . $aiImagesFolder->getPublicUrl();

        $title = FileNameSanitizerService::sanitize($title);
        $targetFile = $this->filesystem->exists($destinationPath . $title . '.' . $fileExtension) ? $title . '-' . time() . '.' . $fileExtension : $title . '.' . $fileExtension;

        $this->filesystem->copy(
            $imageUrl,
            $destinationPath . $targetFile
        );
        $newFile = $aiImagesFolder->getFile($targetFile);
        return $newFile->getUid();
    }

    protected function newStringPlaceholder(string $table, $key = 0): string
    {
        return 'NEW' .
            substr(
                md5(time() . $table . $key . rand(0, 100000)),
                0,
                22
            );
    }
}
