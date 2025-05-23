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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Factory\SettingsFactory;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\BackgroundTaskService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SessionService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class BackgroundTaskController extends AbstractBackendController
{
    protected RequestsRepository $requestsRepository;
    protected BackgroundTaskService $backgroundTaskService;

    protected LoggerInterface $logger;

    protected SettingsFactory $settingsFactory;

    protected array $extConf = [];

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        BackendUserService $backendUserService,
        LibraryService $libraryService,
        PromptTemplateService $promptTemplateService,
        SiteService $siteService,
        TranslationService $translationService,
        SessionService $sessionService,
        RequestsRepository $requestsRepository,
        BackgroundTaskService $backgroundTaskService,
        LoggerInterface $logger,
        SettingsFactory $settingsFactory
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $iconFactory,
            $uriBuilder,
            $pageRenderer,
            $flashMessageService,
            $requestService,
            $backendUserService,
            $libraryService,
            $promptTemplateService,
            $siteService,
            $translationService,
            $sessionService,
        );
        $this->requestsRepository = $requestsRepository;
        $this->backgroundTaskService = $backgroundTaskService;
        $this->logger = $logger;
        $this->settingsFactory = $settingsFactory;
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
            if ($answer->getType() === 'RequestsState') {
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
                        $this->translationService->translate('aiSuite.module.warningFetchingCreditsState.title'),
                        ContextualFeedbackSeverity::WARNING
                    );
                }
                BackendUtility::setUpdateSignal('updateTopbar');
            }
            $backgroundTasks = [
                'page' => [],
                'fileReference' => [],
                'fileMetadata' => [],
            ];
            $uuidStatus = [];

            $this->backgroundTaskService->prefillArrays($backgroundTasks, $uuidStatus);
            if(count($backgroundTasks['page']) > 0 || count($backgroundTasks['fileReference']) > 0 || count($backgroundTasks['fileMetadata']) > 0) {
                $answer = $this->requestService->sendDataRequest(
                    'massActionStatus',
                    [
                        'uuidStatus' => $uuidStatus
                    ],
                );
                if ($answer->getType() === 'Error') {
                    $this->view->addFlashMessage($answer->getResponseData()['message'], 'Warning', ContextualFeedbackSeverity::WARNING);
                }
                $this->backgroundTaskService->mergeBackgroundTasksAndUpdateStatus($backgroundTasks, $answer->getResponseData()['statusData']);
            }
            $taskStatistics = $this->backgroundTaskService->getBackgroundTasksStatistics();
            $this->view->assign('taskStatistics', $taskStatistics);
            $this->view->assign('backgroundTasks', $backgroundTasks);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->translationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
        return $this->view->renderResponse('BackgroundTask/Overview');
    }
}
