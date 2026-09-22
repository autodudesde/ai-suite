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

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Preview\ProvenancePreviewRenderer;
use TYPO3\CMS\Backend\Preview\PreviewRendererInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;

// types.<CType>.previewRenderer resolves before ctrl.previewRenderer, so both are swapped here
#[AsEventListener(
    identifier: 'tx-ai-suite/register-provenance-preview-renderer',
    event: AfterTcaCompilationEvent::class,
)]
class RegisterProvenancePreviewRendererListener
{
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $tca = $event->getTca();
        if (!isset($tca['tt_content'])) {
            return;
        }

        $originals = [];

        foreach (array_keys($tca['tt_content']['types'] ?? []) as $type) {
            $configured = $tca['tt_content']['types'][$type]['previewRenderer'] ?? null;
            if (is_array($configured)) {
                continue;
            }

            if (self::isForeignRenderer($configured)) {
                $originals[(string) $type] = $configured;
            }

            $tca['tt_content']['types'][$type]['previewRenderer'] = ProvenancePreviewRenderer::class;
        }

        $configured = $tca['tt_content']['ctrl']['previewRenderer'] ?? null;
        if (self::isForeignRenderer($configured)) {
            $originals[ProvenancePreviewRenderer::DEFAULT_KEY] = $configured;
        }

        $tca['tt_content']['ctrl']['previewRenderer'] = ProvenancePreviewRenderer::class;
        $tca['tt_content']['ctrl'][ProvenancePreviewRenderer::ORIGINAL_RENDERERS] = $originals;

        $event->setTca($tca);
    }

    private static function isForeignRenderer(mixed $configured): bool
    {
        return is_string($configured)
            && '' !== $configured
            && ProvenancePreviewRenderer::class !== $configured
            && is_a($configured, PreviewRendererInterface::class, true);
    }
}
