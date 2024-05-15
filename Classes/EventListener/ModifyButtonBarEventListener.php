<?php

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Utility\SiteUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
        $languageService = $this->getLanguageService();
        $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/ajax/image/generate-image-filelist.js');
        $buttons = $event->getButtons();

        if ($request->getUri()->getPath() === '/typo3/module/file/list') {
            $buttonIcon = $this->iconFactory->getIcon('apps-clipboard-images', Icon::SIZE_SMALL);
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
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
