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
            $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['table_uid']] = $foundBackgroundTask;
            $uuidStatus[$foundBackgroundTask['uuid']] =  [
                'uuid' => $foundBackgroundTask['uuid'],
                'status' => $foundBackgroundTask['status']
            ];
        }

        $foundBackgroundTasksFiles = $this->backgroundTaskRepository->findAllFileReferenceBackgroundTasks();

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
                } catch (\Exception $e) {
                    $foundBackgroundTask['flag'] = '';
                }
                $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['table_uid']] = $foundBackgroundTask;
                $uuidStatus[$foundBackgroundTask['uuid']] =  [
                    'uuid' => $foundBackgroundTask['uuid'],
                    'status' => $foundBackgroundTask['status']
                ];
            }
        }

        $foundBackgroundTasksFileMetadata = $this->backgroundTaskRepository->findAllFileMetadataBackgroundTasks();

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
                $backgroundTasks[$foundBackgroundTask['scope']][$foundBackgroundTask['uuid']] = $foundBackgroundTask;
                $uuidStatus[$foundBackgroundTask['uuid']] =  [
                    'uuid' => $foundBackgroundTask['uuid'],
                    'status' => $foundBackgroundTask['status']
                ];
            }
        }
    }

    /**
     * @throws DBALException
     */
    public function mergeBackgroundTasksAndUpdateStatus(array &$backgroundTasks, array $fetchedStatusData): void
    {
        foreach($backgroundTasks as $scope => $tasks) {
            $backgroundTasks[$scope] = array_map(function ($task) use ($fetchedStatusData) {
                $task['status'] = isset($fetchedStatusData[$task['uuid']]) > 0 ? $fetchedStatusData[$task['uuid']]['status'] : $task['status'];
                $answer = isset($fetchedStatusData[$task['uuid']]) ? json_decode($fetchedStatusData[$task['uuid']]['answer'], true) : json_decode($task['answer'], true);
                if(isset($answer['type']) && $answer['type'] === 'Metadata') {
                    $task['metadataSuggestions'] = $answer['body']['metadataResult'];
                }
                if(isset($answer['type']) && $answer['type'] === 'Error') {
                    $task['error'] = $answer['body']['message'];
                }
                unset($task['answer']);
                return $task;
            }, $backgroundTasks[$scope]);
        }
        $this->backgroundTaskRepository->updateStatus($fetchedStatusData);
    }
}
