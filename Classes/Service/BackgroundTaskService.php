<?php

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;

class BackgroundTaskService
{
    protected BackendUserService $backendUserService;
    protected BackgroundTaskRepository $backgroundTaskRepository;
    protected PagesRepository $pagesRepository;
    protected SiteFinder $siteFinder;
    protected PageRepository $pageRepository;
    protected MetadataService $metadataService;

    public function __construct(
        BackendUserService $backendUserService,
        BackgroundTaskRepository $backgroundTaskRepository,
        PagesRepository $pagesRepository,
        SiteFinder $siteFinder,
        PageRepository $pageRepository,
        MetadataService $metadataService
    ) {
        $this->backendUserService = $backendUserService;
        $this->backgroundTaskRepository = $backgroundTaskRepository;
        $this->pagesRepository = $pagesRepository;
        $this->siteFinder = $siteFinder;
        $this->pageRepository = $pageRepository;
        $this->metadataService = $metadataService;
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
                'status' => $foundBackgroundTask['status']
            ];
            $counter[$foundBackgroundTask['column']]++;
        }

        $foundBackgroundTasksFiles = $this->backgroundTaskRepository->findAllFileReferenceBackgroundTasks();
        $counter = [
            'title' => 0,
            'alternative' => 0,
        ];
        foreach ($foundBackgroundTasksFiles as $foundBackgroundTask) {
            $filePermissions = false;
            if(!$this->backendUserService->getBackendUser()->isAdmin()) {
                foreach ($this->backendUserService->getBackendUser()->getFileMountRecords() as $fileMount) {
                    if($fileMount['uid'] === $foundBackgroundTask['storage']) {
                        $filePermissions = true;
                    }
                }
            } else {
                $filePermissions = true;
            }
            if($filePermissions) {
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
                    'status' => $foundBackgroundTask['status']
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
            $filePermissions = false;
            if(!$this->backendUserService->getBackendUser()->isAdmin()) {
                foreach ($this->backendUserService->getBackendUser()->getFileMountRecords() as $fileMount) {
                    if($fileMount['uid'] === $foundBackgroundTask['storage']) {
                        $filePermissions = true;
                    }
                }
            } else {
                $filePermissions = true;
            }
            if($filePermissions) {
                $foundBackgroundTask['columnValue'] = $foundBackgroundTask[$foundBackgroundTask['column']];
                if($counter[$foundBackgroundTask['column']] < 50) {
                    $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['column']][$foundBackgroundTask['uuid']] = $foundBackgroundTask;
                }
                $uuidStatus[$foundBackgroundTask['uuid']] =  [
                    'uuid' => $foundBackgroundTask['uuid'],
                    'status' => $foundBackgroundTask['status']
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
                        $answer = json_decode($fetchedStatusData[$task['uuid']]['answer'], true);
                    } else {
                        $backgroundTasks[$scope][$column][$key]['status'] = $task['status'];
                        $answer = json_decode($task['answer'], true);
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

    public function getBackgroundTasksStatistics(): ?array
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

            $result =  $this->backgroundTaskRepository->countBackgroundTasksByStatusAndScope();
            $pageMetadataColumns = $this->metadataService->getMetadataColumns();
            $fileMetadataColumns = $this->metadataService->getFileMetadataColumns();

            foreach ($result as $row) {
                $scope = $row['scope'];
                $status = $row['status'] === 'task-error' ? 'taskError' : $row['status'];
                $column = $row['column'];
                $count = (int)$row['count'];

                if (!in_array($scope, $scopes) || !in_array($status, $statuses)) {
                    continue;
                }

                $statistics['total'][$status] += $count;
                $statistics['total']['total'] += $count;

                $statistics[$scope][$status] += $count;
                $statistics[$scope]['total'] += $count;

                if (!isset($statistics[$scope]['columns'][$column])) {
                    $statistics[$scope]['columns'][$column] = [
                        'finished' => 0,
                        'pending' => 0,
                        'taskError' => 0,
                        'total' => 0,
                        'columnName' => $scope === 'page' ? $pageMetadataColumns[$column] : $fileMetadataColumns[$column],
                    ];
                }
                $statistics[$scope]['columns'][$column][$status] += $count;
                $statistics[$scope]['columns'][$column]['total'] += $count;
            }
            return $statistics;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
