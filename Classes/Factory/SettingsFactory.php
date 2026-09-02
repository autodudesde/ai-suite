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

namespace AutoDudes\AiSuite\Factory;

use AutoDudes\AiSuite\Service\BackendUserService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class SettingsFactory
{
    /** @var array<string, mixed> */
    protected array $extConf;

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct(
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly BackendUserService $backendUserService,
        protected readonly LoggerInterface $logger,
    ) {
        $this->extConf = $this->extensionConfiguration->get('ai_suite');
    }

    /**
     * @return array<string, mixed>
     */
    public function mergeExtConfAndUserGroupSettings(): array
    {
        try {
            if ($this->backendUserService->getBackendUser()?->isAdmin() ?? false) {
                return $this->extConf;
            }
            foreach ($this->extConf as $key => $value) {
                $this->extConf[$key] = $this->backendUserService->checkGroupSpecificInputs($key) ?: ($value ?? '');
            }

            return $this->extConf;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->extConf;
        }
    }

    /**
     * @return array<string, array{type: string, category: string, categoryLabel: string, subcategory: string, subcategoryLabel: string, default: string, label: string, options?: array<string, string>}>
     */
    public function parseExtConfTemplate(): array
    {
        $extPath = ExtensionManagementUtility::extPath('ai_suite');
        $templateFile = $extPath.'ext_conf_template.txt';
        if (!file_exists($templateFile)) {
            return [];
        }

        return $this->parseTemplate((string) file_get_contents($templateFile));
    }

    /**
     * @return array<string, array{type: string, category: string, categoryLabel: string, subcategory: string, subcategoryLabel: string, default: string, label: string, options?: array<string, string>}>
     */
    public function parseTemplate(string $content): array
    {
        $lines = explode("\n", $content);
        $definitions = [];
        $categoryLabels = [];
        $subcategoryLabels = [];
        $currentMeta = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            if (str_starts_with($line, '#')) {
                $commentContent = ltrim($line, '# ');
                $declaration = $this->parseDeclarationLine($commentContent);
                if (null !== $declaration) {
                    [$scope, $key, $label] = $declaration;
                    if ('customcategory' === $scope) {
                        $categoryLabels[$key] = $label;
                    } else {
                        $subcategoryLabels[$key] = $label;
                    }

                    continue;
                }
                $currentMeta = $this->parseCommentLine($commentContent);

                continue;
            }

            if (null !== $currentMeta && str_contains($line, '=')) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $category = $currentMeta['category'];
                $subcategory = $currentMeta['subcategory'];
                $definitions[$key] = [
                    'type' => $currentMeta['type'],
                    'category' => $category,
                    'categoryLabel' => $categoryLabels[$category] ?? $category,
                    'subcategory' => $subcategory,
                    'subcategoryLabel' => $subcategoryLabels[$subcategory] ?? $subcategory,
                    'default' => $value,
                    'label' => $currentMeta['label'],
                ];
                if (!empty($currentMeta['options'])) {
                    $definitions[$key]['options'] = $currentMeta['options'];
                }
                $currentMeta = null;
            }
        }

        return $definitions;
    }

    /**
     * @return null|array{0: string, 1: string, 2: string}
     */
    private function parseDeclarationLine(string $commentContent): ?array
    {
        $parts = explode('=', $commentContent, 3);
        if (3 !== count($parts)) {
            return null;
        }
        $scope = strtolower(trim($parts[0]));
        if ('customcategory' !== $scope && 'customsubcategory' !== $scope) {
            return null;
        }
        $key = strtolower(trim($parts[1]));
        $label = trim($parts[2]);
        if ('' === $key || '' === $label) {
            return null;
        }

        return [$scope, $key, $label];
    }

    /**
     * @return array{category: string, subcategory: string, type: string, label: string, options?: array<string, string>}
     */
    private function parseCommentLine(string $commentContent): array
    {
        $parts = array_map('trim', explode(';', $commentContent));
        $meta = ['category' => '', 'subcategory' => '', 'type' => 'string', 'label' => ''];

        foreach ($parts as $part) {
            if (str_starts_with($part, 'cat=')) {
                $catValue = explode('/', strtolower(substr($part, 4)));
                $meta['category'] = trim($catValue[0]);
                $meta['subcategory'] = trim($catValue[1] ?? '');
            } elseif (str_starts_with($part, 'type=')) {
                $typeValue = substr($part, 5);
                $meta = array_merge($meta, $this->parseType($typeValue));
            } elseif (str_starts_with($part, 'label=')) {
                $meta['label'] = substr($part, 6);
            }
        }

        return $meta;
    }

    /**
     * @return array{type: string, options?: array<string, string>}
     */
    private function parseType(string $typeValue): array
    {
        if ('boolean' === $typeValue) {
            return ['type' => 'boolean'];
        }

        if (str_starts_with($typeValue, 'int')) {
            return ['type' => 'int'];
        }

        if (str_starts_with($typeValue, 'options[')) {
            $optionsString = substr($typeValue, 8, -1);
            $options = [];
            foreach (explode(',', $optionsString) as $optionPair) {
                $optionParts = explode('=', $optionPair, 2);
                if (2 === count($optionParts)) {
                    $options[trim($optionParts[1])] = trim($optionParts[0]);
                }
            }

            return ['type' => 'select', 'options' => $options];
        }

        return ['type' => 'string'];
    }
}
