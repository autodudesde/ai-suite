<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Domain\Repository\AuditResultRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\BackgroundTaskService;
use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Utility\AuditScoreUtility;
use TYPO3\CMS\Backend\Context\PageContext;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
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
        protected AuditResultRepository $auditResults,
        protected BackendUserService $backendUserService,
        protected LocalizationService $localizationService,
        protected UriBuilder $uriBuilder,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();
        $pageContext = $request->getAttribute('pageContext');

        if (!$pageContext instanceof PageContext) {
            return;
        }

        $auditTile = $this->buildAuditTile($pageContext->languageInformation->pageId);
        if ('' !== $auditTile) {
            $event->addHeaderContent($auditTile);
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

    protected function buildAuditTile(int $pageId): string
    {
        if ($pageId <= 0 || !$this->backendUserService->checkPermissions('tx_aisuite_features:enable_audit')) {
            return '';
        }

        try {
            $scores = [];
            foreach (['seo', 'a11y'] as $type) {
                $cached = $this->auditResults->findLatest($pageId, $type);
                if (null === $cached) {
                    continue;
                }
                $summary = $cached['result']['audit']['summary'] ?? [];
                $score = AuditScoreUtility::fromSummary(\is_array($summary) ? $summary : []);
                $scores[] = [
                    'label' => $this->localizationService->translate('module:aiSuite.module.audit.type.'.$type),
                    'score' => $score,
                    'range' => AuditScoreUtility::range($score),
                    'date' => date('d.m.Y', $cached['runTs']),
                    'viewUrl' => (string) $this->uriBuilder->buildUriFromRoute('ai_suite_audit_cached', ['pageId' => $pageId, 'auditType' => $type, 'languageUid' => 0]),
                ];
            }
            $moduleUrl = (string) $this->uriBuilder->buildUriFromRoute('ai_suite_audit');
        } catch (\Throwable) {
            return '';
        }

        $colors = ['high' => '#1e8e3e', 'medium' => '#b06000', 'low' => '#c5221f'];
        $badges = '';
        foreach ($scores as $entry) {
            $badges .= sprintf(
                '<a href="%s" style="display: inline-flex; align-items: center; gap: 0.45em; margin-right: 1.2em; text-decoration: none; color: inherit;" title="%s">'
                .'<span style="display: inline-flex; align-items: center; justify-content: center; width: 2.3em; height: 2.3em; border-radius: 50%%; border: 3px solid %s; color: %s; font-weight: 700;">%d</span>'
                .'<span>%s<br/><small style="opacity: 0.7;">%s</small></span></a>',
                htmlspecialchars($entry['viewUrl']),
                htmlspecialchars($this->localizationService->translate('module:aiSuite.module.audit.tile.view')),
                $colors[$entry['range']],
                $colors[$entry['range']],
                $entry['score'],
                htmlspecialchars($entry['label']),
                htmlspecialchars($entry['date']),
            );
        }
        if ('' === $badges) {
            $badges = '<span style="opacity: 0.7;">'.htmlspecialchars($this->localizationService->translate('module:aiSuite.module.audit.tile.noAudit')).'</span>';
        }

        return sprintf(
            '<div class="callout callout-info" style="margin-bottom: 1rem;"><div class="callout-body" style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.75em;">'
            .'<strong>%s</strong>%s<a class="btn btn-default btn-sm" href="%s">%s</a>'
            .'</div></div>',
            htmlspecialchars($this->localizationService->translate('module:aiSuite.module.audit.tile.title')),
            $badges,
            htmlspecialchars($moduleUrl),
            htmlspecialchars($this->localizationService->translate('module:aiSuite.module.audit.tile.run')),
        );
    }
}
