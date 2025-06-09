<?php

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Domain\Repository\GlossarRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\FileListService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

#[AsEventListener(
    identifier: 'tx-ai-suite/modify-button-bar-event-listener',
    event: ModifyButtonBarEvent::class,
)]
class ModifyButtonBarEventListener
{
    protected PageRenderer $pageRenderer;
    protected IconFactory $iconFactory;
    protected UriBuilder $uriBuilder;
    protected BackendUserService $backendUserService;
    protected FileListService $fileListService;
    protected SiteService $siteService;
    protected UuidService $uuidService;
    protected TranslationService $translationService;
    protected GlossarRepository $glossarRepository;

    protected ExtensionConfiguration $extensionConfiguration;

    protected array $extConf = [];

    public function __construct(
        PageRenderer $pageRenderer,
        IconFactory $iconFactory,
        UriBuilder $uriBuilder,
        BackendUserService $backendUserService,
        FileListService $fileListService,
        SiteService $siteService,
        UuidService $uuidService,
        TranslationService $translationService,
        GlossarRepository $glossarRepository,
        ExtensionConfiguration $extensionConfiguration
    ) {
        $this->pageRenderer = $pageRenderer;
        $this->iconFactory = $iconFactory;
        $this->uriBuilder = $uriBuilder;
        $this->backendUserService = $backendUserService;
        $this->fileListService = $fileListService;
        $this->siteService = $siteService;
        $this->uuidService = $uuidService;
        $this->translationService = $translationService;
        $this->glossarRepository = $glossarRepository;
        $this->extensionConfiguration = $extensionConfiguration;
        $this->extConf = $this->extensionConfiguration->get('ai_suite');
    }

    /**
     * @throws RouteNotFoundException
     */
    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $entryPoint = rtrim($GLOBALS['TYPO3_CONF_VARS']['BE']['entryPoint'] ?? '/typo3', '/');
        $buttons = $event->getButtons();
        if ($request->getUri()->getPath() === $entryPoint . '/module/file/list' &&
            $this->backendUserService->checkPermissions('tx_aisuite_features:enable_image_generation')
        ) {
            $buttonText = htmlspecialchars($this->translationService->translate('aiSuite.generateImageWithAiButton'));
            $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/ajax/image/generate-image-filelist.js');
            $buttonIcon = $this->iconFactory->getIcon('apps-clipboard-images', 'small');
            $buttons[ButtonBar::BUTTON_POSITION_LEFT][5][] = $event->getButtonBar()
                ->makeLinkButton()
                ->setClasses('btn btn-default t3js-ai-suite-image-generation-filelist-add-btn')
                ->setDataAttributes([
                    'target-folder' => $request->getQueryParams()['id'] ?? '1:/',
                    'uuid' => $this->uuidService->generateUuid()
                ])
                ->setTitle($buttonText)
                ->setShowLabelText(true)
                ->setIcon($buttonIcon);
            $event->setButtons($buttons);
        }

        if ($request->getUri()->getPath() === $entryPoint . '/module/web/list' &&
            $this->backendUserService->checkPermissions('tx_aisuite_features:enable_translation_deepl_sync') &&
            $this->glossarRepository->findGlossarEntriesByPid($request->getQueryParams()['id'] ?? 0) > 0
        ) {
            $buttonText = htmlspecialchars($this->translationService->translate('AiSuite.synchronizeDeeplGlossary'));
            $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/glossar/sync.js');
            $buttonIcon = $this->iconFactory->getIcon('tx-aisuite-model-Deepl', 'small');
            $buttons[ButtonBar::BUTTON_POSITION_LEFT][6][] = $event->getButtonBar()
                ->makeLinkButton()
                ->setClasses('btn btn-default t3js-ai-suite-sync-glossary-btn')
                ->setDataAttributes([
                    'pid' => $request->getQueryParams()['id'] ?? 0,
                ])
                ->setTitle($buttonText)
                ->setShowLabelText(true)
                ->setIcon($buttonIcon);
            $event->setButtons($buttons);
        }

        if ($request->getUri()->getPath() === $entryPoint . '/module/web/list' &&
            ExtensionManagementUtility::isLoaded('news') && array_key_exists('id', $request->getQueryParams()) &&
            $this->backendUserService->checkPermissions('tx_aisuite_features:enable_news_generation')
        ) {
            $buttonText = htmlspecialchars($this->translationService->translate('aiSuite.generateNewsWithAiButton'));
            $buttonIcon = $this->iconFactory->getIcon('content-news', 'small');
            $uri = (string)$this->uriBuilder->buildUriFromRoute('ai_suite_record_edit', [
                'edit' => [
                    'tx_news_domain_model_news' => [
                        $request->getQueryParams()['id'] => 'new',
                    ],
                ],
                'returnUrl' => $entryPoint . '/module/web/list?token=' . $request->getQueryParams()['token'] .'&id=' . $request->getQueryParams()['id'],
                'recordType' => '0',
                'recordTable' => 'tx_news_domain_model_news',
                'pid' => $request->getQueryParams()['id']
            ]);
            $buttons[ButtonBar::BUTTON_POSITION_LEFT][5][] = $event->getButtonBar()
                ->makeLinkButton()
                ->setClasses('btn btn-default t3js-ai-suite-news-generation-add-btn')
                ->setTitle($buttonText)
                ->setShowLabelText(true)
                ->setHref($uri)
                ->setIcon($buttonIcon);

            $event->setButtons($buttons);
        }
        if(array_key_exists('disableTranslationFunctionality', $this->extConf) && (bool)$this->extConf['disableTranslationFunctionality'] === false) {
            if ($request->getUri()->getPath() === $entryPoint . '/module/web/layout') {
                $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
                $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
                $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/translation/localization.js');
            }
            if ($request->getUri()->getPath() === $entryPoint . '/module/web/list' || $request->getUri()->getPath() === $entryPoint . '/record/edit' &&
                $this->backendUserService->checkPermissions('tx_aisuite_features:enable_translation')) {
                $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
                $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
                $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/translation/record-localization.js');
            }
        }
    }
}
