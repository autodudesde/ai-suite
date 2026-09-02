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

class SettingsService implements SingletonInterface
{
    public const MASK_PLACEHOLDER = '************';

    private const MASKED_FIELDS = [
        'aiSuiteApiKey',
        'openAiApiKey',
        'anthropicApiKey',
        'googleTranslateApiKey',
        'deeplApiKey',
        'midjourneyApiKey',
        'aiModelHubApiKey',
        'staanApiKey',
        'basicAuth.pass',
    ];

    public function isMaskedField(string $key): bool
    {
        return in_array($key, self::MASKED_FIELDS, true);
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, mixed>                $extConf
     *
     * @return array<string, array<string, mixed>>
     */
    public function buildSettingsForView(array $definitions, array $extConf): array
    {
        $settings = [];
        foreach ($definitions as $key => $definition) {
            $value = $this->getNestedValue($extConf, $key);
            $isMasked = $this->isMaskedField($key);

            $setting = [
                'key' => $key,
                'formKey' => str_replace('.', '_', $key),
                'type' => $definition['type'],
                'category' => $definition['category'],
                'label' => $definition['label'],
                'value' => $isMasked && !empty($value) ? self::MASK_PLACEHOLDER : ($value ?? ($definition['default'] ?? '')),
                'masked' => $isMasked,
            ];

            if (isset($definition['options'])) {
                $setting['options'] = $definition['options'];
                $setting['currentValue'] = $value ?? ($definition['default'] ?? '');
            }

            $settings[$key] = $setting;
        }

        return $settings;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, array<string, mixed>> $settings
     *
     * @return list<array{key: string, label: string, sections: list<array{label: string, settings: list<array<string, mixed>>}>}>
     */
    public function buildCategoryTree(array $definitions, array $settings): array
    {
        $tree = [];
        foreach ($definitions as $key => $definition) {
            if (!isset($settings[$key])) {
                continue;
            }
            $category = (string) ($definition['category'] ?? '');
            $subcategory = (string) ($definition['subcategory'] ?? '');
            if (!isset($tree[$category])) {
                $tree[$category] = [
                    'key' => $category,
                    'label' => (string) ($definition['categoryLabel'] ?? $category),
                    'sections' => [],
                ];
            }
            if (!isset($tree[$category]['sections'][$subcategory])) {
                $tree[$category]['sections'][$subcategory] = [
                    'label' => (string) ($definition['subcategoryLabel'] ?? $subcategory),
                    'settings' => [],
                ];
            }
            $tree[$category]['sections'][$subcategory]['settings'][] = $settings[$key];
        }

        $categories = [];
        foreach ($tree as $category) {
            $sections = [];
            foreach ($category['sections'] as $section) {
                $sections[] = [
                    'label' => $section['label'],
                    'settings' => $section['settings'],
                ];
            }
            $categories[] = [
                'key' => $category['key'],
                'label' => $category['label'],
                'sections' => $sections,
            ];
        }

        return $categories;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setNestedValue(array &$data, string $key, mixed $value): void
    {
        if (!str_contains($key, '.')) {
            $data[$key] = $value;

            return;
        }

        $parts = explode('.', $key);
        $last = array_pop($parts);
        $current = &$data;
        foreach ($parts as $part) {
            if (!isset($current[$part]) || !is_array($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
        $current[$last] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function getNestedValue(array $data, string $key): mixed
    {
        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $current = $data;
            foreach ($parts as $part) {
                if (!is_array($current) || !array_key_exists($part, $current)) {
                    return '';
                }
                $current = $current[$part];
            }

            return $current;
        }

        return $data[$key] ?? '';
    }
}
