<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Domain\Repository\AuditResultRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\BackgroundTaskService;
use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Utility\AuditScoreUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ModifyPageLayoutContentEventListener
{
    public function __construct(
        protected PageRenderer $pageRenderer,
        protected BackgroundTaskService $backgroundTaskService,
        protected AuditResultRepository $auditResults,
        protected BackendUserService $backendUserService,
        protected LocalizationService $localizationService,
        protected UriBuilder $uriBuilder,
        protected BackendLayoutView $backendLayoutView,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();
        $pageId = (int) ($request->getQueryParams()['id'] ?? 0);

        $auditTile = $this->buildAuditTile($pageId);
        if ('' !== $auditTile) {
            $event->addHeaderContent($auditTile);
        }

        $pageLayoutContext = $this->createPageLayoutContext($request, $pageId);
        if (empty($pageLayoutContext->getNewLanguageOptions())) {
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
            $results = '';
            foreach (['seo', 'a11y'] as $type) {
                $cached = $this->auditResults->findLatest($pageId, $type);
                if (null === $cached) {
                    continue;
                }
                $summary = $cached['result']['audit']['summary'] ?? [];
                $score = AuditScoreUtility::fromSummary(\is_array($summary) ? $summary : []);
                $results .= sprintf(
                    '<a class="aisuite-audit-tile__result" href="%s" title="%s">'
                    .'<span class="aisuite-audit-score aisuite-audit-tile__score aisuite-audit-score--%s">%d</span>'
                    .'<span>%s<br/><small class="aisuite-audit-tile__date">%s</small></span>'
                    .'</a>',
                    htmlspecialchars((string) $this->uriBuilder->buildUriFromRoute('ai_suite_audit_cached', ['pageId' => $pageId, 'auditType' => $type, 'languageUid' => 0])),
                    htmlspecialchars($this->localizationService->translate('module:aiSuite.module.audit.tile.view')),
                    AuditScoreUtility::range($score),
                    $score,
                    htmlspecialchars($this->localizationService->translate('module:aiSuite.module.audit.type.'.$type)),
                    htmlspecialchars(date('d.m.Y', $cached['runTs'])),
                );
            }
            $newAuditUrl = (string) $this->uriBuilder->buildUriFromRoute('ai_suite_audit', ['id' => $pageId]);
        } catch (\Throwable) {
            return '';
        }

        if ('' === $results) {
            $results = '<span class="aisuite-audit-tile__empty">'.htmlspecialchars($this->localizationService->translate('module:aiSuite.module.audit.tile.noAudit')).'</span>';
        }

        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/audit.css');

        return sprintf(
            '<div class="callout callout-info aisuite-audit-tile"><div class="callout-body aisuite-audit-tile__body">'
            .'<strong>%s</strong>%s'
            .'<a class="btn btn-primary btn-sm aisuite-audit-tile__new" href="%s">%s</a>'
            .'</div></div>',
            htmlspecialchars($this->localizationService->translate('module:aiSuite.module.audit.tile.title')),
            $results,
            htmlspecialchars($newAuditUrl),
            htmlspecialchars($this->localizationService->translate('module:aiSuite.module.audit.tile.newAudit')),
        );
    }

    protected function createPageLayoutContext(ServerRequestInterface $request, int $pageId): PageLayoutContext
    {
        $pageinfo = BackendUtility::readPageAccess($pageId, '') ?: [];

        $backendLayout = $this->backendLayoutView->getBackendLayoutForPage($pageId);

        return GeneralUtility::makeInstance(
            PageLayoutContext::class,
            $pageinfo,
            $backendLayout
        );
    }
}
