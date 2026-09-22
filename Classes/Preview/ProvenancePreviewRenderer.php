<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Preview;

use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\ProvenanceService;
use AutoDudes\AiSuite\Service\TcaCompatibilityService;
use TYPO3\CMS\Backend\Preview\PreviewRendererInterface;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// PreviewRendererInterface is the only preview extension point common to v12/v13/v14; decorates, never replaces
class ProvenancePreviewRenderer implements PreviewRendererInterface
{
    public const ORIGINAL_RENDERERS = 'aiSuiteOriginalPreviewRenderers';

    public const DEFAULT_KEY = '_default';

    public function __construct(
        protected readonly ProvenanceService $provenanceService,
        protected readonly LocalizationService $localizationService,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
    ) {}

    public function renderPageModulePreviewHeader(GridColumnItem $item): string
    {
        return $this->delegate($item)->renderPageModulePreviewHeader($item);
    }

    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        return $this->delegate($item)->renderPageModulePreviewContent($item).$this->badge($item);
    }

    public function renderPageModulePreviewFooter(GridColumnItem $item): string
    {
        return $this->delegate($item)->renderPageModulePreviewFooter($item);
    }

    public function wrapPageModulePreview(string $previewHeader, string $previewContent, GridColumnItem $item): string
    {
        return $this->delegate($item)->wrapPageModulePreview($previewHeader, $previewContent, $item);
    }

    protected function delegate(GridColumnItem $item): PreviewRendererInterface
    {
        $originals = $GLOBALS['TCA'][$item->getTable()]['ctrl'][self::ORIGINAL_RENDERERS] ?? [];
        $className = null;

        if (is_array($originals)) {
            $className = $originals[$item->getRecordType()] ?? $originals[self::DEFAULT_KEY] ?? null;
        }

        if (!is_string($className) || '' === $className || self::class === $className
            || !is_a($className, PreviewRendererInterface::class, true)) {
            $className = StandardContentPreviewRenderer::class;
        }

        /** @var PreviewRendererInterface $renderer */
        $renderer = GeneralUtility::makeInstance($className);

        return $renderer;
    }

    protected function badge(GridColumnItem $item): string
    {
        try {
            $table = $item->getTable();
            $uid = $this->tcaCompatibilityService->resolveGridItemUid($item);
            if ($uid <= 0) {
                return '';
            }

            $this->provenanceService->primeForPage($table, $item->getContext()->getPageId());
            $rows = $this->provenanceService->disclosedFor($table, $uid);
            if ([] === $rows) {
                return '';
            }

            return sprintf(
                '<div class="mt-1"><span class="badge bg-info" title="%s">%s</span></div>',
                htmlspecialchars($this->describe($rows[0])),
                htmlspecialchars($this->label($rows[0])),
            );
        } catch (\Throwable) {
            // Never let a missing badge break the page module
            return '';
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function label(array $row): string
    {
        return $this->localizationService->translate(
            'module:aiSuite.provenance.badge.'.((string) ($row['mode'] ?? 'generated')),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function describe(array $row): string
    {
        $model = (string) ($row['model'] ?? '');
        $crdate = (int) ($row['crdate'] ?? 0);

        return $this->localizationService->translate(
            'module:aiSuite.provenance.badge.description',
            [
                '' !== $model ? $model : $this->localizationService->translate('module:aiSuite.provenance.badge.unknownModel'),
                ProvenanceService::formatDate($crdate),
                (string) ($row['feature'] ?? ''),
            ],
        );
    }
}
