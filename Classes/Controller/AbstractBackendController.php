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


use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Template\Components\Buttons\AiSuiteLinkButton;
use AutoDudes\AiSuite\Service\BackendUserService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractBackendController
{
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected IconFactory $iconFactory;
    protected UriBuilder $uriBuilder;
    protected PageRenderer $pageRenderer;
    protected FlashMessageService $flashMessageService;
    protected SendRequestService $requestService;
    protected BackendUserService $backendUserService;
    protected LibraryService $libraryService;
    protected PromptTemplateService $promptTemplateService;
    protected SiteService $siteService;
    protected TranslationService $translationService;
    protected ServerRequestInterface $request;
    protected ModuleTemplate $view;

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
        TranslationService $translationService
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->iconFactory = $iconFactory;
        $this->uriBuilder = $uriBuilder;
        $this->pageRenderer = $pageRenderer;
        $this->flashMessageService = $flashMessageService;
        $this->requestService = $requestService;
        $this->backendUserService = $backendUserService;
        $this->libraryService = $libraryService;
        $this->promptTemplateService = $promptTemplateService;
        $this->siteService = $siteService;
        $this->translationService = $translationService;
    }

    public function injectModuleTemplateFactory(ModuleTemplateFactory $moduleTemplateFactory): void
    {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
    }

    /**
     * @throws RouteNotFoundException
     */
    public function initialize(ServerRequestInterface $request): void
    {
        $this->request = $request;
        $this->view = $this->moduleTemplateFactory->create($request);
        $this->view->setTitle('AI Suite');
        $this->view->setFlashMessageQueue($this->flashMessageService->getMessageQueueByIdentifier('ai_suite.template.flashMessages'));
        $this->view->setModuleId('aiSuite');
        $this->generateButtonBar();

        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
    }

    /**
     * @throws RouteNotFoundException
     */
    protected function generateButtonBar(): void
    {
        $buttonBar = $this->view->getDocHeaderComponent()->getButtonBar();
        $buttonBar->addButton($this->buildButton('actions-menu', 'tx_aisuite.module.actionmenu.dashboard', 'btn-md btn-primary rounded', 'ai_suite_dashboard'));
        $buttonBar->addButton($this->buildButton('actions-file-text', 'tx_aisuite.module.actionmenu.pages', 'btn-md btn-secondary mx-2 rounded', 'ai_suite_page'));
        $buttonBar->addButton($this->buildButton('content-store', 'tx_aisuite.module.actionmenu.agencies', 'btn-md btn-default rounded', 'ai_suite_agencies'));
        $buttonBar->addButton($this->buildButton('actions-file-text', 'tx_aisuite.module.actionmenu.promptTemplate', 'btn-md btn-default mx-2 rounded', 'ai_suite_prompt'));
        if($this->backendUserService->checkPermissions('tx_aisuite_features:enable_massaction_generation')) {
            $buttonBar->addButton($this->buildButton('actions-duplicate', 'tx_aisuite.module.actionmenu.massAction', 'btn-md btn-default rounded', 'ai_suite_massaction'));
        }
        if($this->backendUserService->checkPermissions('tx_aisuite_features:enable_background_task_handling')) {
            $buttonBar->addButton($this->buildButton('overlay-scheduled', 'tx_aisuite.module.actionmenu.backgroundTask', 'btn-md btn-default mx-2 rounded', 'ai_suite_backgroundtask'));
        }
    }

    /**
     * @throws RouteNotFoundException
     */
    protected function buildButton(string $iconIdentifier, string $translationKey, string $classes, string $route): AiSuiteLinkButton
    {
        $rootPageId = $this->request->getAttribute('site')->getRootPageId();
        $uriParameters = [
            'id' => $this->request->getQueryParams()['id'] ?? $rootPageId,
        ];
        $url = (string)$this->uriBuilder->buildUriFromRoute($route, $uriParameters);
        $button = GeneralUtility::makeInstance(AiSuiteLinkButton::class);
        return $button
            ->setIcon($this->iconFactory->getIcon($iconIdentifier, 'small'))
            ->setTitle($this->translationService->translate($translationKey))
            ->setShowLabelText(true)
            ->setClasses($classes)
            ->setHref($url);
    }
}
