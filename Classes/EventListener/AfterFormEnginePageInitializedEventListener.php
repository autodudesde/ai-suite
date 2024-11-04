<?php

namespace AutoDudes\AiSuite\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsEventListener(
    identifier: 'ai-suite/after-form-engine-page-initialized-event-listener',
    event: AfterFormEnginePageInitializedEvent::class,
)]
class AfterFormEnginePageInitializedEventListener
{
    public function __invoke(AfterFormEnginePageInitializedEvent $event): void
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
    }
}
