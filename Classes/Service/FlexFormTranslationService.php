<?php

namespace AutoDudes\AiSuite\Service;


use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\SingletonInterface;

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

    protected FlexFormTools $flexFormTools;

    public function __construct(
        FlexFormTools $flexFormTools
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
        $newStructure = $this->flexFormTools->removeElementTceFormsRecursive($originalFlexFormStructure);
        $newStructure = $this->flexFormTools->migrateFlexFormTcaRecursive($newStructure);

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
                        // @todo: Ugly! This relies on the fact that _TOGGLE and _ACTION are *below* the business fields!
                        $theKey = key($el);
                        if (!is_array($dataValues[$key]['el'][$ik][$theKey]['el'] ?? false)) {
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
                $fieldConfiguration = $dsConf['config'] ?? null;
                // init with value from config for passthrough fields
                if (!empty($fieldConfiguration['type']) && $fieldConfiguration['type'] === 'passthrough') {
                    if (!empty($fieldConfiguration['default'])) {
                        // If is new record and a default is specified for field, use it.
                        $dataValues[$key]['vDEF'] = $fieldConfiguration['default'];
                    }
                }
                if (!is_array($fieldConfiguration) || !isset($dataValues[$key]) || !is_array($dataValues[$key])) {
                    continue;
                }
                $type = $fieldConfiguration['type'] ?? '';
                if (in_array($type, $this->ignoredFlexformFieldTypes)) {
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
}
