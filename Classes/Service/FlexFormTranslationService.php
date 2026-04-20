<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\SingletonInterface;

class FlexFormTranslationService implements SingletonInterface
{
    /** @var list<string> */
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
        'user',
    ];

    public function __construct(
        protected readonly FlexFormTools $flexFormTools
    ) {}

    /**
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $translateFields
     */
    public function convertFlexFormToTranslateFields(array $formData, array &$translateFields, string $fieldName = 'pi_flexform'): void
    {
        $flexForm = $formData['databaseRow'][$fieldName] ?? '';
        if (empty($flexForm)) {
            return;
        }
        $newStructure = $formData['processedTca']['columns'][$fieldName]['config']['ds'];
        $flexFormData = $flexForm['data'] ?? [];
        foreach ($flexFormData as $sKey => $sheetDef) {
            if (isset($newStructure['sheets'][$sKey]) && is_array($newStructure['sheets'][$sKey]) && is_array($sheetDef)) {
                foreach ($sheetDef as $lKey => $lData) {
                    $this->checkValue_flex_procInData_travDS(
                        $flexFormData[$sKey][$lKey],
                        $newStructure['sheets'][$sKey]['ROOT']['el'] ?? null,
                        $sKey.'/'.$lKey.'/'
                    );
                }
            }
        }
        $this->removeEmptyArraysRecursively($flexFormData);
        if (!empty($flexFormData)) {
            $translateFields[$fieldName] = [
                'data' => $flexFormData,
            ];
        }
    }

    /**
     * @param array<string, mixed> $DSelements
     * @param array<string, mixed> $dataValues
     */
    protected function checkValue_flex_procInData_travDS(array &$dataValues, array $DSelements, string $structurePath): void
    {
        // For each DS element:
        foreach ($DSelements as $key => $dsConf) {
            // Array/Section:
            if (isset($DSelements[$key]['type']) && 'array' === $DSelements[$key]['type']) {
                if (!is_array($dataValues[$key]['el'] ?? null)) {
                    continue;
                }

                if ($DSelements[$key]['section']) {
                    foreach ($dataValues[$key]['el'] as $ik => $el) {
                        if (!is_array($el)) {
                            continue;
                        }

                        $theKey = key($el);
                        if (!is_array($dataValues[$key]['el'][$ik][$theKey]['el'] ?? false)) {
                            continue;
                        }

                        $this->checkValue_flex_procInData_travDS(
                            $dataValues[$key]['el'][$ik][$theKey]['el'],
                            $DSelements[$key]['el'][$theKey]['el'] ?? [],
                            $structurePath.$key.'/el/'.$ik.'/'.$theKey.'/el/',
                        );
                    }
                } else {
                    if (!isset($dataValues[$key]['el'])) {
                        $dataValues[$key]['el'] = [];
                    }
                    $this->checkValue_flex_procInData_travDS($dataValues[$key]['el'], $DSelements[$key]['el'], $structurePath.$key.'/el/');
                }
            } else {
                $fieldConfiguration = $dsConf['config'] ?? null;
                // init with value from config for passthrough fields
                if (!empty($fieldConfiguration['type']) && 'passthrough' === $fieldConfiguration['type']) {
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

    /**
     * @param array<string, mixed> $flexFormData
     */
    protected function removeEmptyArraysRecursively(array &$flexFormData): void
    {
        foreach ($flexFormData as $key => $value) {
            if (is_array($value)) {
                $this->removeEmptyArraysRecursively($flexFormData[$key]);
                if (empty($flexFormData[$key])) {
                    unset($flexFormData[$key]);
                }
            } elseif ('' === $value) {
                unset($flexFormData[$key]);
            }
        }
    }
}
