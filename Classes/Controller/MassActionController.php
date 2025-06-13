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
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

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
        MetadataService $metadataService,
        MassActionService $massActionService,
        LoggerInterface $logger,
        ResponseFactory $responseFactory
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
            $responseFactory
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
        return match ($identifier) {
            'ai_suite_massaction_pages_prepare' => $this->pagesPrepareAction(),
            'ai_suite_massaction_filereferences_prepare' => $this->fileReferencesPrepareAction(),
            default => $this->overviewAction(),
        };
    }

    public function overviewAction(): ResponseInterface
    {
        return $this->htmlResponse(
            $this->view->setContent(
                $this->renderView(
                    'MassAction/Overview',
                    [
                        'pid' => $this->request->getQueryParams()['id'] ?? $this->request->getAttribute('site')->getRootPageId()
                    ]
                )
            )->renderContent()
        );
    }

    public function pagesPrepareAction(): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/AiSuite/MassAction/PagesPrepare');

        $availableLanguages = $this->siteService->getAvailableLanguages(true);
        ksort($availableLanguages);
        $params = $this->request->getQueryParams();

        $pageId = 0;
        if (isset($params['id'])) {
            $pageId = (int)$params['id'];
        }
        $accessablePages = $this->backendUserService->getPagesByPidAndDepth($pageId, 99);
        $assignMultiple = [];
        if ($pageId > 0 && array_key_exists($pageId, $accessablePages)) {
            $pageTitle = $accessablePages[$pageId];
            $assignMultiple = [
                'pageTitle' => $pageTitle,
                'pageId' => $pageId,
            ];
        }

        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_massaction_pages_prepare');
        if (isset($sessionData['massActionPagesPrepare'])) {
            $assignMultiple['preSelection'] = $sessionData['massActionPagesPrepare'];
        }
        $assignMultiple = array_merge($assignMultiple, [
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
        return $this->htmlResponse(
            $this->view->setContent(
                $this->renderView(
                    'MassAction/PagesPrepare',
                    $assignMultiple
                )
            )->renderContent()
        );
    }

    public function fileReferencesPrepareAction(): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/AiSuite/MassAction/FileReferencesPrepare');

        $availableLanguages = $this->siteService->getAvailableLanguages(true);
        ksort($availableLanguages);
        $params = $this->request->getQueryParams();

        $pageId = 0;
        if (isset($params['id'])) {
            $pageId = (int)$params['id'];
        }
        $accessablePages = $this->backendUserService->getPagesByPidAndDepth($pageId, 99);
        $assignMultiple = [];
        if ($pageId > 0 && array_key_exists($pageId, $accessablePages)) {
            $pageTitle = $accessablePages[$pageId];
            $assignMultiple = [
                'pageTitle' => $pageTitle,
                'pageId' => $pageId,
            ];
        }
        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_massaction_filereferences_prepare');
        if (isset($sessionData['massActionFileReferencesPrepare'])) {
            $assignMultiple['preSelection'] = $sessionData['massActionFileReferencesPrepare'];
        }
        $assignMultiple = array_merge($assignMultiple, [
            'pagesSelect' => $accessablePages,
            'pageTypes' => $this->backendUserService->getAccessablePageTypes(), // TODO different from pagesPrepare (not needed here)
            'depths' => [
                0 => $this->translationService->translate('tx_aisuite.massActionSection.filter.depth.onlyThisPage'),
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5
            ],
            'columns' => $this->metadataService->getFileMetadataColumns(), // TODO function different from pagesPrepare
            'sysLanguages' => $availableLanguages,
        ]);
        return $this->htmlResponse(
            $this->view->setContent(
                $this->renderView(
                    'MassAction/FileReferencesPrepare',
                    $assignMultiple
                )
            )->renderContent()
        );
    }

}
