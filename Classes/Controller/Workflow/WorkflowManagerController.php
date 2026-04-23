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

namespace AutoDudes\AiSuite\Controller\Workflow;

use AutoDudes\AiSuite\Controller\AbstractBackendController;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
class WorkflowManagerController extends AbstractBackendController
{
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
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
    }

    /**
     * @throws RouteNotFoundException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $identifier = $request->getAttribute('route')->getOption('_identifier');

        switch ($identifier) {
            case 'ai_suite_workflow_pages_prepare':
                return $this->pagesPrepareAction();

            case 'ai_suite_workflow_filereferences_prepare':
                return $this->fileReferencesPrepareAction();

            case 'ai_suite_workflow_pages_translation_prepare':
                return $this->pagesTranslationPrepareAction();

            default:
                return $this->overviewAction();
        }
    }

    public function overviewAction(): ResponseInterface
    {
        $this->view->assign('pid', $this->request->getQueryParams()['id'] ?? $this->request->getAttribute('site')->getRootPageId());

        return $this->view->renderResponse('Workflow/Overview');
    }

    public function pagesPrepareAction(): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/workflow/pages-prepare.js');
        $pageId = $this->aiSuiteContext->sessionService->getWebPageId();
        $availableLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true, $pageId);
        $accessablePages = $this->aiSuiteContext->backendUserService->fetchAccessablePages();
        $selectedPageId = $pageId > 0 ? $pageId : array_key_first($accessablePages);
        if ($pageId > 0 && array_key_exists($pageId, $accessablePages)) {
            $pageTitle = $accessablePages[$pageId];
            $this->view->assignMultiple([
                'pageTitle' => $pageTitle,
                'pageId' => $pageId,
            ]);
        }
        $sessionData = $this->aiSuiteContext->sessionService->getParametersForRoute('ai_suite_workflow_pages_prepare');
        if (isset($sessionData['workflowPagesPrepare'])) {
            $this->view->assign('preSelection', $sessionData['workflowPagesPrepare']);
        }
        $this->view->assignMultiple([
            'pagesSelect' => $accessablePages,
            'selectedPageId' => $selectedPageId,
            'pageTypes' => $this->aiSuiteContext->backendUserService->getAccessablePageTypes(),
            'depths' => [
                0 => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.workflow.columns.filter.depth.onlyThisPage'),
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5,
            ],
            'columns' => $this->aiSuiteContext->metadataService->getMetadataColumns(),
            'sysLanguages' => $availableLanguages,
        ]);

        return $this->view->renderResponse('Workflow/PagesPrepare');
    }

    public function fileReferencesPrepareAction(): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/workflow/file-references-prepare.js');
        $pageId = $this->aiSuiteContext->sessionService->getWebPageId();
        $availableLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true, $pageId);
        $accessablePages = $this->aiSuiteContext->backendUserService->fetchAccessablePages();
        $selectedPageId = $pageId > 0 ? $pageId : array_key_first($accessablePages);
        if ($pageId > 0 && array_key_exists($pageId, $accessablePages)) {
            $pageTitle = $accessablePages[$pageId];
            $this->view->assignMultiple([
                'pageTitle' => $pageTitle,
                'pageId' => $pageId,
            ]);
        }
        $sessionData = $this->aiSuiteContext->sessionService->getParametersForRoute('ai_suite_workflow_filereferences_prepare');
        if (isset($sessionData['workflowFileReferencesPrepare'])) {
            $this->view->assign('preSelection', $sessionData['workflowFileReferencesPrepare']);
        }
        $this->view->assignMultiple([
            'pagesSelect' => $this->aiSuiteContext->backendUserService->fetchAccessablePages(),
            'selectedPageId' => $selectedPageId,
            'depths' => [
                0 => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.workflow.columns.filter.depth.onlyThisPage'),
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5,
            ],
            'columns' => $this->aiSuiteContext->metadataService->getFileMetadataColumns(),
            'sysLanguages' => $availableLanguages,
        ]);

        return $this->view->renderResponse('Workflow/FileReferencesPrepare');
    }

    public function pagesTranslationPrepareAction(): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/workflow/pages-translation-prepare.js');
        $pageId = $this->aiSuiteContext->sessionService->getWebPageId();
        $availableSourceLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true, $pageId, true);
        $availableTargetLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true, $pageId);
        $accessablePages = $this->aiSuiteContext->backendUserService->fetchAccessablePages();
        $selectedPageId = $pageId > 0 ? $pageId : array_key_first($accessablePages);

        if ($pageId > 0 && array_key_exists($pageId, $accessablePages)) {
            $pageTitle = $accessablePages[$pageId];
            $this->view->assignMultiple([
                'pageTitle' => $pageTitle,
                'pageId' => $pageId,
            ]);
        }

        $sessionData = $this->aiSuiteContext->sessionService->getParametersForRoute('ai_suite_workflow_pages_translation_prepare');
        if (isset($sessionData['workflowPagesTranslationPrepare'])) {
            $this->view->assign('preSelection', $sessionData['workflowPagesTranslationPrepare']);
        }

        $this->view->assignMultiple([
            'pagesSelect' => $accessablePages,
            'selectedPageId' => $selectedPageId,
            'pageTypes' => $this->aiSuiteContext->backendUserService->getAccessablePageTypes(),
            'depths' => [
                0 => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.workflow.columns.filter.depth.onlyThisPage'),
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5,
            ],
            'sourceLanguages' => $availableSourceLanguages,
            'targetLanguages' => $availableTargetLanguages,
            'translationScopeOptions' => [
                'all' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.workflow.columns.translation.scope.all'),
                'metadata' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.workflow.columns.translation.scope.metadata'),
                'content' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.workflow.columns.translation.scope.content'),
            ],
        ]);

        return $this->view->renderResponse('Workflow/PagesTranslationPrepare');
    }
}
