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

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\MassActionService;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
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
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class MassActionController extends AbstractBackendController
{
    protected MetadataService $metadataService;

    protected MassActionService $massActionService;

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
        MetadataService $metadataService,
        MassActionService $massActionService,
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
        $this->metadataService = $metadataService;
        $this->massActionService = $massActionService;
        $this->logger = $logger;
    }

    /**
     * @throws RouteNotFoundException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $identifier = $request->getAttribute('route')->getOption('_identifier');
        switch ($identifier) {
            case 'ai_suite_massaction_pages_prepare':
                return $this->pagesPrepareAction();
            case 'ai_suite_massaction_filereferences_prepare':
                return $this->fileReferencesPrepareAction();
            case 'ai_suite_massaction_pages_translation_prepare':
                return $this->pagesTranslationPrepareAction();
            default:
                return $this->overviewAction();
        }
    }

    public function overviewAction(): ResponseInterface
    {
        $this->view->assign('pid', $this->request->getQueryParams()['id'] ?? $this->request->getAttribute('site')->getRootPageId());
        return $this->view->renderResponse('MassAction/Overview');
    }

    public function pagesPrepareAction(): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/mass-action/pages-prepare.js');
        $availableLanguages = $this->siteService->getAvailableLanguages(true);
        ksort($availableLanguages);
        $accessablePages = $this->backendUserService->fetchAccessablePages();
        $pageId = $this->sessionService->getWebPageId();
        if ($pageId > 0 && array_key_exists($pageId, $accessablePages)) {
            $pageTitle = $accessablePages[$pageId];
            $this->view->assignMultiple([
                'pageTitle' => $pageTitle,
                'pageId' => $pageId,
            ]);
        }
        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_massaction_pages_prepare');
        if (isset($sessionData['massActionPagesPrepare'])) {
            $this->view->assign('preSelection', $sessionData['massActionPagesPrepare']);
        }
        $this->view->assignMultiple([
            'pagesSelect' => $accessablePages,
            'pageTypes' => $this->backendUserService->getAccessablePageTypes(),
            'depths' => [
                0 => $this->translationService->translate('tx_aisuite.massActionSection.filter.depth.onlyThisPage'),
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5
            ],
            'columns' => $this->metadataService->getMetadataColumns(),
            'sysLanguages' => $availableLanguages
        ]);
        return $this->view->renderResponse('MassAction/PagesPrepare');
    }

    public function fileReferencesPrepareAction(): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/mass-action/file-references-prepare.js');
        $availableLanguages = $this->siteService->getAvailableLanguages(true);
        ksort($availableLanguages);
        $accessablePages = $this->backendUserService->fetchAccessablePages();
        $pageId = $this->sessionService->getWebPageId();
        if ($pageId > 0 && array_key_exists($pageId, $accessablePages)) {
            $pageTitle = $accessablePages[$pageId];
            $this->view->assignMultiple([
                'pageTitle' => $pageTitle,
                'pageId' => $pageId,
            ]);
        }
        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_massaction_filereferences_prepare');
        if (isset($sessionData['massActionFileReferencesPrepare'])) {
            $this->view->assign('preSelection', $sessionData['massActionFileReferencesPrepare']);
        }
        $this->view->assignMultiple([
            'pagesSelect' => $this->backendUserService->fetchAccessablePages(),
            'depths' => [
                0 => $this->translationService->translate('tx_aisuite.massActionSection.filter.depth.onlyThisPage'),
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5
            ],
            'columns' => $this->metadataService->getFileMetadataColumns(),
            'sysLanguages' => $availableLanguages,
        ]);
        return $this->view->renderResponse('MassAction/FileReferencesPrepare');
    }

    public function pagesTranslationPrepareAction(): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/mass-action/pages-translation-prepare.js');

        $availableLanguages = $this->siteService->getAvailableLanguages(true);
        ksort($availableLanguages);
        $accessablePages = $this->backendUserService->fetchAccessablePages();
        $pageId = $this->sessionService->getWebPageId();

        if ($pageId > 0 && array_key_exists($pageId, $accessablePages)) {
            $pageTitle = $accessablePages[$pageId];
            $this->view->assignMultiple([
                'pageTitle' => $pageTitle,
                'pageId' => $pageId,
            ]);
        }

        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_massaction_pages_translation_prepare');
        if (isset($sessionData['massActionPagesTranslationPrepare'])) {
            $this->view->assign('preSelection', $sessionData['massActionPagesTranslationPrepare']);
        }

        $this->view->assignMultiple([
            'pagesSelect' => $accessablePages,
            'pageTypes' => $this->backendUserService->getAccessablePageTypes(),
            'depths' => [
                0 => $this->translationService->translate('tx_aisuite.massActionSection.filter.depth.onlyThisPage'),
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5
            ],
            'sysLanguages' => $availableLanguages,
            'translationScopeOptions' => [
                'all' => $this->translationService->translate('tx_aisuite.massActionSection.translation.scope.all'),
                'metadata' => $this->translationService->translate('tx_aisuite.massActionSection.translation.scope.metadata'),
                'content' => $this->translationService->translate('tx_aisuite.massActionSection.translation.scope.content')
            ]
        ]);

        return $this->view->renderResponse('MassAction/PagesTranslationPrepare');
    }
}
