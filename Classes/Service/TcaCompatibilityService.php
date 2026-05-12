<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\DataHandling\TableColumnType;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\Exception\UndefinedFieldException;
use TYPO3\CMS\Core\Schema\Exception\UndefinedSchemaException;
use TYPO3\CMS\Core\Schema\Field\FieldTranslationBehaviour;
use TYPO3\CMS\Core\Schema\Field\TextFieldType;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TcaCompatibilityService implements SingletonInterface
{
    private readonly ?TcaSchemaFactory $tcaSchemaFactory;

    public function __construct(Typo3Version $typo3Version)
    {
        $this->tcaSchemaFactory = ($typo3Version->getMajorVersion() >= 13 && class_exists(TcaSchemaFactory::class))
            ? GeneralUtility::makeInstance(TcaSchemaFactory::class)
            : null;
    }

    public function hasTable(string $table): bool
    {
        if (null !== $this->tcaSchemaFactory) {
            return $this->tcaSchemaFactory->has($table);
        }

        return isset($GLOBALS['TCA'][$table]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UndefinedSchemaException
     */
    public function getRawConfiguration(string $table): array
    {
        if (null !== $this->tcaSchemaFactory) {
            return $this->tcaSchemaFactory->get($table)->getRawConfiguration();
        }

        return $GLOBALS['TCA'][$table]['ctrl'] ?? [];
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function getTitle(string $table): string
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);

            return $schema->getTitle(fn (string $v) => $v) ?: $table;
        }

        return $GLOBALS['TCA'][$table]['ctrl']['title'] ?? $table;
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function hasField(string $table, string $field): bool
    {
        if (null !== $this->tcaSchemaFactory) {
            return $this->tcaSchemaFactory->has($table)
                && $this->tcaSchemaFactory->get($table)->hasField($field);
        }

        return isset($GLOBALS['TCA'][$table]['columns'][$field]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UndefinedFieldException
     * @throws UndefinedSchemaException
     */
    public function getFieldConfiguration(string $table, string $field): array
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);
            if ($schema->hasField($field)) {
                return $schema->getField($field)->getConfiguration();
            }

            return [];
        }

        return $GLOBALS['TCA'][$table]['columns'][$field]['config'] ?? [];
    }

    /**
     * @throws UndefinedSchemaException
     * @throws UndefinedFieldException
     */
    public function getFieldLabel(string $table, string $field): string
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);
            if ($schema->hasField($field)) {
                return $schema->getField($field)->getLabel() ?: $field;
            }

            return $field;
        }

        return $GLOBALS['TCA'][$table]['columns'][$field]['label'] ?? $field;
    }

    /**
     * @return list<string>
     *
     * @throws UndefinedSchemaException
     */
    public function getFieldNames(string $table): array
    {
        if (null !== $this->tcaSchemaFactory) {
            $names = [];
            foreach ($this->tcaSchemaFactory->get($table)->getFields() as $fieldObj) {
                $names[] = $fieldObj->getName();
            }
            sort($names);

            return $names;
        }

        /** @var list<string> $names */
        $names = array_keys($GLOBALS['TCA'][$table]['columns'] ?? []);
        sort($names);

        return $names;
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function getLabelField(string $table): string
    {
        if (null !== $this->tcaSchemaFactory) {
            return $this->tcaSchemaFactory->get($table)->getRawConfiguration()['label'] ?? 'uid';
        }

        return $GLOBALS['TCA'][$table]['ctrl']['label'] ?? 'uid';
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function getDeleteField(string $table): string
    {
        if (null !== $this->tcaSchemaFactory) {
            return $this->tcaSchemaFactory->get($table)->getRawConfiguration()['delete'] ?? '';
        }

        return $GLOBALS['TCA'][$table]['ctrl']['delete'] ?? '';
    }

    /**
     * True if the table declares a soft-delete column in its TCA ctrl.
     *
     * @throws UndefinedSchemaException
     */
    public function hasSoftDelete(string $table): bool
    {
        return '' !== $this->getDeleteField($table);
    }

    /**
     * True if the table is rootLevel=1 (root-only) or rootLevel=-1 (root + pages).
     *
     * @throws UndefinedSchemaException
     */
    public function isRootLevel(string $table): bool
    {
        $rootLevel = null !== $this->tcaSchemaFactory
            ? ($this->tcaSchemaFactory->get($table)->getRawConfiguration()['rootLevel'] ?? 0)
            : ($GLOBALS['TCA'][$table]['ctrl']['rootLevel'] ?? 0);

        return 1 === (int) $rootLevel || -1 === (int) $rootLevel;
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function getSortField(string $table): string
    {
        $config = null !== $this->tcaSchemaFactory
            ? $this->tcaSchemaFactory->get($table)->getRawConfiguration()
            : ($GLOBALS['TCA'][$table]['ctrl'] ?? []);

        $sortby = (string) ($config['sortby'] ?? '');
        if ('' !== $sortby) {
            return $sortby;
        }

        // sortby may be set to '' (e.g. tx_news_domain_model_news when manual sorting is off);
        // fall back to default_sortby's first column, then to 'uid'.
        $defaultSortby = (string) ($config['default_sortby'] ?? '');
        if ('' !== $defaultSortby) {
            $first = trim(explode(',', $defaultSortby)[0]);
            $first = preg_split('/\s+/', $first)[0] ?? '';
            if ('' !== $first) {
                return $first;
            }
        }

        return 'uid';
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function isLanguageAware(string $table): bool
    {
        if (null !== $this->tcaSchemaFactory) {
            return (bool) $this->tcaSchemaFactory->get($table)->isLanguageAware();
        }

        return !empty($GLOBALS['TCA'][$table]['ctrl']['languageField']);
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function getLanguageFieldName(string $table): ?string
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);
            if (!$schema->isLanguageAware()) {
                return null;
            }

            return $schema->getCapability(TcaSchemaCapability::Language)
                ->getLanguageField()->getName()
                ;
        }

        return $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? null;
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function getTranslationOriginPointerFieldName(string $table): ?string
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);
            if (!$schema->isLanguageAware()) {
                return null;
            }

            return $schema->getCapability(TcaSchemaCapability::Language)
                ->getTranslationOriginPointerField()->getName()
                ;
        }

        return $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? null;
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function getTranslationSourceFieldName(string $table): ?string
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);
            if (!$schema->isLanguageAware()) {
                return null;
            }
            $capability = $schema->getCapability(TcaSchemaCapability::Language);
            if (!$capability->hasTranslationSourceField()) {
                return null;
            }
            $sourceField = $capability->getTranslationSourceField();

            return null !== $sourceField ? $sourceField->getName() : null;
        }

        $name = $GLOBALS['TCA'][$table]['ctrl']['translationSource'] ?? null;

        return is_string($name) && '' !== $name ? $name : null;
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function getSubSchemaDivisorFieldName(string $table): ?string
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);
            if (!$schema->supportsSubSchema()) {
                return null;
            }

            return $schema->getSubSchemaTypeInformation()->getFieldName();
        }

        $type = $GLOBALS['TCA'][$table]['ctrl']['type'] ?? null;
        if (!is_string($type) || '' === $type) {
            return null;
        }
        $name = explode(':', $type, 2)[0];

        return '' !== $name ? $name : null;
    }

    /**
     * @return list<string>
     *
     * @throws UndefinedSchemaException
     */
    public function getPrefixLanguageTitleFields(string $table): array
    {
        $names = [];
        if (null !== $this->tcaSchemaFactory) {
            foreach ($this->tcaSchemaFactory->get($table)->getFields() as $field) {
                if (FieldTranslationBehaviour::PrefixLanguageTitle === $field->getTranslationBehaviour()
                    && $field->isType(TableColumnType::TEXT, TableColumnType::INPUT, TableColumnType::EMAIL, TableColumnType::LINK)
                ) {
                    $names[] = $field->getName();
                }
            }

            return $names;
        }

        $textTypes = ['text', 'input', 'email', 'link'];
        foreach ($GLOBALS['TCA'][$table]['columns'] ?? [] as $name => $column) {
            $l10nMode = $column['l10n_mode'] ?? '';
            $type = $column['config']['type'] ?? '';
            if ('prefixLangTitle' === $l10nMode && in_array($type, $textTypes, true)) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     *
     * @throws UndefinedSchemaException
     */
    public function getMMFieldsNeedingZeroOverride(string $table): array
    {
        $names = [];
        if (null !== $this->tcaSchemaFactory) {
            foreach ($this->tcaSchemaFactory->get($table)->getFields() as $field) {
                $config = $field->getConfiguration();
                if (($config['MM'] ?? false)
                    && (!empty($config['MM_oppositeUsage']) || !isset($config['MM_opposite_field']))
                ) {
                    $names[] = $field->getName();
                }
            }

            return $names;
        }

        foreach ($GLOBALS['TCA'][$table]['columns'] ?? [] as $name => $column) {
            $config = $column['config'] ?? [];
            if (($config['MM'] ?? false)
                && (!empty($config['MM_oppositeUsage']) || !isset($config['MM_opposite_field']))
            ) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function hasCapability(string $table, TcaSchemaCapability $capability): bool
    {
        if (null !== $this->tcaSchemaFactory) {
            return $this->tcaSchemaFactory->get($table)->hasCapability($capability);
        }

        return match ($capability) {
            TcaSchemaCapability::SoftDelete => !empty($GLOBALS['TCA'][$table]['ctrl']['delete']),
            TcaSchemaCapability::Language => !empty($GLOBALS['TCA'][$table]['ctrl']['languageField']),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypes(string $table): array
    {
        return $GLOBALS['TCA'][$table]['types'] ?? [];
    }

    /**
     * @throws UndefinedSchemaException
     * @throws UndefinedFieldException
     */
    public function isRichTextField(string $table, string $field): bool
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);
            if (!$schema->hasField($field)) {
                return false;
            }
            $fieldType = $schema->getField($field);

            return $fieldType instanceof TextFieldType
                && $fieldType->isRichText();
        }

        $config = $GLOBALS['TCA'][$table]['columns'][$field]['config'] ?? [];

        return 'text' === ($config['type'] ?? '') && !empty($config['enableRichtext']);
    }

    /**
     * @throws UndefinedSchemaException
     */
    public function hasSubSchema(string $table, string $type): bool
    {
        if (null !== $this->tcaSchemaFactory) {
            return $this->tcaSchemaFactory->has($table)
                && $this->tcaSchemaFactory->get($table)->hasSubSchema($type);
        }

        return isset($GLOBALS['TCA'][$table]['types'][$type]);
    }

    /**
     * Field names visible for a record type. When $typeKey is null, returns all base fields.
     *
     * @return list<string>
     *
     * @throws UndefinedSchemaException
     */
    public function getFieldNamesForType(string $table, ?string $typeKey): array
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);
            if (null !== $typeKey && $schema->hasSubSchema($typeKey)) {
                $schema = $schema->getSubSchema($typeKey);
            }
            $names = [];
            foreach ($schema->getFields() as $field) {
                $names[] = $field->getName();
            }

            return $names;
        }

        if (null !== $typeKey && isset($GLOBALS['TCA'][$table]['types'][$typeKey]['showitem'])) {
            $showitem = (string) $GLOBALS['TCA'][$table]['types'][$typeKey]['showitem'];
            $names = $this->resolveShowitemFieldNames($table, $showitem);

            return array_values(array_unique($names));
        }

        /** @var list<string> $names */
        $names = array_keys($GLOBALS['TCA'][$table]['columns'] ?? []);

        return $names;
    }

    /**
     * Expand a TCA `showitem` string to its real column names, recursively
     * resolving `--palette--;<label>;<paletteName>` entries against
     * `$GLOBALS['TCA'][$table]['palettes']`.
     *
     * @return list<string>
     */
    private function resolveShowitemFieldNames(string $table, string $showitem, int $depth = 0): array
    {
        if ($depth > 5) {
            return [];
        }
        $names = [];
        foreach (explode(',', $showitem) as $entry) {
            $entry = trim($entry);
            if ('' === $entry) {
                continue;
            }
            if (str_starts_with($entry, '--palette--')) {
                $paletteName = trim(explode(';', $entry, 3)[2] ?? '');
                $paletteShowitem = $GLOBALS['TCA'][$table]['palettes'][$paletteName]['showitem'] ?? null;
                if (is_string($paletteShowitem) && '' !== $paletteShowitem) {
                    foreach ($this->resolveShowitemFieldNames($table, $paletteShowitem, $depth + 1) as $name) {
                        $names[] = $name;
                    }
                }

                continue;
            }
            if (str_starts_with($entry, '--')) {
                continue;
            }
            $name = trim(explode(';', $entry, 2)[0]);
            if ('' === $name) {
                continue;
            }
            if (isset($GLOBALS['TCA'][$table]['columns'][$name])) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Field config merged with the type's columnsOverrides (if any).
     *
     * @return array<string, mixed>
     *
     * @throws UndefinedSchemaException
     */
    public function getEffectiveFieldConfiguration(string $table, ?string $typeKey, string $fieldName): array
    {
        if (null !== $this->tcaSchemaFactory) {
            $schema = $this->tcaSchemaFactory->get($table);
            if (null !== $typeKey && $schema->hasSubSchema($typeKey)) {
                $schema = $schema->getSubSchema($typeKey);
            }
            if ($schema->hasField($fieldName)) {
                return $schema->getField($fieldName)->getConfiguration();
            }

            return [];
        }

        $config = $GLOBALS['TCA'][$table]['columns'][$fieldName]['config'] ?? [];
        if (null !== $typeKey) {
            $override = $GLOBALS['TCA'][$table]['types'][$typeKey]['columnsOverrides'][$fieldName]['config'] ?? null;
            if (is_array($override)) {
                $config = array_replace($config, $override);
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function isFieldRequired(array $config): bool
    {
        if (!empty($config['required'])) {
            return true;
        }

        $eval = $config['eval'] ?? '';
        if (is_string($eval) && '' !== $eval) {
            return in_array('required', array_map('trim', explode(',', $eval)), true);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function isRichTextFieldConfig(array $config): bool
    {
        return 'text' === ($config['type'] ?? '') && !empty($config['enableRichtext']);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function isRelationalFieldConfig(array $config): bool
    {
        $type = (string) ($config['type'] ?? '');
        if (in_array($type, ['inline', 'group', 'file', 'category'], true)) {
            return true;
        }
        if ('select' === $type) {
            return !empty($config['foreign_table']) || !empty($config['MM']);
        }

        return false;
    }
}
