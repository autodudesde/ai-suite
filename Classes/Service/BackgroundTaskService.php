<?php

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;

class BackgroundTaskService
{
    protected BackendUserService $backendUserService;
    protected BackgroundTaskRepository $backgroundTaskRepository;
    protected PagesRepository $pagesRepository;
    protected SiteFinder $siteFinder;
    protected PageRepository $pageRepository;

    public function __construct(
        BackendUserService $backendUserService,
        BackgroundTaskRepository $backgroundTaskRepository,
        PagesRepository $pagesRepository,
        SiteFinder $siteFinder,
        PageRepository $pageRepository
    ) {
        $this->backendUserService = $backendUserService;
        $this->backgroundTaskRepository = $backgroundTaskRepository;
        $this->pagesRepository = $pagesRepository;
        $this->siteFinder = $siteFinder;
        $this->pageRepository = $pageRepository;
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
                    $backgroundTasks[$scope][$column][$key]['status'] = $task['status'];
                    $answer = json_decode($task['answer'], true);
                    if (isset($answer['type'])) {
                        if ($answer['type'] === 'Metadata' && isset($answer['body']['metadataResult'])) {
                            $backgroundTasks[$scope][$column][$key]['metadataSuggestions'] = $answer['body']['metadataResult'];
                        } elseif ($answer['type'] === 'Error' && isset($answer['body']['message'])) {
                            $backgroundTasks[$scope][$column][$key]['error'] = $answer['body']['message'];
                        }
                    }
                    unset($backgroundTasks[$scope][$column][$key]['answer']);
                }
            }
        }
    }
}
