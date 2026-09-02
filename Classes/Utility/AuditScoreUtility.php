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

namespace AutoDudes\AiSuite\Utility;

final class AuditScoreUtility
{
    private const WEIGHT_ERROR = 15;
    private const WEIGHT_WARNING = 5;
    private const WEIGHT_NOTICE = 1;

    /**
     * @param array<string, mixed> $summary
     */
    public static function fromSummary(array $summary): int
    {
        $penalty = self::WEIGHT_ERROR * max(0, (int) ($summary['errors'] ?? 0))
            + self::WEIGHT_WARNING * max(0, (int) ($summary['warnings'] ?? 0))
            + self::WEIGHT_NOTICE * max(0, (int) ($summary['notices'] ?? 0));

        return max(0, 100 - $penalty);
    }

    /**
     * @return 'high'|'low'|'medium'
     */
    public static function range(int $score): string
    {
        return match (true) {
            $score >= 90 => 'high',
            $score >= 50 => 'medium',
            default => 'low',
        };
    }
}
