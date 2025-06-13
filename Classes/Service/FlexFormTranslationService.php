<?php

namespace AutoDudes\AiSuite\Service;


use TYPO3\CMS\Backend\Form\FormDataProvider\TcaFlexPrepare;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Migrations\TcaMigration;
use TYPO3\CMS\Core\Preparations\TcaPreparation;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class FlexFormTranslationService implements SingletonInterface
{
    protected array $ignoredFlexformFieldTypes = [
        'category',
        'check',
        'color',
        'datetime',
        'email',
        'flex',
        'inline',
        'file',
        'language',
        'link',
        'number',
        'password',
        'radio',
        'slug',
        'group',
        'folder',
        'select',
        'json',
        'uuid',
        'passthrough',
        'imageManipulation',
        'user'
    ];

    protected array $ignoreFlexformInputRenderTypes = [
        'inputLink',
    ];

    protected TcaFlexPrepare $flexFormTools;

    public function __construct(
        TcaFlexPrepare $flexFormTools
    ) {
        $this->flexFormTools = $flexFormTools;
    }

    public function convertFlexFormToTranslateFields(array $formData, array &$translateFields): void
    {
        $flexForm = $formData['databaseRow']['pi_flexform'] ?? '';
        if (empty($flexForm)) {
            return;
        }
        $originalFlexFormStructure = $formData["processedTca"]["columns"]["pi_flexform"]["config"]["ds"];
        $newStructure = $this->removeElementTceFormsRecursive($originalFlexFormStructure);
        $newStructure = $this->migrateFlexFormTcaRecursive($newStructure, $formData['tableName'], 'pi_flexform');

        $flexFormData = $flexForm['data'] ?? [];
        foreach ($flexFormData as $sKey => $sheetDef) {
            if (isset($newStructure['sheets'][$sKey]) && is_array($newStructure['sheets'][$sKey]) && is_array($sheetDef)) {
                foreach ($sheetDef as $lKey => $lData) {
                    $this->checkValue_flex_procInData_travDS(
                        $flexFormData[$sKey][$lKey],
                        $newStructure['sheets'][$sKey]['ROOT']['el'] ?? null,
                        $sKey . '/' . $lKey . '/'
                    );
                }
            }
        }
        $this->removeEmptyArraysRecursively($flexFormData);
        $translateFields['pi_flexform'] = [
            'data' => $flexFormData
        ];
    }

    protected function checkValue_flex_procInData_travDS(&$dataValues, $DSelements, $structurePath): void
    {
        if (!is_array($DSelements)) {
            return;
        }

        // For each DS element:
        foreach ($DSelements as $key => $dsConf) {
            // Array/Section:
            if (isset($DSelements[$key]['type']) && $DSelements[$key]['type'] === 'array') {
                if (!is_array($dataValues[$key]['el'] ?? null)) {
                    continue;
                }

                if ($DSelements[$key]['section']) {
                    foreach ($dataValues[$key]['el'] as $ik => $el) {
                        if (!is_array($el)) {
                            continue;
                        }
                        $theKey = key($el);
                        if (!is_array($dataValues[$key]['el'][$ik][$theKey]['el'])) {
                            continue;
                        }

                        $this->checkValue_flex_procInData_travDS(
                            $dataValues[$key]['el'][$ik][$theKey]['el'],
                            $DSelements[$key]['el'][$theKey]['el'] ?? [],
                            $structurePath . $key . '/el/' . $ik . '/' . $theKey . '/el/',
                        );
                    }
                } else {
                    if (!isset($dataValues[$key]['el'])) {
                        $dataValues[$key]['el'] = [];
                    }
                    $this->checkValue_flex_procInData_travDS($dataValues[$key]['el'], $DSelements[$key]['el'], $structurePath . $key . '/el/');
                }
            } else {
                // When having no specific sheets, it's "TCEforms.config", when having a sheet, it's just "config"
                $fieldConfiguration = $dsConf['TCEforms']['config'] ?? $dsConf['config'] ?? null;
                // init with value from config for passthrough fields
                if (!empty($fieldConfiguration['type']) && $fieldConfiguration['type'] === 'passthrough') {
                    if (!empty($dataValues_current[$key]['vDEF'])) {
                        // If there is existing value, keep it
                        $dataValues[$key]['vDEF'] = $dataValues_current[$key]['vDEF'];
                    } elseif (!empty($fieldConfiguration['default'])) {
                        // If is new record and a default is specified for field, use it.
                        $dataValues[$key]['vDEF'] = $fieldConfiguration['default'];
                    }
                }
                if (!is_array($fieldConfiguration) || !isset($dataValues[$key]) || !is_array($dataValues[$key])) {
                    continue;
                }
                $type = $fieldConfiguration['type'] ?? '';
                $renderType = $fieldConfiguration['renderType'] ?? '';
                if (in_array($type, $this->ignoredFlexformFieldTypes) || ($type === 'input' && in_array($renderType, $this->ignoreFlexformInputRenderTypes))) {
                    unset($dataValues[$key]);
                }
            }
        }
    }

    protected function removeEmptyArraysRecursively(array &$flexFormData): void
    {
        foreach ($flexFormData as $key => $value) {
            if (is_array($value)) {
                $this->removeEmptyArraysRecursively($flexFormData[$key]);
                if (empty($flexFormData[$key])) {
                    unset($flexFormData[$key]);
                }
            }
        }
    }

    protected function removeElementTceFormsRecursive(array $structure): array {
        $newStructure = [];
        foreach ($structure as $key => $value) {
            if ($key === 'ROOT' && is_array($value) && isset($value['TCEforms'])) {
                trigger_error(
                    'The tag "<TCEforms>" should not be set under the FlexForm definition "<ROOT>" anymore. It should be omitted while the underlying configuration ascends one level up. This compatibility layer will be removed in TYPO3 v13.',
                    E_USER_DEPRECATED
                );
                $value = array_merge($value, $value['TCEforms']);
                unset($value['TCEforms']);
            }
            if ($key === 'el' && is_array($value)) {
                $newSubStructure = [];
                foreach ($value as $subKey => $subValue) {
                    if (is_array($subValue) && count($subValue) === 1 && isset($subValue['TCEforms'])) {
                        trigger_error(
                            'The tag "<TCEforms>" was found in a FlexForm definition for the field "<' . $subKey . '>". It should be omitted while the underlying configuration ascends one level up. This compatibility layer will be removed in TYPO3 v13.',
                            E_USER_DEPRECATED
                        );
                        $newSubStructure[$subKey] = $subValue['TCEforms'];
                    } else {
                        $newSubStructure[$subKey] = $subValue;
                    }
                }
                $value = $newSubStructure;
            }
            if (is_array($value)) {
                $value = $this->removeElementTceFormsRecursive($value);
            }
            $newStructure[$key] = $value;
        }
        return $newStructure;
    }

    protected function migrateFlexformTcaRecursive($structure, $table, $fieldName): array
    {
        $newStructure = [];
        foreach ($structure as $key => $value) {
            if ($key === 'el' && is_array($value)) {
                $newSubStructure = [];
                $tcaMigration = GeneralUtility::makeInstance(TcaMigration::class);
                $tcaPreparation = GeneralUtility::makeInstance(TcaPreparation::class);
                foreach ($value as $subKey => $subValue) {
                    // On-the-fly migration for flex form "TCA". Call the TcaMigration and log any deprecations.
                    $dummyTca = [
                        'dummyTable' => [
                            'columns' => [
                                'dummyField' => $subValue,
                            ],
                        ],
                    ];
                    $migratedTca = $tcaMigration->migrate($dummyTca);
                    $messages = $tcaMigration->getMessages();
                    if (!empty($messages)) {
                        $context = 'FormEngine did an on-the-fly migration of a flex form data structure. This is deprecated and will be removed.'
                            . ' Merge the following changes into the flex form definition of table "' . $table . '"" in field "' . $fieldName . '"":';
                        array_unshift($messages, $context);
                        trigger_error(implode(LF, $messages), E_USER_DEPRECATED);
                    }
                    $preparedTca = $tcaPreparation->prepare($migratedTca);
                    $newSubStructure[$subKey] = $preparedTca['dummyTable']['columns']['dummyField'];
                }
                $value = $newSubStructure;
            }
            if (is_array($value)) {
                $value = $this->migrateFlexformTcaRecursive($value, $table, $fieldName);
            }
            $newStructure[$key] = $value;
        }
        return $newStructure;
    }
}
