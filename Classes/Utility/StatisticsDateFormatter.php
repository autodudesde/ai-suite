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

final class StatisticsDateFormatter
{
    public static function formatPeriod(string $period, string $language): string
    {
        $isMonth = 7 === strlen($period);
        $normalized = $isMonth ? $period.'-01' : $period;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);
        if (false === $date) {
            return $period;
        }

        $locale = self::resolveLocale($language);

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = $isMonth
                ? new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, \IntlDateFormatter::GREGORIAN, 'MMM y')
                : new \IntlDateFormatter($locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);
            $formatted = $formatter->format($date);
            if (is_string($formatted) && '' !== $formatted) {
                return $formatted;
            }
        }

        // Fallback without ext-intl
        if (str_starts_with($locale, 'de')) {
            return $isMonth ? $date->format('m.Y') : $date->format('d.m.Y');
        }

        return $isMonth ? $date->format('Y-m') : $date->format('Y-m-d');
    }

    private static function resolveLocale(string $language): string
    {
        $language = trim($language);
        if ('' === $language || 'default' === $language) {
            return 'en';
        }

        return $language;
    }
}
