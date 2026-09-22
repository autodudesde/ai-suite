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

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;

class TcaSelectLabelService implements SingletonInterface
{
    public function __construct(
        protected readonly LocalizationService $localizationService,
    ) {}

    /**
     * @param array<int|string, mixed> $items
     */
    public function label(array $items, string $value): string
    {
        $value = trim($value);
        if ('' === $value) {
            return '';
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemValue = $item['value'] ?? $item[1] ?? null;
            if ('--div--' === $itemValue || null === $itemValue || (string) $itemValue !== $value) {
                continue;
            }
            $label = (string) ($item['label'] ?? $item[0] ?? '');
            if (str_starts_with($label, 'LLL:')) {
                $label = $this->localizationService->translate($label);
            }

            return '' === $label ? $value : $label;
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $items
     */
    public function labelList(array $items, string $values): string
    {
        $labels = [];
        foreach (explode(',', $values) as $value) {
            $label = $this->label($items, $value);
            if ('' !== $label) {
                $labels[] = $label;
            }
        }

        return implode(', ', $labels);
    }
}
