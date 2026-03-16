<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackgroundTaskService;
use TYPO3\CMS\Backend\Context\PageContext;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsEventListener(
    identifier: 'tx-ai-suite/modify-page-layout-event-listener',
    event: ModifyPageLayoutContentEvent::class,
)]
class ModifyPageLayoutContentEventListener
{
    public function __construct(
        protected PageRenderer $pageRenderer,
        protected BackgroundTaskService $backgroundTaskService,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();
        $pageContext = $request->getAttribute('pageContext');

        if (!$pageContext instanceof PageContext) {
            return;
        }

        if (empty($pageContext->languageInformation->creatableLanguageIds)) {
            return;
        }

        $translationButtons = $this->backgroundTaskService->generateTranslationPageButtons($request);

        if (!empty($translationButtons)) {
            $event->addHeaderContent($translationButtons);
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/translation/page-localization.js');
        }
    }
}
