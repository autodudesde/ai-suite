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
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Factory\SettingsFactory;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class AiSuiteController extends AbstractBackendController
{
    use AjaxResponseTrait;

    /** @var array<string, mixed> */
    protected array $extConf;

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
        protected readonly SettingsFactory $settingsFactory,
        protected readonly LoggerInterface $logger,
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
     * @throws RouteNotFoundException
     * @throws PropagateResponseException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $this->aiSuiteContext->sessionService->handleRedirectBySessionRoute();

        return $this->dashboardAction();
    }

    public function dashboardAction(): ResponseInterface
    {
        try {
            if (empty($this->extConf['aiSuiteApiKey'])) {
                $this->requestsRepository->deleteRequests();
                $this->view->addFlashMessage(
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.missingAiSuiteApiKey.message'),
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.missingAiSuiteApiKey.title'),
                    ContextualFeedbackSeverity::NOTICE
                );
            }
            $answer = $this->requestService->sendDataRequest('getRequestsState');
            $freeRequests = -1;
            $paidRequests = -1;
            $aboRequests = -1;
            $modelType = '-';
            if ('RequestsState' === $answer->getType()) {
                $freeRequests = $answer->getResponseData()['free_requests'] ?? -1;
                $paidRequests = $answer->getResponseData()['paid_requests'] ?? -1;
                $aboRequests = $answer->getResponseData()['abo_requests'] ?? -1;
                $modelType = $answer->getResponseData()['model_type'] ?? '';
                $this->requestsRepository->setRequests($freeRequests, $paidRequests, $aboRequests, $modelType, $this->extConf['aiSuiteApiKey']);
            } else {
                $this->requestsRepository->deleteRequests($this->extConf['aiSuiteApiKey']);
                $this->view->addFlashMessage(
                    $answer->getResponseData()['message'],
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.warningFetchingCreditsState.title'),
                    ContextualFeedbackSeverity::WARNING
                );
            }
            $this->view->assignMultiple([
                'freeRequests' => $freeRequests,
                'paidRequests' => $paidRequests,
                'aboRequests' => (int) $modelType - $aboRequests.' / '.$modelType,
                'modelType' => $modelType,
            ]);
            BackendUtility::setUpdateSignal('updateTopbar');
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->view->renderResponse('AiSuite/Dashboard');
    }

    public function getStatusAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        $backendUser = $this->aiSuiteContext->backendUserService->getBackendUser();
        $lang = $backendUser?->user['lang'] ?? 'default';
        if ('default' === $lang || '' === $lang) {
            $langIsoCode = 'en';
        } else {
            $langIsoCode = $lang;
        }

        $answer = $this->requestService->sendDataRequest(
            'requestStatusUpdate',
            [
                'uuid' => ((array) $request->getParsedBody())['uuid'],
            ],
            '',
            $langIsoCode,
        );
        if ('Error' === $answer->getType()) {
            $this->logError($answer->getResponseData()['message'], $response, 503);

            return $response;
        }
        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => true,
                    'output' => $answer->getResponseData()['status'],
                ]
            )
        );

        return $response;
    }
}
