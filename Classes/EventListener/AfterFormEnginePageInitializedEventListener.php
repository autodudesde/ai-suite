<?php

namespace AutoDudes\AiSuite\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsEventListener(
    identifier: 'tx-ai-suite/after-form-engine-page-initialized-event-listener',
    event: AfterFormEnginePageInitializedEvent::class,
)]
class AfterFormEnginePageInitializedEventListener
{
    protected PageRenderer $pageRenderer;
    public function __construct(PageRenderer $pageRenderer)
    {
        $this->pageRenderer = $pageRenderer;
    }

    public function __invoke(AfterFormEnginePageInitializedEvent $event): void
    {
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
    }
}
