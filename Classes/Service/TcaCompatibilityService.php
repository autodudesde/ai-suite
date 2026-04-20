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
     * @throws UndefinedSchemaException
     */
    public function getSortField(string $table): string
    {
        if (null !== $this->tcaSchemaFactory) {
            return $this->tcaSchemaFactory->get($table)->getRawConfiguration()['sortby'] ?? 'uid';
        }

        return $GLOBALS['TCA'][$table]['ctrl']['sortby'] ?? 'uid';
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
}
