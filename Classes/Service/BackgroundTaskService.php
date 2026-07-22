<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackgroundTaskService implements SingletonInterface
{
    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
        protected readonly PagesRepository $pagesRepository,
        protected readonly SiteFinder $siteFinder,
        protected readonly PageRepository $pageRepository,
        protected readonly MetadataService $metadataService,
        protected readonly LoggerInterface $logger,
        protected readonly SendRequestService $sendRequestService,
        protected readonly TranslationService $translationService,
        protected readonly LocalizationService $localizationService,
        protected readonly IconService $iconService,
        protected readonly SiteService $siteService,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly SysFileMetadataRepository $sysFileMetadataRepository,
    ) {}

    /**
     * @param array<mixed> $backgroundTasks
     * @param array<mixed> $uuidStatus
     */
    public function prefillArrays(array &$backgroundTasks, array &$uuidStatus): void
    {
        $foundBackgroundTasksPages = $this->backgroundTaskRepository->findAllPageBackgroundTasks();
        $counter = [
            'seo_title' => 0,
            'description' => 0,
            'og_title' => 0,
            'og_description' => 0,
            'twitter_title' => 0,
            'twitter_description' => 0,
            'abstract' => 0,
        ];
        foreach ($foundBackgroundTasksPages as $foundBackgroundTask) {
            if (!($this->backendUserService->getBackendUser()?->isInWebMount($foundBackgroundTask['table_uid']) ?? false)) {
                continue;
            }

            try {
                if (-1 === $foundBackgroundTask['sys_language_uid']) {
                    $foundBackgroundTask['flag'] = 'flags-multiple';
                } else {
                    $page = $this->pageRepository->getPage($foundBackgroundTask['table_uid']);
                    $pageId = $foundBackgroundTask['table_uid'];
                    if (1 === $page['is_siteroot'] && $page['l10n_parent'] > 0) {
                        $pageId = $page['l10n_parent'];
                    }
                    $site = $this->siteFinder->getSiteByPageId($pageId);
                    $foundBackgroundTask['flag'] = $site->getLanguageById($foundBackgroundTask['sys_language_uid'])->getFlagIdentifier();
                }
            } catch (\Exception $e) {
                $foundBackgroundTask['flag'] = '';
            }
            if (!array_key_exists($foundBackgroundTask['column'], $counter)) {
                continue;
            }
            if ($counter[$foundBackgroundTask['column']] < 50) {
                $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['column']][$foundBackgroundTask['table_uid']] = $foundBackgroundTask;
            }
            $uuidStatus[$foundBackgroundTask['uuid']] = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status'] ?? 'pending',
            ];
            ++$counter[$foundBackgroundTask['column']];
        }

        $foundBackgroundTasksFiles = $this->backgroundTaskRepository->findAllFileReferenceBackgroundTasks();
        $counter = [
            'title' => 0,
            'alternative' => 0,
            'description' => 0,
        ];
        foreach ($foundBackgroundTasksFiles as $foundBackgroundTask) {
            if ($this->backendUserService->canEditFileReferenceMetadata($foundBackgroundTask['fileUid'])) {
                $foundBackgroundTask['columnValue'] = $foundBackgroundTask[$foundBackgroundTask['column']];

                try {
                    if (-1 === $foundBackgroundTask['sys_language_uid']) {
                        $foundBackgroundTask['flag'] = 'flags-multiple';
                    } else {
                        $page = $this->pageRepository->getPage($foundBackgroundTask['pageId']);
                        $pageId = $foundBackgroundTask['pageId'];
                        if (1 === $page['is_siteroot'] && $page['l10n_parent'] > 0) {
                            $pageId = $page['l10n_parent'];
                        }
                        $site = $this->siteFinder->getSiteByPageId($pageId);
                        $foundBackgroundTask['flag'] = $site->getLanguageById($foundBackgroundTask['sys_language_uid'])->getFlagIdentifier();
                    }
                } catch (\Throwable $e) {
                    $foundBackgroundTask['flag'] = '';
                }
                if (!array_key_exists($foundBackgroundTask['column'], $counter)) {
                    continue;
                }
                if ($counter[$foundBackgroundTask['column']] < 50) {
                    $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['column']][$foundBackgroundTask['table_uid']] = $foundBackgroundTask;
                }
                $uuidStatus[$foundBackgroundTask['uuid']] = [
                    'uuid' => $foundBackgroundTask['uuid'],
                    'status' => $foundBackgroundTask['status'] ?? 'pending',
                ];
                ++$counter[$foundBackgroundTask['column']];
            }
        }

        $foundBackgroundTasksFileMetadata = $this->backgroundTaskRepository->findAllFileMetadataBackgroundTasks();
        $counter = [
            'title' => 0,
            'alternative' => 0,
            'description' => 0,
        ];
        foreach ($foundBackgroundTasksFileMetadata as $foundBackgroundTask) {
            if ($this->backendUserService->canEditFileMetadata($foundBackgroundTask['fileUid'])) {
                if ('NEW' === $foundBackgroundTask['mode']) {
                    $foundBackgroundTask['title'] = '';
                    $foundBackgroundTask['alternative'] = '';
                    $foundBackgroundTask['description'] = '';
                    $foundBackgroundTask['columnValue'] = '';
                } else {
                    $foundBackgroundTask['columnValue'] = $foundBackgroundTask[$foundBackgroundTask['column']];
                }

                $sourceLanguageFlags = $this->siteService->getLanguageFlagsByLanguageId($foundBackgroundTask['sys_language_uid']);
                $foundBackgroundTask['sourceLanguageFlags'] = $sourceLanguageFlags;

                if (!array_key_exists($foundBackgroundTask['column'], $counter)) {
                    continue;
                }
                if ($counter[$foundBackgroundTask['column']] < 50) {
                    $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['column']][$foundBackgroundTask['table_uid']] = $foundBackgroundTask;
                }
                $uuidStatus[$foundBackgroundTask['uuid']] = [
                    'uuid' => $foundBackgroundTask['uuid'],
                    'status' => $foundBackgroundTask['status'] ?? 'pending',
                ];
                ++$counter[$foundBackgroundTask['column']];
            }
        }

        $foundBackgroundTasksFileMetadataTranslation = $this->backgroundTaskRepository->findAllFileMetadataTranslationBackgroundTasks();
        $counter = [
            'title' => 0,
            'alternative' => 0,
            'description' => 0,
        ];
        foreach ($foundBackgroundTasksFileMetadataTranslation as $foundBackgroundTask) {
            if ($this->backendUserService->canEditFileMetadata($foundBackgroundTask['fileUid'])) {
                $foundBackgroundTask['columnValue'] = $foundBackgroundTask[$foundBackgroundTask['column']];
                $defaultSysFileMetadataRow = $this->backgroundTaskRepository->findSourceColumnValueByFileUidAndDefaultLanguage($foundBackgroundTask['fileUid']);
                $foundBackgroundTask['sourceColumnValue'] = $defaultSysFileMetadataRow[$foundBackgroundTask['column']] ?? '';

                $defaultLanguageFlags = $this->siteService->getLanguageFlagsByLanguageId(0);
                $targetLanguageFlags = $this->siteService->getLanguageFlagsByLanguageId($foundBackgroundTask['sys_language_uid']);
                $foundBackgroundTask['sourceLanguageFlags'] = $defaultLanguageFlags;
                $foundBackgroundTask['targetLanguageFlags'] = $targetLanguageFlags;

                if (!array_key_exists($foundBackgroundTask['column'], $counter)) {
                    continue;
                }
                if ($counter[$foundBackgroundTask['column']] < 50) {
                    $backgroundTasks['fileMetadataTranslation'][$foundBackgroundTask['column']][$foundBackgroundTask['table_uid']] = $foundBackgroundTask;
                }
                $uuidStatus[$foundBackgroundTask['uuid']] = [
                    'uuid' => $foundBackgroundTask['uuid'],
                    'status' => $foundBackgroundTask['status'] ?? 'pending',
                ];
                ++$counter[$foundBackgroundTask['column']];
            }
        }
    }

    /**
     * @param array<string, mixed> $backgroundTasks
     * @param array<string, mixed> $fetchedStatusData
     */
    public function mergeBackgroundTasksAndUpdateStatus(array &$backgroundTasks, array $fetchedStatusData): void
    {
        if (count($fetchedStatusData) > 0) {
            $this->backgroundTaskRepository->updateStatus($fetchedStatusData);
        }

        foreach ($backgroundTasks as $scope => $tasksByColumn) {
            foreach ($tasksByColumn as $column => $tasks) {
                foreach ($tasks as $key => $task) {
                    if (array_key_exists($task['uuid'], $fetchedStatusData) && array_key_exists('status', $fetchedStatusData[$task['uuid']]) && array_key_exists('answer', $fetchedStatusData[$task['uuid']])) {
                        $backgroundTasks[$scope][$column][$key]['status'] = $fetchedStatusData[$task['uuid']]['status'];
                        $answer = json_decode($fetchedStatusData[$task['uuid']]['answer'] ?? '', true);
                    } else {
                        $backgroundTasks[$scope][$column][$key]['status'] = $task['status'];
                        $answer = json_decode($task['answer'] ?? '', true);
                    }
                    if (isset($answer['type'])) {
                        if ('Metadata' === $answer['type'] && isset($answer['body']['metadataResult'])) {
                            $result = array_filter($answer['body']['metadataResult'], 'is_string');
                            $extConf = $this->extensionConfiguration->get('ai_suite');
                            $suggestionCount = (int) $extConf['metadataSuggestionCount'];
                            $result = array_slice($result, 0, $suggestionCount);
                            $backgroundTasks[$scope][$column][$key]['metadataSuggestions'] = $result;
                        } elseif ('Translate' === $answer['type'] && isset($answer['body']['translationResults']['sys_file_metadata'])) {
                            $translationSuggestionAnswer = $answer['body']['translationResults']['sys_file_metadata'];
                            $uidKey = array_key_first($translationSuggestionAnswer);
                            $translationSuggestion = $translationSuggestionAnswer[$uidKey][$column];
                            $backgroundTasks[$scope][$column][$key]['translationSuggestions'] = [$translationSuggestion];
                        } elseif ('Error' === $answer['type'] && isset($answer['body']['message'])) {
                            $backgroundTasks[$scope][$column][$key]['error'] = $answer['body']['message'];
                        }
                    }
                    unset($backgroundTasks[$scope][$column][$key]['answer']);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $backgroundTasks
     *
     * @return array<string, mixed>
     */
    public function getBackgroundTasksStatistics(array $backgroundTasks): ?array
    {
        try {
            $statuses = ['finished', 'pending', 'taskError'];
            $scopes = ['page', 'fileReference', 'fileMetadata', 'fileMetadataTranslation'];

            /** @var array<string, array<string, mixed>> $statistics */
            $statistics = [
                'total' => [
                    'finished' => 0,
                    'pending' => 0,
                    'taskError' => 0,
                    'total' => 0,
                ],
            ];

            foreach ($scopes as $scope) {
                $statistics[$scope] = [
                    'finished' => 0,
                    'pending' => 0,
                    'taskError' => 0,
                    'total' => 0,
                    'columns' => [],
                ];
            }

            $pageMetadataColumns = $this->metadataService->getMetadataColumns();
            $fileMetadataColumns = $this->metadataService->getFileMetadataColumns();

            foreach ($backgroundTasks as $scope => $tasksByColumn) {
                foreach ($tasksByColumn as $column => $tasks) {
                    foreach ($tasks as $task) {
                        $status = 'task-error' === $task['status'] ? 'taskError' : $task['status'];

                        if (!in_array($scope, $scopes) || !in_array($status, $statuses)) {
                            continue;
                        }

                        ++$statistics['total'][$status];
                        ++$statistics['total']['total'];

                        ++$statistics[$scope][$status];
                        ++$statistics[$scope]['total'];

                        if (!isset($statistics[$scope]['columns'][$column])) {
                            $columnName = match ($scope) {
                                'page' => $pageMetadataColumns[$column] ?? $column,
                                'fileMetadataTranslation' => $fileMetadataColumns[$column] ?? $column,
                                default => $fileMetadataColumns[$column] ?? $column,
                            };

                            $statistics[$scope]['columns'][$column] = [
                                'finished' => 0,
                                'pending' => 0,
                                'taskError' => 0,
                                'total' => 0,
                                'columnName' => $columnName,
                            ];
                        }
                        ++$statistics[$scope]['columns'][$column][$status];
                        ++$statistics[$scope]['columns'][$column]['total'];
                    }
                }
            }

            return $statistics;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function collectUuidsForStatusUpdate(): array
    {
        $uuidStatus = [];

        $this->collectPageBackgroundTasks($uuidStatus);
        $this->collectFileReferenceBackgroundTasks($uuidStatus);
        $this->collectFileMetadataBackgroundTasks($uuidStatus);
        $this->collectPageTranslationBackgroundTasks($uuidStatus);
        $this->collectContentElementTranslationBackgroundTasks($uuidStatus);
        $this->collectFileMetadataTranslationBackgroundTasks($uuidStatus);

        return $uuidStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchStructuredBackgroundTaskStatus(): array
    {
        $uuidStatus = [];

        $this->collectPageBackgroundTasks($uuidStatus, true);
        $this->collectFileReferenceBackgroundTasks($uuidStatus, true);
        $this->collectFileMetadataBackgroundTasks($uuidStatus, true);
        $this->collectPageTranslationBackgroundTasks($uuidStatus, true);
        $this->collectContentElementTranslationBackgroundTasks($uuidStatus, true);
        $this->collectFileMetadataTranslationBackgroundTasks($uuidStatus, true);

        return $uuidStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchBackgroundTaskStatus(bool $update = false): array
    {
        if ($update) {
            $uuidStatus = $this->collectUuidsForStatusUpdate();
            if (count($uuidStatus) > 0) {
                $sendRequestService = GeneralUtility::makeInstance(
                    SendRequestService::class
                );

                $answer = $sendRequestService->sendDataRequest('massActionStatus', [
                    'uuidStatus' => $uuidStatus,
                ]);

                if ('Error' !== $answer->getType()) {
                    $statusData = $answer->getResponseData()['statusData'] ?? [];
                    $this->backgroundTaskRepository->updateStatus($statusData);
                }
            }
        }

        return $this->fetchStructuredBackgroundTaskStatus();
    }

    public function generateTranslationPageButtons(ServerRequestInterface $request): string
    {
        if (!($this->backendUserService->getBackendUser()?->check('custom_options', 'tx_aisuite_features:enable_translation_whole_page') ?? false)) {
            return '';
        }

        $pageId = $this->translationService->getPageIdFromRequest($request);
        if (0 === $pageId) {
            return '';
        }

        if (!($this->backendUserService->getBackendUser()?->isInWebMount($pageId) ?? false)) {
            return '';
        }

        try {
            $actionButtonsAbove = '<div class="form-row"><div class="form-group d-flex gap-2">';

            $iconLocalize = $this->iconService->getIcon('actions-localize', 'small')->render();
            $translateWholePageButton = '<a href="#" id="aiSuiteTranslateWholePage" class="btn btn-default" data-page-id="'.$pageId.'">'
                .$iconLocalize
                .' '.$this->localizationService->translate('aiSuite.translateWholePage')
                .'</a>';

            $actionButtonsAbove .= $translateWholePageButton;

            $backgroundTasks = $this->fetchBackgroundTaskStatus(true);
            $statusUuid = $this->translationService->addTranslationNotifications($backgroundTasks, $pageId);
            if (!empty($statusUuid)) {
                $parts = explode('__', $statusUuid);
                $status = $parts[0] ?? '';
                $uuid = $parts[1] ?? '';
                if (!empty($uuid)) {
                    if ('error' === $status) {
                        $iconSynchronize = $this->iconService->getIcon('actions-document-synchronize', 'small')->render();
                        $actionButtonsAbove .= '<a href="#" id="aiSuiteTranslationTaskRetry" class="btn btn-default" data-uuid="'.$uuid.'">'
                            .$iconSynchronize
                            .' '.$this->localizationService->translate('aiSuite.action.retryTranslationTask')
                            .'</a>';
                    }
                    $iconSynchronize = $this->iconService->getIcon('actions-delete', 'small')->render();
                    $actionButtonsAbove .= '<a href="#" id="aiSuiteTranslationTaskRemove" class="btn btn-default" data-uuid="'.$uuid.'">'
                        .$iconSynchronize
                        .' '.$this->localizationService->translate('aiSuite.action.removeTranslationTask')
                        .'</a>';
                }
            }
            $actionButtonsAbove .= '</div></div>';

            return $actionButtonsAbove;
        } catch (\Exception $e) {
            $this->logger->error('Error generating translation page buttons', [
                'pageId' => $pageId,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * @param array<string, mixed> $backgroundTask
     * @param array<string, mixed> $data
     */
    public function handleFileMetadataTranslationSave(array $backgroundTask, array $data): void
    {
        if ('NEW' === $backgroundTask['mode']) {
            $sysFileMetadataRow = $this->backgroundTaskRepository->findFileUid($backgroundTask['table_uid'], $data['uuid'], 'translation', 'metadata');
            if (empty($sysFileMetadataRow)) {
                throw new \Exception($this->localizationService->translate('aiSuite.error.backgroundTask.fileUidNotFound', [$data['uuid']]));
            }
            $fileUid = $sysFileMetadataRow['fileUid'];
            $existingMetadataTranslation = $this->sysFileMetadataRepository->findTranslatedMetadataUid($backgroundTask['table_uid'], $fileUid, $backgroundTask['sys_language_uid']);

            if (empty($existingMetadataTranslation)) {
                $cmdmap = [
                    $backgroundTask['table_name'] => [
                        $backgroundTask['table_uid'] => [
                            'localize' => $backgroundTask['sys_language_uid'],
                        ],
                    ],
                ];
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start([], $cmdmap);
                $dataHandler->process_cmdmap();
                if (count($dataHandler->errorLog) > 0) {
                    throw new \Exception(implode(', ', $dataHandler->errorLog));
                }
                $translatedMetadataUid = $dataHandler->copyMappingArray_merged[$backgroundTask['table_name']][$backgroundTask['table_uid']];
            } else {
                $translatedMetadataUid = $existingMetadataTranslation[0];
            }

            $datamap = [
                $backgroundTask['table_name'] => [
                    $translatedMetadataUid => [
                        $backgroundTask['column'] => $data['inputValue'],
                    ],
                ],
            ];
        } else {
            $datamap = [
                $backgroundTask['table_name'] => [
                    $backgroundTask['table_uid'] => [
                        $backgroundTask['column'] => $data['inputValue'],
                    ],
                ],
            ];
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();
        if (count($dataHandler->errorLog) > 0) {
            throw new \Exception(implode(', ', $dataHandler->errorLog));
        }
    }

    /**
     * @param array<string, mixed> $backgroundTask
     * @param array<string, mixed> $data
     */
    public function handleNewMetadataRecordSave(array $backgroundTask, array $data): void
    {
        $sysFileMetadataRow = $this->backgroundTaskRepository->findFileUid($backgroundTask['table_uid'], $data['uuid'], 'metadata', 'fileMetadata');
        if (empty($sysFileMetadataRow)) {
            throw new \Exception($this->localizationService->translate('aiSuite.error.backgroundTask.fileUidNotFound', [$data['uuid']]));
        }
        $fileUid = $sysFileMetadataRow['fileUid'];
        $existingMetadataTranslation = $this->sysFileMetadataRepository->findTranslatedMetadataUid($backgroundTask['table_uid'], $fileUid, $backgroundTask['sys_language_uid']);

        if (empty($existingMetadataTranslation)) {
            $cmdmap = [
                $backgroundTask['table_name'] => [
                    $backgroundTask['table_uid'] => [
                        'localize' => $backgroundTask['sys_language_uid'],
                    ],
                ],
            ];
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], $cmdmap);
            $dataHandler->process_cmdmap();
            if (count($dataHandler->errorLog) > 0) {
                throw new \Exception(implode(', ', $dataHandler->errorLog));
            }
            $translatedMetadataUid = $dataHandler->copyMappingArray_merged[$backgroundTask['table_name']][$backgroundTask['table_uid']];
        } else {
            $translatedMetadataUid = $existingMetadataTranslation[0];
        }

        $datamap = [
            $backgroundTask['table_name'] => [
                $translatedMetadataUid => [
                    $data['column'] => $data['inputValue'],
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();
        if (count($dataHandler->errorLog) > 0) {
            throw new \Exception(implode(', ', $dataHandler->errorLog));
        }
    }

    /**
     * @param array<string, mixed> $backgroundTask
     * @param array<string, mixed> $data
     */
    public function handleExistingMetadataRecordSave(array $backgroundTask, array $data): void
    {
        $datamap = [
            $backgroundTask['table_name'] => [
                $backgroundTask['table_uid'] => [
                    $backgroundTask['column'] => $data['inputValue'],
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();
        if (count($dataHandler->errorLog) > 0) {
            throw new \Exception(implode(', ', $dataHandler->errorLog));
        }
    }

    public function getMaxTasksLimit(): int
    {
        try {
            $extConf = $this->extensionConfiguration->get('ai_suite');

            return (int) ($extConf['maxTasks'] ?? 50);
        } catch (\Exception $e) {
            $this->logger->warning('Could not read extension configuration for maxTasks, using default of 50', [
                'error' => $e->getMessage(),
            ]);

            return 50;
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function retryFailedTasks(array $config = []): array
    {
        try {
            $config['status'] ??= 'failed';
            $maxTasks = $this->getMaxTasksLimit();
            $tasks = $this->backgroundTaskRepository->findBackgroundTasksHandledByCli($maxTasks, $config);

            if (empty($tasks)) {
                return [
                    'success' => true,
                    'processed' => 0,
                    'message' => 'No failed CLI tasks found.',
                ];
            }

            $retried = 0;
            $failed = 0;
            $modelOverride = isset($config['model']) && '' !== $config['model'] ? (string) $config['model'] : null;

            foreach ($tasks as $task) {
                $uuid = $task['uuid'];
                $scope = ('translation' === $task['type']) ? 'translation' : 'metadata';
                $models = [];
                if (null !== $modelOverride) {
                    $modelKey = ('translation' === $task['type']) ? 'translate' : 'text';
                    $models = [$modelKey => $modelOverride];
                }

                $answer = $this->sendRequestService->sendDataRequest(
                    'handleBackgroundTask',
                    [
                        'uuid' => $uuid,
                        'mode' => 'retry',
                        'scope' => $scope,
                    ],
                    '',
                    '',
                    $models,
                );

                if ('Error' === $answer->getType()) {
                    $errorMessage = $answer->getResponseData()['message'] ?? 'Unknown error occurred';
                    $this->logger->error('Error retrying background task', [
                        'uuid' => $uuid,
                        'message' => $errorMessage,
                    ]);
                    ++$failed;

                    continue;
                }

                $this->backgroundTaskRepository->updateStatus([
                    $uuid => [
                        'status' => 'pending',
                        'answer' => '',
                        'error' => '',
                    ],
                ]);

                ++$retried;
            }

            return [
                'success' => true,
                'processed' => $retried,
                'message' => sprintf(
                    'Successfully retried %d task(s).%s',
                    $retried,
                    $failed > 0 ? sprintf(' %d task(s) could not be retried — check logs for details.', $failed) : ''
                ),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception during retryFailedTasks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'processed' => 0,
                'message' => 'An error occurred: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed> ['success' => bool, 'message' => string]
     */
    public function updateAllTaskStatuses(): array
    {
        try {
            $backgroundTasks = [
                'page' => [],
                'pageTranslation' => [],
                'pageTranslate' => [],
                'fileReference' => [],
                'fileMetadata' => [],
                'fileMetadataTranslation' => [],
            ];
            $uuidStatus = [];

            $this->prefillArrays($backgroundTasks, $uuidStatus);

            $foundBackgroundTasksPageTranslation = $this->backgroundTaskRepository->findAllPageTranslationBackgroundTasks();
            foreach ($foundBackgroundTasksPageTranslation as $foundBackgroundTask) {
                $uuidStatus[$foundBackgroundTask['uuid']] = [
                    'uuid' => $foundBackgroundTask['uuid'],
                    'status' => $foundBackgroundTask['status'],
                ];
            }

            $this->collectContentElementTranslationBackgroundTasks($uuidStatus);

            if (count($uuidStatus) > 0) {
                $answer = $this->sendRequestService->sendDataRequest(
                    'massActionStatus',
                    [
                        'uuidStatus' => $uuidStatus,
                    ]
                );
                if ('Error' === $answer->getType()) {
                    $message = $answer->getResponseData()['message'] ?? 'Unknown error during massActionStatus request.';
                    $this->logger->error('Error in massActionStatus request: '.$message);

                    return [
                        'success' => false,
                        'message' => $message,
                    ];
                }
                $statusData = $answer->getResponseData()['statusData'] ?? [];
                $this->mergeBackgroundTasksAndUpdateStatus($backgroundTasks, $statusData);
            }

            $maxTasks = $this->getMaxTasksLimit();
            $tasks = $this->backgroundTaskRepository->findBackgroundTasksHandledByCli(
                $maxTasks,
                ['status' => 'finished']
            );
            if (0 === count($tasks)) {
                return [
                    'success' => true,
                    'message' => 'No tasks found to update!',
                ];
            }

            $processed = 0;
            $failed = 0;
            foreach ($tasks as $task) {
                if ('translation' === $task['type']) {
                    try {
                        if ($this->translationService->processTranslationTask($task)) {
                            ++$processed;
                        } else {
                            ++$failed;
                        }
                    } catch (\Throwable $e) {
                        ++$failed;
                        $this->logger->error('Error processing translation task', [
                            'uuid' => $task['uuid'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    try {
                        $result = $this->processFinishedMetadataTask($task);
                        if (true === $result['success']) {
                            ++$processed;
                        } else {
                            ++$failed;
                        }
                    } catch (\Exception $e) {
                        ++$failed;
                        $this->logger->error('Error processing metadata task', [
                            'uuid' => $task['uuid'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return [
                'success' => true,
                'message' => sprintf(
                    'Successfully updated task status. %d tasks processed successfully, %d tasks failed.',
                    $processed,
                    $failed
                ),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception during updateAllTaskStatuses', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param array<mixed> $uuidStatus
     */
    private function collectPageBackgroundTasks(array &$uuidStatus, bool $structuredResult = false): void
    {
        $foundBackgroundTasksPages = $this->backgroundTaskRepository->findAllPageBackgroundTasks();
        foreach ($foundBackgroundTasksPages as $foundBackgroundTask) {
            if (!($this->backendUserService->getBackendUser()?->isInWebMount($foundBackgroundTask['table_uid']) ?? false)) {
                continue;
            }

            $taskData = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status'],
            ];

            if ($structuredResult) {
                $uuidStatus['pages'][$foundBackgroundTask['table_uid']] = $taskData;
            } else {
                $uuidStatus[$foundBackgroundTask['uuid']] = $taskData;
            }
        }
    }

    /**
     * @param array<mixed> $uuidStatus
     */
    private function collectFileReferenceBackgroundTasks(array &$uuidStatus, bool $structuredResult = false): void
    {
        $foundBackgroundTasksFiles = $this->backgroundTaskRepository->findAllFileReferenceBackgroundTasks();
        foreach ($foundBackgroundTasksFiles as $foundBackgroundTask) {
            if (!$this->backendUserService->canEditFileReferenceMetadata($foundBackgroundTask['fileUid'])) {
                continue;
            }

            $taskData = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status'],
            ];

            if ($structuredResult) {
                $uuidStatus['fileReferences'][$foundBackgroundTask['table_uid']] = $taskData;
            } else {
                $uuidStatus[$foundBackgroundTask['uuid']] = $taskData;
            }
        }
    }

    /**
     * @param array<mixed> $uuidStatus
     */
    private function collectPageTranslationBackgroundTasks(array &$uuidStatus, bool $structuredResult = false): void
    {
        $foundBackgroundTasksPageTranslation = $this->backgroundTaskRepository->findAllPageTranslationBackgroundTasks();
        foreach ($foundBackgroundTasksPageTranslation as $foundBackgroundTask) {
            if (!($this->backendUserService->getBackendUser()?->isInWebMount($foundBackgroundTask['table_uid']) ?? false)) {
                continue;
            }

            $taskData = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status'],
            ];

            if ($structuredResult) {
                $this->mergeTranslationStatus($uuidStatus, (int) $foundBackgroundTask['table_uid'], $taskData);
            } else {
                $uuidStatus[$foundBackgroundTask['uuid']] = $taskData;
            }
        }
    }

    /**
     * @param array<mixed>                        $uuidStatus
     * @param array{uuid: string, status: string} $taskData
     */
    private function mergeTranslationStatus(array &$uuidStatus, int $pageId, array $taskData): void
    {
        $priority = ['finished' => 1, 'pending' => 2, 'task-error' => 3];
        $existingStatus = $uuidStatus['translation'][$pageId]['status'] ?? null;
        $existingPriority = null === $existingStatus ? 0 : ($priority[$existingStatus] ?? 0);
        $newPriority = $priority[$taskData['status'] ?? ''] ?? 0;

        if (null === $existingStatus || $newPriority > $existingPriority) {
            $uuidStatus['translation'][$pageId] = $taskData;
        }
    }

    /**
     * @param array<mixed> $uuidStatus
     */
    private function collectContentElementTranslationBackgroundTasks(array &$uuidStatus, bool $structuredResult = false): void
    {
        $foundBackgroundTasks = $this->backgroundTaskRepository->findAllContentElementTranslationBackgroundTasks();
        foreach ($foundBackgroundTasks as $foundBackgroundTask) {
            $taskData = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status'],
            ];

            if (!$structuredResult) {
                $uuidStatus[$foundBackgroundTask['uuid']] = $taskData;

                continue;
            }

            $contentElement = BackendUtility::getRecord('tt_content', (int) $foundBackgroundTask['table_uid'], 'pid');
            $pageId = (int) ($contentElement['pid'] ?? 0);
            if ($pageId <= 0
                || !($this->backendUserService->getBackendUser()?->isInWebMount($pageId) ?? false)
            ) {
                continue;
            }

            $this->mergeTranslationStatus($uuidStatus, $pageId, $taskData);
        }
    }

    /**
     * @param array<mixed> $uuidStatus
     */
    private function collectFileMetadataTranslationBackgroundTasks(array &$uuidStatus, bool $structuredResult = false): void
    {
        $foundBackgroundTasksFileMetadataTranslation = $this->backgroundTaskRepository->findAllFileMetadataTranslationBackgroundTasks();
        foreach ($foundBackgroundTasksFileMetadataTranslation as $foundBackgroundTask) {
            if (!$this->backendUserService->canEditFileMetadata($foundBackgroundTask['fileUid'])) {
                continue;
            }

            $taskData = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status'],
            ];

            if ($structuredResult) {
                $uuidStatus['fileMetadataTranslation'][$foundBackgroundTask['table_uid']] = $taskData;
            } else {
                $uuidStatus[$foundBackgroundTask['uuid']] = $taskData;
            }
        }
    }

    /**
     * @param array<mixed> $uuidStatus
     */
    private function collectFileMetadataBackgroundTasks(array &$uuidStatus, bool $structuredResult = false): void
    {
        $foundBackgroundTasksFileMetadata = $this->backgroundTaskRepository->findAllFileMetadataBackgroundTasks();
        foreach ($foundBackgroundTasksFileMetadata as $foundBackgroundTask) {
            if (!$this->backendUserService->canEditFileMetadata($foundBackgroundTask['fileUid'])) {
                continue;
            }

            $taskData = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status'],
            ];

            if ($structuredResult) {
                $uuidStatus['fileMetadata'][$foundBackgroundTask['table_uid']] = $taskData;
            } else {
                $uuidStatus[$foundBackgroundTask['uuid']] = $taskData;
            }
        }
    }

    /**
     * @param array<string, mixed> $task
     *
     * @return array<string, mixed>
     */
    private function processFinishedMetadataTask(array $task): array
    {
        try {
            $decoded = json_decode((string) ($task['answer'] ?? ''), true);
            if (!is_array($decoded) || !isset($decoded['body']) || !is_array($decoded['body']) || [] === $decoded['body']) {
                throw new \Exception('Background task '.($task['uuid'] ?? '').' has invalid or empty answer payload.');
            }
            $result = $decoded['body'];
            $data = [
                'uuid' => $task['uuid'],
                'column' => $task['column'],
                'inputValue' => $result[array_key_first($result)][0],
            ];
            $backgroundTask = $this->backgroundTaskRepository->findByUuid($data['uuid']);
            if (empty($backgroundTask)) {
                throw new \Exception($this->localizationService->translate('aiSuite.error.backgroundTask.notFound', [$data['uuid']]));
            }
            if (empty($backgroundTask['table_name'])) {
                throw new \Exception('Background task with uuid '.$data['uuid'].' has invalid table_name');
            }

            if ('translation' === $backgroundTask['type'] && 'sys_file_metadata' === $backgroundTask['table_name']) {
                $this->handleFileMetadataTranslationSave($backgroundTask, $data);
            } elseif ('NEW' === $backgroundTask['mode']) {
                $this->handleNewMetadataRecordSave($backgroundTask, $data);
            } else {
                $this->handleExistingMetadataRecordSave($backgroundTask, $data);
            }

            $answer = $this->sendRequestService->sendDataRequest(
                'handleBackgroundTask',
                [
                    'uuids' => [$data['uuid']],
                    'mode' => 'delete',
                ]
            );
            if ('Error' === $answer->getType()) {
                $this->logger->error('Error while sending delete request to server: '.$answer->getResponseData()['message']);
            }

            $affectedRows = $this->backgroundTaskRepository->deleteByUuid($data['uuid']);
            if (0 === $affectedRows) {
                throw new \Exception($this->localizationService->translate('aiSuite.error.backgroundTask.notFound', [$data['uuid']]));
            }

            return [
                'success' => true,
                'message' => sprintf('Successfully inserted task with uuid %s and column %s', $data['uuid'], $data['column']),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Exception during processFinishedMetadataTask', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ];
        }
    }
}
