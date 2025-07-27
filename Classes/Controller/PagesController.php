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

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Factory\PageStructureFactory;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SessionService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class PagesController extends AbstractBackendController
{
    protected PageStructureFactory $pageStructureFactory;
    protected PagesRepository $pagesRepository;
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
        PageStructureFactory $pageStructureFactory,
        PagesRepository $pagesRepository,
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
            $eventDispatcher
        );
        $this->pageStructureFactory = $pageStructureFactory;
        $this->pagesRepository = $pagesRepository;
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $identifier = $request->getAttribute('route')->getOption('_identifier');
        switch ($identifier) {
            case 'ai_suite_page_create_pagetree':
                return $this->pageStructureAction();
            case 'ai_suite_page_validate_pagetree':
                return $this->validatePageStructureResultAction();
            case 'ai_suite_page_validate_pagetree_create':
                return $this->createValidatedPageStructureAction();
            default:
                return $this->overviewAction();
        }
    }
    public function overviewAction(): ResponseInterface
    {
        return $this->view->renderResponse('Pages/Overview');
    }

    public function pageStructureAction(): ResponseInterface
    {
        try {
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/pages/creation.js');
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::PAGETREE, 'pageTree', ['text']);
            $this->view->assignMultiple([
                'pagesSelect' => $this->getPagesInWebMount(),
                'textGenerationLibraries' => $this->libraryService->prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries']),
                'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
                'promptTemplates' => $this->promptTemplateService->getAllPromptTemplates('pageTree'),
                'sysLanguages' => $this->siteService->getAvailableLanguages(),
            ]);
        } catch (\Throwable $e) {
            $this->view->assign('error', true);
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->translationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
        return $this->view->renderResponse('Pages/PageStructure');
    }

    public function validatePageStructureResultAction(): ResponseInterface
    {
        try {
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/pages/validation.js');
            $parsedBody = $this->request->getParsedBody();
            $textAi = isset($parsedBody['libraries']['textGenerationLibrary']) ? $parsedBody['libraries']['textGenerationLibrary'] : '';
            if((int)$parsedBody['startStructureFromPid'] === -1) {
                $langIsoCode = $parsedBody['sysLanguage'];
            } else {
                $langIsoCode = $this->siteService->getIsoCodeByLanguageId(0, (int)$parsedBody['startStructureFromPid']);
            }
            $answer = $this->requestService->sendDataRequest(
                'pageTree',
                [],
                $this->request->getParsedBody()['plainPrompt'] ?? '',
                $langIsoCode,
                [
                    'text' => $textAi,
                ],
            );
            if ($answer->getType() === 'Error') {
                $this->view->addFlashMessage(
                    $answer->getResponseData()['message'],
                    $this->translationService->translate('aiSuite.module.errorFetchingPagetreeResponse.title'),
                    ContextualFeedbackSeverity::ERROR
                );
                return $this->pageStructureAction();
            }
            $this->view->assignMultiple([
                'aiResult' => $answer->getResponseData()['pagetreeResult'],
                'prompt' => $this->request->getParsedBody()['plainPrompt'] ?? '',
                'promptTemplates' => $this->promptTemplateService->getAllPromptTemplates('pageTree'),
                'selectedPid' => $this->request->getParsedBody()['startStructureFromPid'] ?? 0,
                'pagesSelect' => $this->getPagesInWebMount(),
                'textGenerationLibraries' => $this->libraryService->prepareLibraries(json_decode($this->request->getParsedBody()['textGenerationLibraries'], true), $textAi),
                'sysLanguages' => $this->siteService->getAvailableLanguages(),
                'selectedSysLanguage' => $langIsoCode,
            ]);
            $this->view->addFlashMessage(
                $this->translationService->translate('aiSuite.module.fetchingDataSuccessful.message'),
                $this->translationService->translate('aiSuite.module.fetchingDataSuccessful.title'),
            );
        } catch (\Throwable $e) {
            $this->view->assign('error', true);
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->translationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
        return $this->view->renderResponse('Pages/ValidatePageStructureResult');
    }

    public function createValidatedPageStructureAction(): ResponseInterface
    {
        try {
            $selectedPageTreeContent = $this->request->getParsedBody()['selectedPageTreeContent'] ?? '';
            $startStructureFromPid = $this->request->getParsedBody()['startStructureFromPid'] ?? 0;
            $this->pageStructureFactory->createFromArray(json_decode($selectedPageTreeContent, true), $startStructureFromPid);
            BackendUtility::setUpdateSignal('updatePageTree');
            $this->view->addFlashMessage(
                $this->translationService->translate('aiSuite.module.pagetreeGenerationSuccessful.title'),
                $this->translationService->translate('aiSuite.module.pagetreeGenerationSuccessful.title'),
            );
        } catch (\Throwable $e) {
            $this->view->assign('error', true);
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->translationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
        return $this->overviewAction();
    }

    private function getPagesInWebMount(): array
    {
        $pagesSelect = [];
        if ($this->backendUserService->getBackendUser()->isAdmin()) {
            $pagesSelect = [
                -1 => $this->translationService->translate('aiSuite.module.pages.newRootPage')
            ];
        }
        return $pagesSelect + $this->backendUserService->fetchAccessablePages();
    }
}
