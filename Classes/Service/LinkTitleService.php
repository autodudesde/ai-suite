<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LinkTitleService
{
    private const EXPLICIT_URL = '~^(t3://|[a-z][a-z0-9+.-]*://|//|mailto:|tel:|file:|record:|/|#|\d+$)~i';
    private const WRITABLE_TARGET = '~^(t3://|https?://|//|mailto:|tel:|file:|record:|/|#|\d+$)~i';

    public function __construct(
        private readonly TypoLinkCodecService $typoLinkCodecService,
        private readonly TcaCompatibilityService $tcaCompatibilityService,
        private readonly LoggerInterface $logger,
    ) {}

    public function extractTitle(string $typolink): string
    {
        if (!$this->isExplicitLink($typolink)) {
            return '';
        }

        return (string) ($this->decode($typolink)['title'] ?? '');
    }

    public function applyTitle(string $storedTypolink, string $title): ?string
    {
        if (!$this->isExplicitLink($storedTypolink)) {
            return null;
        }

        $parts = $this->decode($storedTypolink);
        $parts['title'] = trim($title);

        return (string) $this->typoLinkCodecService->encode($parts);
    }

    public function isExplicitLink(string $value): bool
    {
        $url = (string) ($this->decode($value)['url'] ?? '');

        return '' !== $url && 1 !== preg_match('/\s/', $url) && 1 === preg_match(self::EXPLICIT_URL, $url);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function restoreLinks(string $table, array $fields, array $row): array
    {
        $columnConfigs = $this->tcaCompatibilityService->getColumnConfigs($table);
        $parentRow = null;

        foreach ($fields as $field => $value) {
            $field = (string) $field;
            $type = $columnConfigs[$field]['type'] ?? '';

            if ('link' === $type && \is_string($value)) {
                $parentRow ??= $this->parentRow($table, $row);
                $restored = $this->resolveValue($value, [(string) ($row[$field] ?? ''), (string) ($parentRow[$field] ?? '')]);
                if (null === $restored) {
                    unset($fields[$field]);
                } else {
                    $fields[$field] = $restored;
                }

                continue;
            }

            if ('flex' === $type && \is_array($value) && \is_array($value['data'] ?? null)) {
                $parentRow ??= $this->parentRow($table, $row);
                $restored = $this->restoreFlexLinks($table, $field, $value, $fields + $row, $row, $parentRow);
                if (null === $restored) {
                    unset($fields[$field]);
                } else {
                    $fields[$field] = $restored;
                }
            }
        }

        return $fields;
    }

    /**
     * @param list<string> $bases
     */
    private function resolveValue(string $value, array $bases): ?string
    {
        if ('' === trim($value)) {
            return $value;
        }
        if ($this->isExplicitLink($value)) {
            if (1 === preg_match(self::WRITABLE_TARGET, (string) ($this->decode($value)['url'] ?? ''))) {
                return $value;
            }
            $this->logger->warning('Refused a link target with a scheme that is not written', ['value' => $value]);

            return null;
        }

        foreach ($bases as $base) {
            $restored = $this->applyTitle($base, $value);
            if (null !== $restored) {
                return $restored;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $dsRow
     * @param array<string, mixed> $row
     * @param array<string, mixed> $parentRow
     *
     * @return null|array<string, mixed>
     */
    private function restoreFlexLinks(string $table, string $field, array $value, array $dsRow, array $row, array $parentRow): ?array
    {
        try {
            $fieldTca = $this->tcaCompatibilityService->getFieldTca($table, $field);
            $dataStructure = $this->tcaCompatibilityService->resolveFlexFormDataStructure($fieldTca, $table, $field, $dsRow);
        } catch (\Throwable $e) {
            $this->logger->warning('LinkTitleService: FlexForm data structure unresolvable, translated values dropped', [
                'table' => $table,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $baseData = $this->flexData($row[$field] ?? null);
        $parentData = $this->flexData($parentRow[$field] ?? null);

        foreach ($value['data'] as $sheet => $languages) {
            $elements = $dataStructure['sheets'][$sheet]['ROOT']['el'] ?? null;
            if (!\is_array($languages) || !\is_array($elements)) {
                continue;
            }
            foreach ($languages as $languageKey => $translated) {
                if (!\is_array($translated)) {
                    continue;
                }
                $value['data'][$sheet][$languageKey] = $this->walkFlex(
                    $translated,
                    $elements,
                    (array) ($baseData[$sheet][$languageKey] ?? $baseData[$sheet]['lDEF'] ?? []),
                    (array) ($parentData[$sheet][$languageKey] ?? $parentData[$sheet]['lDEF'] ?? []),
                );
            }
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $translated
     * @param array<array-key, mixed> $elements
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $parent
     *
     * @return array<array-key, mixed>
     */
    private function walkFlex(array $translated, array $elements, array $base, array $parent): array
    {
        foreach ($translated as $key => $node) {
            $definition = $elements[$key] ?? null;
            if (!\is_array($definition) || !\is_array($node)) {
                continue;
            }

            if (!empty($definition['section']) && \is_array($node['el'] ?? null)) {
                foreach ($node['el'] as $index => $container) {
                    if (!\is_array($container)) {
                        continue;
                    }
                    foreach ($container as $containerKey => $containerNode) {
                        if (!\is_array($containerNode) || !\is_array($containerNode['el'] ?? null)) {
                            continue;
                        }
                        $translated[$key]['el'][$index][$containerKey]['el'] = $this->walkFlex(
                            $containerNode['el'],
                            (array) ($definition['el'][$containerKey]['el'] ?? []),
                            (array) ($base[$key]['el'][$index][$containerKey]['el'] ?? []),
                            (array) ($parent[$key]['el'][$index][$containerKey]['el'] ?? []),
                        );
                    }
                }

                continue;
            }

            if ('array' === ($definition['type'] ?? '') && \is_array($definition['el'] ?? null) && \is_array($node['el'] ?? null)) {
                $translated[$key]['el'] = $this->walkFlex(
                    $node['el'],
                    $definition['el'],
                    (array) ($base[$key]['el'] ?? []),
                    (array) ($parent[$key]['el'] ?? []),
                );

                continue;
            }

            $config = $definition['config'] ?? $definition['TCEforms']['config'] ?? [];
            if ('link' !== ($config['type'] ?? '') || !\is_string($node['vDEF'] ?? null)) {
                continue;
            }

            $restored = $this->resolveValue($node['vDEF'], [(string) ($base[$key]['vDEF'] ?? ''), (string) ($parent[$key]['vDEF'] ?? '')]);
            if (null === $restored) {
                unset($translated[$key]);
            } else {
                $translated[$key]['vDEF'] = $restored;
            }
        }

        return $translated;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function flexData(mixed $stored): array
    {
        if (\is_array($stored)) {
            return (array) ($stored['data'] ?? []);
        }
        if (!\is_string($stored) || '' === trim($stored)) {
            return [];
        }

        $parsed = GeneralUtility::xml2array($stored);

        return \is_array($parsed) ? (array) ($parsed['data'] ?? []) : [];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function parentRow(string $table, array $row): array
    {
        $pointerField = $this->tcaCompatibilityService->getTranslationOriginPointerFieldName($table);
        $parentUid = null !== $pointerField ? (int) ($row[$pointerField] ?? 0) : 0;
        if ($parentUid <= 0) {
            return [];
        }

        return BackendUtility::getRecord($table, $parentUid) ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function decode(string $typolink): array
    {
        if ('' === trim($typolink)) {
            return [];
        }

        return (array) $this->typoLinkCodecService->decode($typolink);
    }
}
