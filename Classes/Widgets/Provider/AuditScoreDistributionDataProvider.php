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

namespace AutoDudes\AiSuite\Widgets\Provider;

use AutoDudes\AiSuite\Domain\Repository\AuditResultRepository;
use AutoDudes\AiSuite\Utility\AuditScoreUtility;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;

final class AuditScoreDistributionDataProvider implements ChartDataProviderInterface
{
    private const COLORS = ['high' => '#1e8e3e', 'medium' => '#b06000', 'low' => '#c5221f'];

    public function __construct(
        private readonly AuditResultRepository $auditResults,
    ) {}

    /**
     * @return array{labels: list<string>, datasets: list<array{backgroundColor: list<string>, data: list<int>}>}
     */
    public function getChartData(): array
    {
        $buckets = self::bucketSummaries($this->auditResults->findAllSummaries('seo'));

        return [
            'labels' => [
                $this->translate('widgets.auditScores.high'),
                $this->translate('widgets.auditScores.medium'),
                $this->translate('widgets.auditScores.low'),
            ],
            'datasets' => [[
                'backgroundColor' => array_values(self::COLORS),
                'data' => [$buckets['high'], $buckets['medium'], $buckets['low']],
            ]],
        ];
    }

    /**
     * @param list<array{pageUid: int, summary: array<string, mixed>}> $summaries
     *
     * @return array{high: int, low: int, medium: int}
     */
    public static function bucketSummaries(array $summaries): array
    {
        $buckets = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($summaries as $entry) {
            ++$buckets[AuditScoreUtility::range(AuditScoreUtility::fromSummary($entry['summary']))];
        }

        return $buckets;
    }

    private function translate(string $key): string
    {
        $languageService = $GLOBALS['LANG'] ?? null;

        return $languageService?->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang_module.xlf:'.$key) ?: $key;
    }
}
