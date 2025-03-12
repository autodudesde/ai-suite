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
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

#[AsController]
class BackgroundTaskController extends AbstractAjaxController
{
    protected BackgroundTaskRepository $backgroundTaskRepository;

    public function __construct(
        BackendUserService $backendUserService,
        SendRequestService $requestService,
        PromptTemplateService $promptTemplateService,
        LibraryService $libraryService,
        UuidService $uuidService,
        SiteService $siteService,
        TranslationService $translationService,
        LoggerInterface $logger,
        BackgroundTaskRepository $backgroundTaskRepository,
    ) {
        parent::__construct(
            $backendUserService,
            $requestService,
            $promptTemplateService,
            $libraryService,
            $uuidService,
            $siteService,
            $translationService,
            $logger
        );
        $this->backgroundTaskRepository = $backgroundTaskRepository;
    }

    public function saveAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        try {
            $data = $request->getParsedBody();
            $backgroundTask = $this->backgroundTaskRepository->findByUuid($data['uuid']);
            if (empty($backgroundTask)) {
                throw new \Exception('No background task with uuid ' . $data['uuid'] . ' found');
            }

            $datamap[$backgroundTask['table_name']][$backgroundTask['table_uid']][$backgroundTask['column']] = $data['inputValue'];

            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($datamap, []);
            $dataHandler->process_datamap();
            if(count($dataHandler->errorLog) > 0) {
                throw new \Exception(implode(', ', $dataHandler->errorLog));
            }
            $affectedRows = $this->backgroundTaskRepository->deleteByUuid($data['uuid']);
            if ($affectedRows === 0) {
                throw new \Exception('No background task with uuid ' . $data['uuid'] . ' found');
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
                throw new \Exception('No UUIDs provided');
            }
            $answer = $this->requestService->sendDataRequest(
                'handleBackgroundTask',
                [
                    'uuids' => $uuids,
                    'mode' => 'delete'
                ]
            );
            if ($answer->getType() === 'Error') {
                throw new \Exception('AI Suite Server error: ' . $answer->getResponseData()['message']);
            }
            $deletedCount = $this->backgroundTaskRepository->deleteByUuids($uuids);

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

            if (empty($uuid)) {
                throw new \Exception('No UUID provided');
            }

            $backgroundTask = $this->backgroundTaskRepository->findByUuid($uuid);
            if (empty($backgroundTask)) {
                throw new \Exception('No background task with UUID ' . $uuid . ' found');
            }
            $answer = $this->requestService->sendDataRequest(
                'handleBackgroundTask',
                [
                    'uuid' => $uuid,
                    'mode' => 'retry'
                ]
            );
            if ($answer->getType() === 'Error') {
                throw new \Exception('AI Suite Server error: ' . $answer->getResponseData()['message']);
            }
            $this->backgroundTaskRepository->updateStatus([
                $uuid => [
                    'status' => 'pending',
                    'answer' => '',
                    'error' => ''
                ]
            ]);

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
