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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Controller\Trait\AjaxResponseTrait;
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use AutoDudes\AiSuite\Factory\SettingsFactory;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\BackgroundTaskService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
class BackgroundTaskController extends AbstractBackendController
{
    use AjaxResponseTrait;

    /** @var array<string, mixed> */
    protected array $extConf = [];

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly RequestsRepository $requestsRepository,
        protected readonly BackgroundTaskService $backgroundTaskService,
        protected readonly LoggerInterface $logger,
        protected readonly SettingsFactory $settingsFactory,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
        protected readonly SysFileMetadataRepository $sysFileMetadataRepository,
        protected readonly ViewFactoryService $viewFactoryService,
        protected readonly UuidService $uuidService,
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $uriBuilder,
            $pageRenderer,
            $flashMessageService,
            $requestService,
            $translationService,
            $eventDispatcher,
            $aiSuiteContext,
        );
        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
    }

    /**
     * @throws Exception
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);

        return $this->overviewAction();
    }

    public function overviewAction(): ResponseInterface
    {
        try {
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/background-task/overview.js');
            $answer = $this->requestService->sendDataRequest('getRequestsState');
            if ('RequestsState' === $answer->getType()) {
                $freeRequests = $answer->getResponseData()['free_requests'] ?? -1;
                $paidRequests = $answer->getResponseData()['paid_requests'] ?? -1;
                $aboRequests = $answer->getResponseData()['abo_requests'] ?? -1;
                $modelType = $answer->getResponseData()['model_type'] ?? '';

                try {
                    $this->requestsRepository->setRequests($freeRequests, $paidRequests, $aboRequests, $modelType, $this->extConf['aiSuiteApiKey']);
                } catch (\Exception $e) {
                    $this->requestsRepository->deleteRequests();
                    $this->view->addFlashMessage(
                        $answer->getResponseData()['message'],
                        $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.warningFetchingCreditsState.title'),
                        ContextualFeedbackSeverity::WARNING
                    );
                }
                BackendUtility::setUpdateSignal('updateTopbar');
            }
            $backgroundTasks = [
                'page' => [],
                'fileReference' => [],
                'fileMetadata' => [],
                'fileMetadataTranslation' => [],
            ];
            $uuidStatus = [];

            $this->backgroundTaskService->prefillArrays($backgroundTasks, $uuidStatus);

            if (count($backgroundTasks['page']) > 0 || count($backgroundTasks['fileReference']) > 0 || count($backgroundTasks['fileMetadata']) > 0 || count($backgroundTasks['fileMetadataTranslation']) > 0) {
                $answer = $this->requestService->sendDataRequest(
                    'massActionStatus',
                    [
                        'uuidStatus' => $uuidStatus,
                    ],
                );
                if ('Error' === $answer->getType()) {
                    $this->view->addFlashMessage($answer->getResponseData()['message'], 'Warning', ContextualFeedbackSeverity::WARNING);
                }
                $statusData = $answer->getResponseData()['statusData'] ?? null;
                if (null !== $statusData) {
                    $this->backgroundTaskService->mergeBackgroundTasksAndUpdateStatus($backgroundTasks, $statusData);
                }
            }
            $taskStatistics = $this->backgroundTaskService->getBackgroundTasksStatistics($backgroundTasks);
            $this->view->assign('taskStatistics', $taskStatistics);
            $this->view->assign('backgroundTasks', $backgroundTasks);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->view->renderResponse('BackgroundTask/Overview');
    }

    public function saveAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $data = (array) $request->getParsedBody();
            $backgroundTask = $this->backgroundTaskRepository->findByUuid($data['uuid']);
            if (empty($backgroundTask)) {
                throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.backgroundTask.notFound', [$data['uuid']]));
            }
            if (empty($backgroundTask['table_name'])) {
                throw new \Exception('Background task with uuid '.$data['uuid'].' has invalid table_name');
            }

            // Handle file metadata translation tasks
            if ('translation' === $backgroundTask['type'] && 'sys_file_metadata' === $backgroundTask['table_name']) {
                $this->handleFileMetadataTranslationSave($backgroundTask, $data);
            } elseif ('NEW' === $backgroundTask['mode']) {
                $this->handleNewMetadataRecordSave($backgroundTask, $data);
            } else {
                $this->handleExistingMetadataRecordSave($backgroundTask, $data);
            }
            $answer = $this->requestService->sendDataRequest(
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
                throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.backgroundTask.notFound', [$data['uuid']]));
            }
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => true,
                    ]
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error while saving metadata: '.$e->getMessage());
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => false,
                        'error' => $this->aiSuiteContext->localizationService->translate('aiSuite.notification.errorSavingTask'),
                    ]
                )
            );
        }

        return $response;
    }

    public function deleteAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $data = (array) $request->getParsedBody();
            $uuids = $data['uuids'] ?? [];

            if (empty($uuids)) {
                throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.backgroundTask.noUuidsProvided'));
            }
            $answer = $this->requestService->sendDataRequest(
                'handleBackgroundTask',
                [
                    'uuids' => $uuids,
                    'mode' => 'delete',
                ]
            );
            if ('Error' === $answer->getType()) {
                throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.server.aiSuiteError', [$answer->getResponseData()['message']]));
            }
            $deletedCount = $this->backgroundTaskRepository->deleteByUuids($uuids);
            BackendUtility::setUpdateSignal('updatePageTree');
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => true,
                        'count' => $deletedCount,
                    ]
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error while deleting background tasks: '.$e->getMessage());
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => false,
                        'error' => $this->aiSuiteContext->localizationService->translate('aiSuite.notification.errorDeletingAllTasks'),
                    ]
                )
            );
        }

        return $response;
    }

    public function retryAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $data = (array) $request->getParsedBody();
            $uuid = $data['uuid'] ?? '';
            $scope = $data['scope'] ?? 'metadata';

            if (empty($uuid)) {
                throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.backgroundTask.noUuidProvided'));
            }

            $backgroundTask = $this->backgroundTaskRepository->findByUuid($uuid);
            if (empty($backgroundTask) || !is_array($backgroundTask)) {
                throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.backgroundTask.notFound', [$uuid]));
            }

            if ('translation' === $backgroundTask['type']) {
                $scope = 'translation';
            }

            $answer = $this->requestService->sendDataRequest(
                'handleBackgroundTask',
                [
                    'uuid' => $uuid,
                    'mode' => 'retry',
                    'scope' => $scope,
                ]
            );
            if ('Error' === $answer->getType()) {
                throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.server.aiSuiteError', [$answer->getResponseData()['message']]));
            }
            $this->backgroundTaskRepository->updateStatus([
                $uuid => [
                    'status' => 'pending',
                    'answer' => '',
                    'error' => '',
                ],
            ]);
            BackendUtility::setUpdateSignal('updatePageTree');
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => true,
                    ]
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error while retrying background task: '.$e->getMessage());
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => false,
                        'error' => $this->aiSuiteContext->localizationService->translate('aiSuite.notification.errorRetryingTask'),
                    ]
                )
            );
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $backgroundTask
     * @param array<string, mixed> $data
     */
    private function handleFileMetadataTranslationSave(array $backgroundTask, array $data): void
    {
        if ('NEW' === $backgroundTask['mode']) {
            $sysFileMetadataRow = $this->backgroundTaskRepository->findFileUid($backgroundTask['table_uid'], $data['uuid'], 'translation', 'metadata');
            if (empty($sysFileMetadataRow)) {
                throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.backgroundTask.fileUidNotFound', [$data['uuid']]));
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
    private function handleNewMetadataRecordSave(array $backgroundTask, array $data): void
    {
        $sysFileMetadataRow = $this->backgroundTaskRepository->findFileUid($backgroundTask['table_uid'], $data['uuid'], 'metadata', 'fileMetadata');
        if (empty($sysFileMetadataRow)) {
            throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.backgroundTask.fileUidNotFound', [$data['uuid']]));
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
    private function handleExistingMetadataRecordSave(array $backgroundTask, array $data): void
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
}
