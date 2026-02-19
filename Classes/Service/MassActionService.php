<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\BackgroundTask;
use AutoDudes\AiSuite\Domain\Model\Dto\FileMetadata;
use AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer\ClientAnswer;
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MassActionService implements SingletonInterface
{
    protected MetadataService $metadataService;
    protected ResourceFactory $resourceFactory;
    protected BackgroundTaskRepository $backgroundTaskRepository;
    protected LoggerInterface $logger;
    protected UuidService $uuidService;

    protected SiteService $siteService;
    protected LibraryService $libraryService;
    protected TranslationService $translationService;
    protected SessionService $sessionService;
    protected SysFileMetadataRepository $sysFileMetadataRepository;
    protected DirectiveService $directiveService;
    protected GlobalInstructionService $globalInstructionService;
    protected array $supportedMimeTypes;

    public function __construct(
        MetadataService $metadataService,
        ResourceFactory $resourceFactory,
        BackgroundTaskRepository $backgroundTaskRepository,
        UuidService $uuidService,
        SiteService $siteService,
        LibraryService $libraryService,
        TranslationService $translationService,
        SessionService $sessionService,
        SysFileMetadataRepository $sysFileMetadataRepository,
        DirectiveService $directiveService,
        GlobalInstructionService $globalInstructionService,
        LoggerInterface $logger
    ) {
        $this->metadataService = $metadataService;
        $this->resourceFactory = $resourceFactory;
        $this->backgroundTaskRepository = $backgroundTaskRepository;
        $this->uuidService = $uuidService;
        $this->siteService = $siteService;
        $this->libraryService = $libraryService;
        $this->translationService = $translationService;
        $this->sessionService = $sessionService;
        $this->sysFileMetadataRepository = $sysFileMetadataRepository;
        $this->directiveService = $directiveService;
        $this->globalInstructionService = $globalInstructionService;
        $this->logger = $logger;

        $this->supportedMimeTypes = [
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/webp",
        ];
    }

    public function filelistFileDirectorySupport(ClientAnswer $librariesAnswer): array
    {
        $directoryId = $this->sessionService->getFilelistFolderId();
        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_massaction_filelist_files_prepare');

        $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
        $textGenerationLibraries = array_filter($textGenerationLibraries, function ($library) {
            return $library['name'] === 'Vision' || $library['model_identifier'] === 'MittwaldMinistral14BVision';
        });

        $availableLanguages = $this->siteService->getAvailableLanguages(true);

        $pendingFileMetadata = [];
        $fileMetadata = [];
        $unsupportedFileMetadata = [];
        $folderName = '';
        if ($directoryId !== '') {
            $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($directoryId);
            $files = $folder->getFiles();
            $folderName = $folder->getName();

            if (count($files) > 0) {
                $fileUids = [0];
                foreach ($files as $file) {
                    if ($this->metadataService->hasFilePermissions($file->getUid()) && $file->getType() === 2) {
                        $fileUids[] = $file->getUid();
                    }
                }

                $languageParts = isset($sessionData['options']['sysLanguage']) ? explode('__', $sessionData['options']['sysLanguage']) : [];
                $column = $sessionData['options']['column'] ?? 'all';

                $languageId = isset($languageParts[1]) ? (int)$languageParts[1] : 0;
                $metadataList = $this->sysFileMetadataRepository->findByLangUidAndFileIdList(
                    $fileUids,
                    $column,
                    'file',
                    $languageId
                );

                if ($languageId > 0) {
                    $translatedFileUids = array_keys($metadataList);

                    $nonTranslatedFileUids = array_diff($fileUids, $translatedFileUids);

                    $defaultLanguageMetadataUids = $this->sysFileMetadataRepository->findDefaultLanguageMetadataUidsByFileUids($nonTranslatedFileUids);

                    foreach ($nonTranslatedFileUids as $fileUid) {
                        if ($fileUid === 0) {
                            continue;
                        }
                        $defaultMetadataUid = $defaultLanguageMetadataUids[$fileUid] ?? 0;
                        if ($defaultMetadataUid === 0) {
                            $this->logger->error('Missing default file metadata for file ' . $fileUid);
                            continue;
                        }
                        $metadataList[$fileUid] = [
                            'uid' => $defaultMetadataUid,
                            'file' => $fileUid,
                            'title' => '',
                            'alternative' => '',
                            'description' => '',
                            'mode' => 'NEW'
                        ];
                    }
                }

                $showOnlyEmpty = isset($sessionData['options']['showOnlyEmpty']);
                $showOnlyUsed = isset($sessionData['options']['showOnlyUsed']);
                if ($showOnlyEmpty || $showOnlyUsed) {
                    $metadataList = $this->filterMetadataList($metadataList, $column, $showOnlyEmpty, $showOnlyUsed);
                }

                $translatedMetadata = [];
                $nonTranslatedMetadata = [];
                foreach ($metadataList as $fileUid => $metadata) {
                    if (isset($metadata['mode']) && $metadata['mode'] === "NEW") {
                        $nonTranslatedMetadata[$fileUid] = $metadata;
                    } else {
                        $translatedMetadata[$fileUid] = $metadata;
                    }
                }
                $translatedMetadataUids = array_column($translatedMetadata, 'uid');
                $nonTranslatedMetadataUids = array_column($nonTranslatedMetadata, 'uid');
                $pendingTranslatedFileMetadata = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($translatedMetadataUids, 'sys_file_metadata', $column, '', 'metadata');
                $pendingTranslatedFileMetadata = array_reduce($pendingTranslatedFileMetadata, function ($task, $item) {
                    $task[$item['table_uid']] = $item['status'];
                    return $task;
                }, []);
                $pendingNonTranslatedFileMetadata = [];
                if ($languageId > 0) {
                    $pendingNonTranslatedFileMetadata = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($nonTranslatedMetadataUids, 'sys_file_metadata', $column, 'NEW', 'metadata');
                    $pendingNonTranslatedFileMetadata = array_reduce($pendingNonTranslatedFileMetadata, function ($task, $item) {
                        $task[$item['table_uid']] = $item['status'];
                        return $task;
                    }, []);
                }
                $pendingFileMetadata = $pendingTranslatedFileMetadata + $pendingNonTranslatedFileMetadata;

                foreach ($files as $file) {
                    if ($file->checkActionPermission('write') && strpos('image', $file->getMimeType()) !== -1) {
                        if (array_key_exists($file->getUid(), $metadataList)) {
                            $fileMeta = $metadataList[$file->getUid()];
                            if (in_array($file->getMimeType(), $this->supportedMimeTypes)) {
                                $fileMetadata[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                            } else {
                                $unsupportedFileMetadata[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                            }
                        }
                    }
                }
            }
        }

        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'metadata', null, $directoryId);

        return [
            'directory' => $directoryId,
            'directoryName' => $folderName,
            'fileMetadata' => $fileMetadata,
            'unsupportedFileMetadata' => $unsupportedFileMetadata,
            'depths' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5],
            'columns' => array_merge_recursive(
                ['all' => $this->translationService->translate('tx_aisuite.module.massActionFilelist.allColumns')],
                $this->metadataService->getFileMetadataColumns()
            ),
            'activeColumn' => $sessionData['options']['column'] ?? 'all',
            'sysLanguages' => $availableLanguages,
            'alreadyPendingFiles' => $pendingFileMetadata,
            'parentUuid' => $this->uuidService->generateUuid(),
            'textGenerationLibraries' => $this->libraryService->prepareLibraries($textGenerationLibraries),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
            'preSelection' => $sessionData['options'] ?? [],
            'maxAllowedFileSize' => $this->directiveService->getEffectiveMaxUploadSize(),
            'globalInstructions' => $globalInstructions,
        ];
    }

    private function filterMetadataList(array $metadataList, string $column, bool $showOnlyEmpty, bool $showOnlyUsed): array
    {
        $filteredList = $metadataList;

        if ($showOnlyEmpty) {
            $filteredList = array_filter($filteredList, function ($metadata) use ($column) {
                return $this->isMetadataEmpty($metadata, $column);
            });
        }

        if ($showOnlyUsed) {
            $filteredList = array_filter($filteredList, function ($metadata) {
                return $this->sysFileMetadataRepository->isFileUsed($metadata['file']);
            });
        }

        return $filteredList;
    }

    private function isMetadataEmpty(array $metadata, string $column): bool
    {
        if ($column === 'title') {
            return empty($metadata['title']);
        }

        if ($column === 'alternative') {
            return empty($metadata['alternative']);
        }

        if ($column === 'description') {
            return empty($metadata['description']);
        }

        return empty($metadata['title']) && empty($metadata['alternative']) && empty($metadata['description']);
    }

    public function getFolderCombinedIdentifier(int $fileUid): ?string
    {
        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
            return $file->getParentFolder()->getCombinedIdentifier();
        } catch (\Exception $e) {
            $this->logger->error('Could not get folder identifier for file ' . $fileUid . ': ' . $e->getMessage());
            return null;
        }
    }

    public function filelistFileTranslationDirectorySupport(ClientAnswer $librariesAnswer): array
    {
        $directoryId = $this->sessionService->getFilelistFolderId();
        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_massaction_filelist_files_translate_prepare');
        $sourceLanguageParts = isset($sessionData['options']['sourceLanguage']) ? explode('__', $sessionData['options']['sourceLanguage']) : [];
        $targetLanguageParts = isset($sessionData['options']['targetLanguage']) ? explode('__', $sessionData['options']['targetLanguage']) : [];
        $sourceLanguageId = isset($sourceLanguageParts[1]) ? (int)$sourceLanguageParts[1] : 0;
        $targetLanguageId = isset($targetLanguageParts[1]) ? (int)$targetLanguageParts[1] : 0;
        $column = $sessionData['options']['column'] ?? 'all';

        $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];

        $pendingFileMetadata = [];
        $fileMetadata = [];
        $folderName = '';

        if ($directoryId !== '') {
            $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($directoryId);
            $files = $folder->getFiles();
            $folderName = $folder->getName();

            if (count($files) > 0) {
                $fileUids = [0];
                foreach ($files as $file) {
                    if ($this->metadataService->hasFilePermissions($file->getUid()) &&
                        ($file->getType() === 2 || $file->getType() === 4 || $file->getType() === 5)
                    ) {
                        $fileUids[] = $file->getUid();
                    }
                }

                $sourceMetadataList = $this->sysFileMetadataRepository->findByLangUidAndFileIdList(
                    $fileUids,
                    $column,
                    'file',
                    $sourceLanguageId
                );

                $targetMetadataList = $this->sysFileMetadataRepository->findByLangUidAndFileIdList(
                    $fileUids,
                    $column,
                    'file',
                    $targetLanguageId
                );

                $translationData = [];
                foreach ($sourceMetadataList as $fileUid => $sourceMetadata) {
                    $targetMetadata = $targetMetadataList[$fileUid] ?? null;

                    if ($targetMetadata === null && $targetLanguageId > 0) {
                        $defaultLanguageMetadataUids = $this->sysFileMetadataRepository->findDefaultLanguageMetadataUidsByFileUids([$fileUid]);
                        $defaultMetadataUid = $defaultLanguageMetadataUids[$fileUid] ?? 0;

                        if ($defaultMetadataUid > 0) {
                            $targetMetadata = [
                                'uid' => $defaultMetadataUid,
                                'file' => $fileUid,
                                'title' => '',
                                'alternative' => '',
                                'description' => '',
                                'mode' => 'NEW'
                            ];
                        }
                    }

                    if ($targetMetadata !== null) {
                        $translationData[$fileUid] = [
                            'source' => $sourceMetadata,
                            'target' => $targetMetadata
                        ];
                    }
                }

                $showOnlyUsed = isset($sessionData['options']['showOnlyUsed']);
                if ($showOnlyUsed) {
                    $filteredTranslationData = [];
                    foreach ($translationData as $fileUid => $data) {
                        $metadataList = [$fileUid => $data['target']];
                        $filtered = $this->filterMetadataList($metadataList, $column, false, $showOnlyUsed);
                        if (!empty($filtered)) {
                            $filteredTranslationData[$fileUid] = $data;
                        }
                    }
                    $translationData = $filteredTranslationData;
                }

                $translatedMetadataUids = [];
                $nonTranslatedMetadataUids = [];
                foreach ($translationData as $fileUid => $data) {
                    if (isset($data['target']['mode']) && $data['target']['mode'] === 'NEW') {
                        $nonTranslatedMetadataUids[] = $data['target']['uid'];
                    } else {
                        $translatedMetadataUids[] = $data['target']['uid'];
                    }
                }
                $pendingTranslatedFileMetadata = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($translatedMetadataUids, 'sys_file_metadata', $column, '', 'translation', $targetLanguageId);
                $pendingTranslatedFileMetadata = array_reduce($pendingTranslatedFileMetadata, function ($task, $item) {
                    $task[$item['table_uid']] = $item['status'];
                    return $task;
                }, []);
                $pendingNonTranslatedFileMetadata = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($nonTranslatedMetadataUids, 'sys_file_metadata', $column, 'NEW', 'translation', $targetLanguageId);
                $pendingNonTranslatedFileMetadata = array_reduce($pendingNonTranslatedFileMetadata, function ($task, $item) {
                    $task[$item['table_uid']] = $item['status'];
                    return $task;
                }, []);

                $pendingFileMetadata = $pendingTranslatedFileMetadata + $pendingNonTranslatedFileMetadata;

                foreach ($files as $file) {
                    if ($file->checkActionPermission('write') &&
                        ($file->getType() === 2 || $file->getType() === 4 || $file->getType() === 5)
                    ) {
                        if (array_key_exists($file->getUid(), $translationData)) {
                            $data = $translationData[$file->getUid()];
                            $fileMeta = $data['target'];
                            $fileMeta['sourceMetadata'] = $data['source'];
                            $fileMetadata[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                        }
                    }
                }
            }
        }

        //$globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'translation', null, $directoryId);
        return [
            'directory' => $directoryId,
            'directoryName' => $folderName,
            'fileMetadata' => $fileMetadata,
            'columns' => array_merge_recursive(
                ['all' => $this->translationService->translate('tx_aisuite.module.massActionFilelist.allColumns')],
                $this->metadataService->getFileMetadataColumns()
            ),
            'activeColumn' => $sessionData['options']['column'] ?? 'all',
            'alreadyPendingFiles' => $pendingFileMetadata,
            'parentUuid' => $this->uuidService->generateUuid(),
            'textGenerationLibraries' => $this->libraryService->prepareLibraries($textGenerationLibraries),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
            'preSelection' => $sessionData['options'] ?? [],
            'maxAllowedFileSize' => $this->directiveService->getEffectiveMaxUploadSize(),
            'globalInstructions' => '',
            'equalLanguages' => $sourceLanguageId === $targetLanguageId,
        ];
    }

    /**
     * Processes filelist files for metadata generation with batch processing
     *
     * @param array $massActionData Mass action data from request
     * @param array $massActionDataFiles Decoded files data
     * @param array $languageParts Language parts [locale, id]
     * @param string $scope Processing scope (e.g., 'fileMetadata')
     * @param SendRequestService $requestService Request service for API calls
     * @return array Returns ['payload', 'bulkPayload', 'failedFilesMetadata']
     */
    public function processFilelistFilesForMetadataGeneration(
        array $massActionData,
        array $massActionDataFiles,
        array $languageParts,
        string $scope,
        SendRequestService $requestService
    ): array {
        $filesMetadataUidList = [];
        $files = [];
        foreach ($massActionDataFiles as $sysFileMetaUid => $data) {
            $filesMetadataUidList[] = $sysFileMetaUid;
            $fileMetaData = $massActionDataFiles[$sysFileMetaUid];
            foreach ($fileMetaData as $column => $value) {
                if ($massActionData['column'] === $column || $massActionData['column'] === 'all') {
                    $files[$sysFileMetaUid][$column] = $value;
                }
            }
            $files[$sysFileMetaUid]['mode'] = $fileMetaData['mode'];
        }

        $metadataListFromRepo = [];
        if (count($filesMetadataUidList) > 0) {
            $metadataListFromRepo = $this->sysFileMetadataRepository->findByUidList($filesMetadataUidList);
        }

        $payload = [];
        $bulkPayload = [];
        $failedFilesMetadata = [];
        $allowedFileSize = $this->directiveService->getEffectiveMaxUploadSize();
        $fileSizeSumInBytes = 0;

        foreach ($files as $sysFileMetaUid => $columns) {
            foreach ($columns as $column => $value) {
                try {
                    if ($column === 'mode') {
                        continue;
                    }
                    $fileUid = (int)$metadataListFromRepo[$sysFileMetaUid]['file'];
                    $defaultSysFileMetaUid = (int)$sysFileMetaUid;
                    $targetLanguageId = (int)$languageParts[1];

                    $fileContent = $this->metadataService->getFileContent($fileUid);
                    $fileSize = strlen($fileContent);
                    $filename = $this->metadataService->getFilename($fileUid);

                    if (($fileSizeSumInBytes + $fileSize) >= $allowedFileSize && count($payload) > 0) {
                        $answer = $requestService->sendDataRequest(
                            'createMassAction',
                            [
                                'uuid' => $massActionData['parentUuid'],
                                'payload' => $payload,
                                'scope' => $scope,
                                'type' => 'metadata'
                            ],
                            '',
                            $languageParts[0],
                            [
                                'text' => $massActionData['textAiModel'],
                            ]
                        );

                        if ($answer->getType() === 'Error') {
                            throw new \Exception($answer->getResponseData()['message']);
                        }
                        $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
                        $payload = [];
                        $bulkPayload = [];
                        $fileSizeSumInBytes = 0;
                    }

                    $uuid = $this->uuidService->generateUuid();

                    $bulkPayload[] = new BackgroundTask(
                        $scope,
                        'metadata',
                        $massActionData['parentUuid'],
                        $uuid,
                        $column,
                        'sys_file_metadata',
                        'uid',
                        $defaultSysFileMetaUid,
                        $targetLanguageId,
                        $columns['mode']
                    );
                    $folderCombinedIdentifier = $this->getFolderCombinedIdentifier($fileUid);
                    $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'metadata', null, $folderCombinedIdentifier);
                    $globalInstructionsOverride = $this->globalInstructionService->checkOverridePredefinedPrompt('files', 'metadata', [$folderCombinedIdentifier]);
                    $payload[] = [
                        'field_label' => $column,
                        'request_content' => $fileContent,
                        'uuid' => $uuid,
                        'global_instructions' => $globalInstructions,
                        'override_predefined_prompt' => $globalInstructionsOverride,
                        'filename' => $filename,
                    ];
                    $fileSizeSumInBytes += $fileSize;
                } catch (\Exception $e) {
                    $this->logger->error('Error while fetching file content for file ' . $fileUid . ' with sys file metadata uid ' . $sysFileMetaUid . ': ' . $e->getMessage());
                    $failedFilesMetadata[] = $fileUid;
                }
            }
        }

        return [
            'payload' => $payload,
            'bulkPayload' => $bulkPayload,
            'failedFilesMetadata' => $failedFilesMetadata
        ];
    }

    public function handleMetadaGenerationAfterFileAdded(
        FileInterface $file,
        array $extConf
    ): void {
        try {
            $fileMetadata = $file->getMetaData();
            $fileMetadataUid = (int)$fileMetadata->offsetGet('uid');
            $massActionDataFiles = [
                $fileMetadataUid => [
                    'mode' => ''
                ]
            ];
            if ((bool)$extConf['metadataAutogenerateAlternative'] && (bool)$extConf['metadataAutogenerateTitle']) {
                $column = 'all';
                $massActionDataFiles[$fileMetadataUid]['title'] = '';
                $massActionDataFiles[$fileMetadataUid]['alternative'] = '';
            } elseif ((bool)$extConf['metadataAutogenerateTitle']) {
                $column = 'title';
                $massActionDataFiles[$fileMetadataUid]['title'] = '';
            } else {
                $column = 'alternative';
                $massActionDataFiles[$fileMetadataUid]['alternative'] = '';
            }

            $availableSourceLanguages = $this->siteService->getAvailableLanguages(true, 0, true);
            $firstLanguageKey = array_key_first($availableSourceLanguages);
            $massActionData = [
                'parentUuid' => $this->uuidService->generateUuid(),
                'column' => $column,
                'sysLanguage' => $firstLanguageKey,
                'textAiModel' => $extConf['metadataAutogenerateModel'],
            ];
            $scope = 'fileMetadata';
            $languageParts = explode('__', $massActionData['sysLanguage']);

            $requestService = GeneralUtility::makeInstance(SendRequestService::class);
            $result = $this->processFilelistFilesForMetadataGeneration(
                $massActionData,
                $massActionDataFiles,
                $languageParts,
                $scope,
                $requestService
            );

            $payload = $result['payload'];
            $bulkPayload = $result['bulkPayload'];
            if (count($payload) > 0) {
                $requestService = GeneralUtility::makeInstance(SendRequestService::class);
                $answer = $requestService->sendDataRequest(
                    'createMassAction',
                    [
                        'uuid' => $massActionData['parentUuid'],
                        'payload' => $payload,
                        'scope' => $scope,
                        'type' => 'metadata'
                    ],
                    '',
                    $languageParts[0],
                    [
                        'text' => $extConf['metadataAutogenerateModel'],
                    ]
                );

                if ($answer->getType() === 'Error') {
                    $this->logger->error('Error generating metadata for file UID ' . $file->getUid() . ': ' . $answer->getResponseData()['message']);
                    $this->metadataService->flashMessage(
                        $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.errorGeneratingAutoUpload.message', [$file->getName()]),
                        $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.errorGeneratingAutoUpload.title'),
                        ContextualFeedbackSeverity::ERROR
                    );
                    return;
                }

                $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
                $this->metadataService->flashMessage(
                    $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.autoGenerationSuccess.message'),
                    $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.autoGenerationSuccess.title'),
                    ContextualFeedbackSeverity::INFO
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Exception while generating metadata for uploaded file ' . $file->getName() . ': ' . $e->getMessage());
            $this->metadataService->flashMessage(
                $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.unexpectedErrorAutoGenerateUpload.message'),
                $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.unexpectedErrorAutoGenerateUpload.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
    }
}
