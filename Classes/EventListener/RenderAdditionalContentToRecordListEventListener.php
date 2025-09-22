<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackgroundTaskService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\RenderAdditionalContentToRecordListEvent;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Backend\View\Drawing\DrawingConfiguration;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Backend\View\PageViewMode;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RenderAdditionalContentToRecordListEventListener
{
    public function __construct(
        protected PageRenderer $pageRenderer,
        protected BackgroundTaskService $backgroundTaskService,
        protected BackendLayoutView $backendLayoutView,
    ) {
    }

    public function __invoke(RenderAdditionalContentToRecordListEvent $event): void
    {
        $request = $event->getRequest();
        $pageLayoutContext = $this->createPageLayoutContext($request);

        if (!empty($pageLayoutContext->getNewLanguageOptions())) {
            $translationButtons = $this->backgroundTaskService->generateTranslationPageButtons($request);

            if (!empty($translationButtons)) {
                $event->addContentAbove($translationButtons);
                $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/translation/page-localization.js');
            }
        }
    }

    protected function createPageLayoutContext(ServerRequestInterface $request): PageLayoutContext
    {
        $pageId = (int)($request->getQueryParams()['id'] ?? 0);
        $pageinfo = BackendUtility::readPageAccess($pageId, '') ?: [];

        $backendLayout = $this->backendLayoutView->getBackendLayoutForPage($pageId);
        $configuration = DrawingConfiguration::create($backendLayout, [], PageViewMode::LayoutView);

        return GeneralUtility::makeInstance(
            PageLayoutContext::class,
            $pageinfo,
            $backendLayout,
            $request->getAttribute('site'),
            $configuration,
            $request
        );
    }
}
