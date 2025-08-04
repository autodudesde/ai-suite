<?php

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use Doctrine\DBAL\DBALException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackgroundTaskService
{
    protected BackendUserService $backendUserService;
    protected BackgroundTaskRepository $backgroundTaskRepository;
    protected PagesRepository $pagesRepository;
    protected SiteFinder $siteFinder;
    protected PageRepository $pageRepository;
    protected MetadataService $metadataService;
    protected LoggerInterface $logger;
    protected SendRequestService $sendRequestService;
    protected TranslationService $translationService;
    protected IconFactory $iconFactory;

    protected array $extConf = [];

    public function __construct(
        BackendUserService $backendUserService,
        BackgroundTaskRepository $backgroundTaskRepository,
        PagesRepository $pagesRepository,
        SiteFinder $siteFinder,
        PageRepository $pageRepository,
        MetadataService $metadataService,
        LoggerInterface $logger,
        SendRequestService $sendRequestService,
        TranslationService $translationService,
        IconFactory $iconFactory
    ) {
        $this->backendUserService = $backendUserService;
        $this->backgroundTaskRepository = $backgroundTaskRepository;
        $this->pagesRepository = $pagesRepository;
        $this->siteFinder = $siteFinder;
        $this->pageRepository = $pageRepository;
        $this->metadataService = $metadataService;
        $this->logger = $logger;
        $this->sendRequestService = $sendRequestService;
        $this->translationService = $translationService;
        $this->iconFactory = $iconFactory;
    }

    public function prefillArrays(array &$backgroundTasks, array &$uuidStatus): void
    {
        $foundBackgroundTasksPages = $this->backgroundTaskRepository->findAllPageBackgroundTasks();
        $counter = [
            'seo_title' => 0,
            'description' => 0,
            'og_title' => 0,
            'og_description' => 0,
            'twitter_title' => 0,
            'twitter_description' => 0
        ];
        foreach ($foundBackgroundTasksPages as $foundBackgroundTask) {
            if(!$this->backendUserService->getBackendUser()->isInWebMount($foundBackgroundTask['table_uid'])) {
                continue;
            }
            try {
                if ($foundBackgroundTask['sys_language_uid'] === -1) {
                    $foundBackgroundTask['flag'] = 'flags-multiple';
                } else {
                    $page = $this->pageRepository->getPage($foundBackgroundTask['table_uid']);
                    $pageId = $foundBackgroundTask['table_uid'];
                    if ($page['is_siteroot'] === 1 && $page['l10n_parent'] > 0) {
                        $pageId = $page['l10n_parent'];
                    }
                    $site = $this->siteFinder->getSiteByPageId($pageId);
                    $foundBackgroundTask['flag'] = $site->getLanguageById($foundBackgroundTask['sys_language_uid'])->getFlagIdentifier() ?? '';
                }
            } catch (\Exception $e) {
                $foundBackgroundTask['flag'] = '';
            }
            if($counter[$foundBackgroundTask['column']] < 50) {
                $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['column']][$foundBackgroundTask['table_uid']] = $foundBackgroundTask;
            }
            $uuidStatus[$foundBackgroundTask['uuid']] =  [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status'] ?? 'pending'
            ];
            $counter[$foundBackgroundTask['column']]++;
        }

        $foundBackgroundTasksFiles = $this->backgroundTaskRepository->findAllFileReferenceBackgroundTasks();
        $counter = [
            'title' => 0,
            'alternative' => 0,
        ];
        foreach ($foundBackgroundTasksFiles as $foundBackgroundTask) {
            if ($this->metadataService->hasFilePermissions($foundBackgroundTask['fileUid'])) {
                $foundBackgroundTask['columnValue'] = $foundBackgroundTask[$foundBackgroundTask['column']];
                try {
                    if ($foundBackgroundTask['sys_language_uid'] === -1) {
                        $foundBackgroundTask['flag'] = 'flags-multiple';
                    } else {
                        $page = $this->pageRepository->getPage($foundBackgroundTask['pageId']);
                        $pageId = $foundBackgroundTask['pageId'];
                        if ($page['is_siteroot'] === 1 && $page['l10n_parent'] > 0) {
                            $pageId = $page['l10n_parent'];
                        }
                        $site = $this->siteFinder->getSiteByPageId($pageId);
                        $foundBackgroundTask['flag'] = $site->getLanguageById($foundBackgroundTask['sys_language_uid'])->getFlagIdentifier() ?? '';
                    }
                } catch (\Throwable $e) {
                    $foundBackgroundTask['flag'] = '';
                }
                if ($counter[$foundBackgroundTask['column']] < 50) {
                    $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['column']][$foundBackgroundTask['table_uid']] = $foundBackgroundTask;
                }
                $uuidStatus[$foundBackgroundTask['uuid']] =  [
                    'uuid' => $foundBackgroundTask['uuid'],
                    'status' => $foundBackgroundTask['status'] ?? 'pending'
                ];
                $counter[$foundBackgroundTask['column']]++;
            }
        }

        $foundBackgroundTasksFileMetadata = $this->backgroundTaskRepository->findAllFileMetadataBackgroundTasks();
        $counter = [
            'title' => 0,
            'alternative' => 0,
        ];
        foreach ($foundBackgroundTasksFileMetadata as $foundBackgroundTask) {
            if ($this->metadataService->hasFilePermissions($foundBackgroundTask['fileUid'])) {
                $foundBackgroundTask['columnValue'] = $foundBackgroundTask[$foundBackgroundTask['column']];
                if($counter[$foundBackgroundTask['column']] < 50) {
                    $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['column']][$foundBackgroundTask['table_uid']] = $foundBackgroundTask;
                }
                $uuidStatus[$foundBackgroundTask['uuid']] =  [
                    'uuid' => $foundBackgroundTask['uuid'],
                    'status' => $foundBackgroundTask['status'] ?? 'pending'
                ];
                $counter[$foundBackgroundTask['column']]++;
            }
        }
    }

    /**
     * @throws DBALException
     */
    public function mergeBackgroundTasksAndUpdateStatus(array &$backgroundTasks, array $fetchedStatusData): void
    {
        if(count($fetchedStatusData) > 0) {
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
                        if ($answer['type'] === 'Metadata' && isset($answer['body']['metadataResult'])) {
                            $result = array_filter($answer['body']['metadataResult'], 'is_string');
                            $backgroundTasks[$scope][$column][$key]['metadataSuggestions'] = $result;
                        } elseif ($answer['type'] === 'Error' && isset($answer['body']['message'])) {
                            $backgroundTasks[$scope][$column][$key]['error'] = $answer['body']['message'];
                        }
                    }
                    unset($backgroundTasks[$scope][$column][$key]['answer']);
                }
            }
        }
    }

    public function getBackgroundTasksStatistics(array $backgroundTasks): ?array
    {
        try {
            $statuses = ['finished', 'pending', 'taskError'];
            $scopes = ['page', 'fileReference', 'fileMetadata'];
            $statistics = [
                'total' => [
                    'finished' => 0,
                    'pending' => 0,
                    'taskError' => 0,
                    'total' => 0
                ]
            ];

            foreach ($scopes as $scope) {
                $statistics[$scope] = [
                    'finished' => 0,
                    'pending' => 0,
                    'taskError' => 0,
                    'total' => 0,
                    'columns' => []
                ];
            }

            $pageMetadataColumns = $this->metadataService->getMetadataColumns();
            $fileMetadataColumns = $this->metadataService->getFileMetadataColumns();

            foreach ($backgroundTasks as $scope => $tasksByColumn) {
                foreach ($tasksByColumn as $column => $tasks) {
                    foreach ($tasks as $task) {
                        $status = $task['status'] === 'task-error' ? 'taskError' : $task['status'];

                        if (!in_array($scope, $scopes) || !in_array($status, $statuses)) {
                            continue;
                        }

                        $statistics['total'][$status]++;
                        $statistics['total']['total']++;

                        $statistics[$scope][$status]++;
                        $statistics[$scope]['total']++;

                        if (!isset($statistics[$scope]['columns'][$column])) {
                            $columnName = match ($scope) {
                                'page' => $pageMetadataColumns[$column] ?? $column,
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
                        $statistics[$scope]['columns'][$column][$status]++;
                        $statistics[$scope]['columns'][$column]['total']++;
                    }
                }
            }
            return $statistics;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function collectUuidsForStatusUpdate(): array
    {
        $uuidStatus = [];

        $this->collectPageBackgroundTasks($uuidStatus);
        $this->collectFileReferenceBackgroundTasks($uuidStatus);
        $this->collectPageTranslationBackgroundTasks($uuidStatus);

        return $uuidStatus;
    }

    public function fetchStructuredBackgroundTaskStatus(): array
    {
        $uuidStatus = [];

        $this->collectPageBackgroundTasks($uuidStatus, true);
        $this->collectFileReferenceBackgroundTasks($uuidStatus, true);
        $this->collectPageTranslationBackgroundTasks($uuidStatus, true);

        return $uuidStatus;
    }

    public function fetchBackgroundTaskStatus(bool $update = false): array
    {
        if($update) {
            $uuidStatus = $this->collectUuidsForStatusUpdate();
            if (count($uuidStatus) > 0) {
                $sendRequestService = GeneralUtility::makeInstance(
                    SendRequestService::class
                );

                $answer = $sendRequestService->sendDataRequest('massActionStatus', [
                    'uuidStatus' => $uuidStatus
                ]);

                if ($answer->getType() !== 'Error') {
                    $statusData = $answer->getResponseData()['statusData'] ?? [];
                    $this->backgroundTaskRepository->updateStatus($statusData);
                }
            }
        }
        return $this->fetchStructuredBackgroundTaskStatus();
    }

    private function collectPageBackgroundTasks(array &$uuidStatus, bool $structuredResult = false): void
    {
        $foundBackgroundTasksPages = $this->backgroundTaskRepository->findAllPageBackgroundTasks();
        foreach ($foundBackgroundTasksPages as $foundBackgroundTask) {
            if (!$this->backendUserService->getBackendUser()->isInWebMount($foundBackgroundTask['table_uid'])) {
                continue;
            }

            $taskData = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status']
            ];

            if ($structuredResult) {
                $uuidStatus['pages'][$foundBackgroundTask['table_uid']] = $taskData;
            } else {
                $uuidStatus[$foundBackgroundTask['uuid']] = $taskData;
            }
        }
    }

    private function collectFileReferenceBackgroundTasks(array &$uuidStatus, bool $structuredResult = false): void
    {
        $foundBackgroundTasksFiles = $this->backgroundTaskRepository->findAllFileReferenceBackgroundTasks();
        foreach ($foundBackgroundTasksFiles as $foundBackgroundTask) {
            if (!$this->metadataService->hasFilePermissions($foundBackgroundTask['fileUid'])) {
                continue;
            }

            $taskData = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status']
            ];

            if ($structuredResult) {
                $uuidStatus['fileReferences'][$foundBackgroundTask['table_uid']] = $taskData;
            } else {
                $uuidStatus[$foundBackgroundTask['uuid']] = $taskData;
            }
        }
    }

    private function collectPageTranslationBackgroundTasks(array &$uuidStatus, bool $structuredResult = false): void
    {
        $foundBackgroundTasksPageTranslation = $this->backgroundTaskRepository->findAllPageTranslationBackgroundTasks();
        foreach ($foundBackgroundTasksPageTranslation as $foundBackgroundTask) {
            if (!$this->backendUserService->getBackendUser()->isInWebMount($foundBackgroundTask['table_uid'])) {
                continue;
            }

            $taskData = [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status']
            ];

            if ($structuredResult) {
                $uuidStatus['translation'][$foundBackgroundTask['table_uid']] = $taskData;
            } else {
                $uuidStatus[$foundBackgroundTask['uuid']] = $taskData;
            }
        }
    }

    public function retryBackgroundTask(string $uuid): bool
    {
        try {
            if (empty($uuid)) {
                throw new \Exception($this->translationService->translate('tx_aisuite.error.backgroundTask.noUuidProvided'));
            }

            $backgroundTask = $this->backgroundTaskRepository->findByUuid($uuid);
            if (empty($backgroundTask)) {
                throw new \Exception($this->translationService->translate('tx_aisuite.error.backgroundTask.notFound', [$uuid]));
            }

            $answer = $this->sendRequestService->sendDataRequest(
                'handleBackgroundTask',
                [
                    'uuid' => $uuid,
                    'mode' => 'retry'
                ]
            );

            if ($answer->getType() === 'Error') {
                throw new \Exception($this->translationService->translate('tx_aisuite.error.server.aiSuiteError', [$answer->getResponseData()['message']]));
            }

            $this->backgroundTaskRepository->updateStatus([
                $uuid => [
                    'status' => 'pending',
                    'answer' => '',
                    'error' => ''
                ]
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Error while retrying background task: ' . $e->getMessage());
            return false;
        }
    }

    public function generateTranslationPageButtons(ServerRequestInterface $request): string
    {
        if (!$this->backendUserService->getBackendUser()->check('custom_options', 'tx_aisuite_features:enable_translations')) {
            return '';
        }

        $pageId = $this->translationService->getPageIdFromRequest($request);
        if ($pageId === 0) {
            return '';
        }

        if (!$this->backendUserService->getBackendUser()->isInWebMount($pageId)) {
            return '';
        }

        try {
            $actionButtonsAbove = '<div class="form-row"><div class="form-group d-flex gap-2">';

            $iconLocalize = $this->iconFactory->getIcon('actions-localize', 'small')->render();
            $translateWholePageButton = '<a href="#" id="aiSuiteTranslateWholePage" class="btn btn-default" data-page-id="' . $pageId . '">'
                . $iconLocalize
                . ' ' . $this->translationService->translate('tx_aisuite.action.translateWholePage')
                . '</a>';

            $actionButtonsAbove .= $translateWholePageButton;

            $backgroundTasks = $this->fetchBackgroundTaskStatus(true);
            $statusUuid = $this->translationService->addTranslationNotifications($backgroundTasks, $pageId);
            if (!empty($statusUuid)) {
                $parts = explode('__', $statusUuid);
                $status = $parts[0] ?? '';
                $uuid = $parts[1] ?? '';
                if (!empty($uuid)) {
                    if($status === 'error') {
                        $iconSynchronize = $this->iconFactory->getIcon('actions-document-synchronize', 'small')->render();
                        $actionButtonsAbove .= '<a href="#" id="aiSuiteTranslationTaskRetry" class="btn btn-default" data-uuid="' . $uuid . '">'
                            . $iconSynchronize
                            . ' ' . $this->translationService->translate('tx_aisuite.action.retryTranslationTask')
                            . '</a>';
                    }
                    $iconSynchronize = $this->iconFactory->getIcon('actions-delete', 'small')->render();
                    $actionButtonsAbove .= '<a href="#" id="aiSuiteTranslationTaskRemove" class="btn btn-default" data-uuid="' . $uuid . '">'
                        . $iconSynchronize
                        . ' ' . $this->translationService->translate('tx_aisuite.action.removeTranslationTask')
                        . '</a>';
                }
            }
            $actionButtonsAbove .= '</div></div>';

            return $actionButtonsAbove;
        } catch (\Exception $e) {
            $this->logger->error('Error generating translation page buttons', [
                'pageId' => $pageId,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }
}
