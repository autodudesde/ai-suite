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

namespace AutoDudes\AiSuite\Controller\Ajax;

use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class BackgroundTaskController extends AbstractAjaxController
{
    protected BackgroundTaskRepository $backgroundTaskRepository;

    protected SysFileMetadataRepository $sysFileMetadataRepository;

    public function __construct(
        BackendUserService $backendUserService,
        SendRequestService $requestService,
        PromptTemplateService $promptTemplateService,
        LibraryService $libraryService,
        UuidService $uuidService,
        SiteService $siteService,
        TranslationService $translationService,
        ViewFactoryInterface $viewFactory,
        LoggerInterface $logger,
        EventDispatcher $eventDispatcher,
        BackgroundTaskRepository $backgroundTaskRepository,
        SysFileMetadataRepository $sysFileMetadataRepository,
    ) {
        parent::__construct(
            $backendUserService,
            $requestService,
            $promptTemplateService,
            $libraryService,
            $uuidService,
            $siteService,
            $translationService,
            $viewFactory,
            $logger,
            $eventDispatcher
        );
        $this->backgroundTaskRepository = $backgroundTaskRepository;
        $this->sysFileMetadataRepository = $sysFileMetadataRepository;
    }

    public function saveAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        try {
            $data = $request->getParsedBody();
            $backgroundTask = $this->backgroundTaskRepository->findByUuid($data['uuid']);
            if (empty($backgroundTask)) {
                throw new \Exception($this->translationService->translate('tx_aisuite.error.backgroundTask.notFound', [$data['uuid']]));
            }
            if (empty($backgroundTask['table_name'])) {
                throw new \Exception('Background task with uuid ' . $data['uuid'] . ' has invalid table_name');
            }

            if ($backgroundTask['mode'] === 'NEW') {
                $sysFileMetadataRow = $this->backgroundTaskRepository->findFileUid($backgroundTask['table_uid'], $data['uuid']);
                if (empty($sysFileMetadataRow)) {
                    throw new \Exception($this->translationService->translate('tx_aisuite.error.backgroundTask.fileUidNotFound', [$data['uuid']]));
                }
                $fileUid = $sysFileMetadataRow['fileUid'];
                $existingMetadataTranslation = $this->sysFileMetadataRepository->findTranslatedMetadataUid($backgroundTask['table_uid'], $fileUid, $backgroundTask['sys_language_uid']);
                if (empty($existingMetadataTranslation)) {
                    $cmdmap = [
                        $backgroundTask['table_name'] =>
                            [
                                $backgroundTask['table_uid'] =>
                                    [
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

                $datamap = array(
                    $backgroundTask['table_name'] =>
                        array(
                            $translatedMetadataUid =>
                                array(
                                    $data['column'] => $data['inputValue'],
                                ),
                        ),
                );
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start($datamap, []);
                $dataHandler->process_datamap();
                if (count($dataHandler->errorLog) > 0) {
                    throw new \Exception(implode(', ', $dataHandler->errorLog));
                }
            } else {
                $datamap[$backgroundTask['table_name']][$backgroundTask['table_uid']][$backgroundTask['column']] = $data['inputValue'];
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start($datamap, []);
                $dataHandler->process_datamap();
                if (count($dataHandler->errorLog) > 0) {
                    throw new \Exception(implode(', ', $dataHandler->errorLog));
                }
            }

            $affectedRows = $this->backgroundTaskRepository->deleteByUuid($data['uuid']);
            if ($affectedRows === 0) {
                throw new \Exception($this->translationService->translate('tx_aisuite.error.backgroundTask.notFound', [$data['uuid']]));
            }
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true
                    ]
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error while saving metadata: ' . $e->getMessage());
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->translationService->translate('AiSuite.notification.errorSavingTask')
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
            $data = $request->getParsedBody();
            $uuids = $data['uuids'] ?? [];

            if (empty($uuids)) {
                throw new \Exception($this->translationService->translate('tx_aisuite.error.backgroundTask.noUuidsProvided'));
            }
            $answer = $this->requestService->sendDataRequest(
                'handleBackgroundTask',
                [
                    'uuids' => $uuids,
                    'mode' => 'delete'
                ]
            );
            if ($answer->getType() === 'Error') {
                throw new \Exception($this->translationService->translate('tx_aisuite.error.server.aiSuiteError', [$answer->getResponseData()['message']]));
            }
            $deletedCount = $this->backgroundTaskRepository->deleteByUuids($uuids);
            BackendUtility::setUpdateSignal('updatePageTree');
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                        'count' => $deletedCount
                    ]
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error while deleting background tasks: ' . $e->getMessage());
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->translationService->translate('AiSuite.notification.errorDeletingAllTasks')
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
            $data = $request->getParsedBody();
            $uuid = $data['uuid'] ?? '';
            $scope = $data['scope'] ?? 'metadata';

            if (empty($uuid)) {
                throw new \Exception($this->translationService->translate('tx_aisuite.error.backgroundTask.noUuidProvided'));
            }

            $backgroundTask = $this->backgroundTaskRepository->findByUuid($uuid);
            if (empty($backgroundTask)) {
                throw new \Exception($this->translationService->translate('tx_aisuite.error.backgroundTask.notFound', [$uuid]));
            }
            $answer = $this->requestService->sendDataRequest(
                'handleBackgroundTask',
                [
                    'uuid' => $uuid,
                    'mode' => 'retry',
                    'scope' => $scope
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
            BackendUtility::setUpdateSignal('updatePageTree');
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true
                    ]
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error while retrying background task: ' . $e->getMessage());
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->translationService->translate('AiSuite.notification.errorRetryingTask')
                    ]
                )
            );
        }
        return $response;
    }
}
