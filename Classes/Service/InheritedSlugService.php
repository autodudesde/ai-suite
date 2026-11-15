<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;

class InheritedSlugService
{
    public function __construct(
        private readonly TcaCompatibilityService $tcaCompatibilityService,
    ) {}

    /**
     * @param array<array-key, mixed> $writtenFields
     *
     * @return list<string>
     */
    public function inheritedSlugFields(string $table, int $targetUid, array $writtenFields): array
    {
        $slugConfigs = $this->tcaCompatibilityService->getTranslatableSlugFieldConfigs($table);
        if ([] === $slugConfigs || $targetUid <= 0) {
            return [];
        }

        $writtenFieldNames = array_map('strval', array_keys($writtenFields));
        $candidates = [];
        foreach ($slugConfigs as $slugField => $config) {
            if (!in_array($slugField, $writtenFieldNames, true)
                && [] !== array_intersect($this->generatorFields($config), $writtenFieldNames)
            ) {
                $candidates[] = $slugField;
            }
        }
        if ([] === $candidates) {
            return [];
        }

        $languageField = $this->tcaCompatibilityService->getLanguageFieldName($table);
        $pointerField = $this->tcaCompatibilityService->getTranslationOriginPointerFieldName($table);
        $record = BackendUtility::getRecordWSOL($table, $targetUid);
        if (null === $languageField || null === $pointerField || !is_array($record) || (int) ($record[$languageField] ?? 0) <= 0) {
            return [];
        }

        $origin = BackendUtility::getRecordWSOL($table, (int) ($record[$pointerField] ?? 0));
        if (!is_array($origin)) {
            return [];
        }

        $inherited = [];
        foreach ($candidates as $slugField) {
            $slug = (string) ($record[$slugField] ?? '');
            if ('' === $slug || ('/' !== $slug && $slug === (string) ($origin[$slugField] ?? ''))) {
                $inherited[] = $slugField;
            }
        }

        return $inherited;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private function generatorFields(array $config): array
    {
        $configured = $config['generatorOptions']['fields'] ?? [];
        $fields = [];
        if (!is_array($configured)) {
            return $fields;
        }

        array_walk_recursive($configured, static function (mixed $field) use (&$fields): void {
            if (is_string($field) && '' !== $field) {
                $fields[] = $field;
            }
        });

        return $fields;
    }
}
