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
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\SessionService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class AiSuiteController extends AbstractBackendController
{
    protected array $extConf;
    protected SettingsFactory $settingsFactory;
    protected RequestsRepository $requestsRepository;
    protected LoggerInterface $logger;

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
        EventDispatcher $eventDispatcher,
        RequestsRepository $requestsRepository,
        SettingsFactory $settingsFactory,
        LoggerInterface $logger
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
            $eventDispatcher,
        );
        $this->settingsFactory = $settingsFactory;
        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
        $this->requestsRepository = $requestsRepository;
        $this->logger = $logger;
    }

    /**
     * @throws RouteNotFoundException
     * @throws PropagateResponseException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $this->sessionService->handleRedirectBySessionRoute();
        return $this->dashboardAction();
    }

    public function dashboardAction(): ResponseInterface
    {
        try {
            if (empty($this->extConf['aiSuiteApiKey'])) {
                $this->requestsRepository->deleteRequests();
                $this->view->addFlashMessage(
                    $this->translationService->translate('aiSuite.module.missingAiSuiteApiKey.message'),
                    $this->translationService->translate('aiSuite.module.missingAiSuiteApiKey.title'),
                    ContextualFeedbackSeverity::NOTICE
                );
            }
            $answer = $this->requestService->sendDataRequest('getRequestsState');
            $freeRequests = -1;
            $paidRequests = -1;
            $aboRequests = -1;
            $modelType = '-';
            if ($answer->getType() === 'RequestsState') {
                $freeRequests = $answer->getResponseData()['free_requests'] ?? -1;
                $paidRequests = $answer->getResponseData()['paid_requests'] ?? -1;
                $aboRequests = $answer->getResponseData()['abo_requests'] ?? -1;
                $modelType = $answer->getResponseData()['model_type'] ?? '';
                $this->requestsRepository->setRequests($freeRequests, $paidRequests, $aboRequests, $modelType, $this->extConf['aiSuiteApiKey']);
            } else {
                $this->requestsRepository->deleteRequests($this->extConf['aiSuiteApiKey']);
                $this->view->addFlashMessage(
                    $answer->getResponseData()['message'],
                    $this->translationService->translate('aiSuite.module.warningFetchingCreditsState.title'),
                    ContextualFeedbackSeverity::WARNING
                );
            }
            $this->view->assignMultiple([
                'freeRequests' => $freeRequests,
                'paidRequests' => $paidRequests,
                'aboRequests' => (int)$modelType - $aboRequests . ' / ' . $modelType,
                'modelType' => $modelType
            ]);
            BackendUtility::setUpdateSignal('updateTopbar');
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->translationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
        return $this->view->renderResponse('AiSuite/Dashboard');
    }
}
