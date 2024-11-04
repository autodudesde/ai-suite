<?php

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Utility\BackendUserUtility;
use AutoDudes\AiSuite\Utility\SiteUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsEventListener(
    identifier: 'ai-suite/modify-button-bar-event-listener',
    event: ModifyButtonBarEvent::class,
)]
class ModifyButtonBarEventListener
{
    protected IconFactory $iconFactory;

    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $buttons = $event->getButtons();
        if ($request->getUri()->getPath() === '/typo3/module/file/list' && BackendUserUtility::checkPermissions('tx_aisuite_features:enable_image_generation')) {
            $languageService = $this->getLanguageService();
            $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
            $pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/ajax/image/generate-image-filelist.js');
            $buttonIcon = $this->iconFactory->getIcon('apps-clipboard-images', IconSize::SMALL);
            $buttons[ButtonBar::BUTTON_POSITION_LEFT][5][] = $event->getButtonBar()
                ->makeLinkButton()
                ->setClasses('btn btn-default t3js-ai-suite-image-generation-filelist-add-btn')
                ->setDataAttributes([
                    'target-folder' => $request->getQueryParams()['id'] ?? '1:/',
                    'uuid' => UuidUtility::generateUuid(),
                    'page-id' => SiteUtility::getAvailableRootPages()[0], // needed for status updates
                ])
                ->setTitle($buttonText)
                ->setShowLabelText(true)
                ->setIcon($buttonIcon);
            $event->setButtons($buttons);
        }
        if ($request->getUri()->getPath() === '/typo3/module/web/list' && ExtensionManagementUtility::isLoaded('news') && array_key_exists('id', $request->getQueryParams()) && BackendUserUtility::checkPermissions('tx_aisuite_features:enable_news_generation')) {
            $languageService = $this->getLanguageService();
            $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateNewsWithAiButton'));
            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
            $buttonIcon = $iconFactory->getIcon('content-news', IconSize::SMALL);
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $uri = (string)$uriBuilder->buildUriFromRoute('ai_suite_record_edit', [
                'edit' => [
                    'tx_news_domain_model_news' => [
                        $request->getQueryParams()['id'] => 'new',
                    ],
                ],
                'returnUrl' => '/typo3/module/web/list?token=' . $request->getQueryParams()['token'] .'&id=' . $request->getQueryParams()['id'],
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
        if ($request->getUri()->getPath() === '/typo3/module/web/layout') {
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
            $pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
            $pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/translation/localization.js');
        }
        if ($request->getUri()->getPath() === '/typo3/module/web/list' || $request->getUri()->getPath() === '/typo3/record/edit' && BackendUserUtility::checkPermissions('tx_aisuite_features:enable_translation')) {
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
            $pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
            $pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/translation/record-localization.js');
        }
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
