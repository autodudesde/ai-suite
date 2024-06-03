<?php

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Utility\ContentUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentService
{
    protected array $ignoredTcaFields = [
        'uid',
        'pid',
        'colPos',
        'sys_language_uid',
        't3ver_oid',
        't3ver_id',
        't3ver_wsid',
        't3ver_label',
        't3ver_state',
        't3ver_stage',
        't3ver_count',
        't3ver_tstamp',
        't3ver_move_id',
        't3_origuid',
        'tstamp',
        'crdate',
        'cruser_id',
        'hidden',
        'deleted',
        'starttime',
        'endtime',
        'sorting',
        'fe_group',
        'editlock',
        'lockToDomain',
        'lockToIP',
        'l10n_parent',
        'l10n_diffsource',
        'rowDescription',
    ];

    protected array $consideredTextRenderTypes = [
        'input',
        'text',
    ];

    public array $consideredImageRenderTypes = [
        'file'
    ];

    public function fetchRequestFields(ServerRequestInterface $request, array $defaultValues, string $cType, int $pid, $table): array
    {
        $formData = $this->getFormData($request, $defaultValues, $pid, $table);

        $requestFields[$table] = [
            'label' => 'General fields',
            'text' => [],
            'image' => []
        ];

        $itemList = $GLOBALS['TCA'][$table]['types'][$cType]['showitem'];
        $fieldsArray = GeneralUtility::trimExplode(',', $itemList, true);
        $this->iterateOverFieldsArray($fieldsArray, $requestFields, $formData, $pid, $table);
        return ContentUtility::cleanupRequestField($requestFields, $table);
    }

    protected function explodeSingleFieldShowItemConfiguration($field): array
    {
        $fieldArray = GeneralUtility::trimExplode(';', $field);
        if (empty($fieldArray[0])) {
            throw new \RuntimeException('Field must not be empty', 1426448465);
        }
        return [
            'fieldName' => $fieldArray[0],
            'fieldLabel' => !empty($fieldArray[1]) ? $fieldArray[1] : null,
            'paletteName' => !empty($fieldArray[2]) ? $fieldArray[2] : null,
        ];
    }

    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function createPaletteContentArray(
        string $paletteName,
        array &$requestFields,
        array $formData,
        int $pid,
        string $table
    ): void
    {
        // palette needs a palette name reference, otherwise it does not make sense to try rendering of it
        if (!empty($paletteName)) {
            $fieldsArray = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['palettes'][$paletteName]['showitem'], true);
            foreach ($fieldsArray as $fieldString) {
                $fieldArray = $this->explodeSingleFieldShowItemConfiguration($fieldString);
                $fieldName = $fieldArray['fieldName'];
                if ($fieldName === '--linebreak--') {
                    continue;
                } else {
                    $this->checkSingleField($formData, $fieldName, $requestFields, $pid, $table);
                }
            }
        }
    }

    public function checkSingleField(
        array $formData,
        string $fieldName,
        array &$requestFields,
        int $pid,
        string $table
    ): void {
        if (!is_array($formData['processedTca']['columns'][$fieldName] ?? null) || in_array($fieldName, $this->ignoredTcaFields)) {
            return;
        }
        $parameterArray = [];
        $parameterArray['fieldConf'] = $formData['processedTca']['columns'][$fieldName];

        // A couple of early returns in case the field should not be rendered
        $fieldIsExcluded = $parameterArray['fieldConf']['exclude'] ?? false;
        $fieldNotExcludable = $this->getBackendUserAuthentication()->check('non_exclude_fields', $formData['tableName'] . ':' . $fieldName);
        // $fieldExcludedFromTranslatedRecords = empty($parameterArray['fieldConf']['l10n_display']) && ($parameterArray['fieldConf']['l10n_mode'] ?? '') === 'exclude';
        // Return if BE-user has no access rights to this field,
        // if (($fieldIsExcluded && !$fieldNotExcludable) || ($isOverlay && $fieldExcludedFromTranslatedRecords) || $this->inlineFieldShouldBeSkipped()) {
        if ($fieldIsExcluded && !$fieldNotExcludable) {
            return;
        }

        $tsConfig = $formData['pageTsConfig']['TCEFORM.'][$formData['tableName'] . '.'][$fieldName . '.'] ?? [];
        $parameterArray['fieldTSConfig'] = is_array($tsConfig) ? $tsConfig : [];

        if ($parameterArray['fieldTSConfig']['disabled'] ?? false) {
            return;
        }

        // Override fieldConf by fieldTSconfig:
        $parameterArray['fieldConf']['config'] = FormEngineUtility::overrideFieldConf($parameterArray['fieldConf']['config'], $parameterArray['fieldTSConfig']);

        if($parameterArray['fieldConf']['config']['type'] === 'inline') {
            $foreignTable = $parameterArray['fieldConf']['config']['foreign_table'];
            $requestFields[$foreignTable]['label'] = $parameterArray['fieldConf']['label'];
            $requestFields[$foreignTable]['foreignField'] = $table;
            $this->fetchIrreRequestFields($formData['request'], $formData['defaultValues'], $requestFields, $foreignTable, $pid);
        }
        if (!empty($parameterArray['fieldConf']['config']['renderType'])) {
            $renderType = $parameterArray['fieldConf']['config']['renderType'];
        } else {
            // Fallback to type if no renderType is given
            $renderType = $parameterArray['fieldConf']['config']['type'];
        }
        if (in_array($renderType, $this->consideredTextRenderTypes)) {
            $requestFields[$table]['text'][$fieldName] = [
                'label' => $parameterArray['fieldConf']['label'],
                'renderType' => $renderType
            ];
        }
        if (in_array($renderType, $this->consideredImageRenderTypes)) {
            if(array_key_exists('allowed', $parameterArray['fieldConf']['config']) &&
                (str_contains($parameterArray['fieldConf']['config']['allowed'], 'jpg') || str_contains($parameterArray['fieldConf']['config']['allowed'], 'jpeg'))) {
                $requestFields[$table]['image'][$fieldName] = [
                    'label' => $parameterArray['fieldConf']['label'],
                    'renderType' => $renderType
                ];
            } else {
                $allowedFileExtensions = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
                if((str_contains($allowedFileExtensions, 'jpg') || str_contains($allowedFileExtensions, 'jpeg'))) {
                    $requestFields[$table]['image'][$fieldName] = [
                        'label' => $parameterArray['fieldConf']['label'],
                        'renderType' => $renderType
                    ];
                }
            }
        }
    }

    protected function fetchIrreRequestFields(
        ServerRequestInterface $request,
        array $defaultValues,
        array &$requestFields,
        string $table,
        int $pid
    ): void
    {
        $formData = $this->getFormData($request, $defaultValues, $pid, $table);

        $showItemKey = array_key_first($GLOBALS['TCA'][$table]['types']);
        $itemList = $GLOBALS['TCA'][$table]['types'][$showItemKey]['showitem'];
        $fieldsArray = GeneralUtility::trimExplode(',', $itemList, true);
        $this->iterateOverFieldsArray($fieldsArray, $requestFields, $formData, $pid, $table);
    }

    protected function getFormData(ServerRequestInterface $request, array $defaultValues, int $pid, string $table) {
        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class);
        $formDataCompilerInput = [
            'request' => $request,
            'tableName' => $table,
            'vanillaUid' => $pid,
            'command' => 'new',
            'returnUrl' => '',
            'defaultValues' => $defaultValues,
        ];
        return $formDataCompiler->compile($formDataCompilerInput, GeneralUtility::makeInstance(TcaDatabaseRecord::class));
    }

    protected function iterateOverFieldsArray(
        array $fieldsArray,
        array &$requestFields,
        array $formData,
        int $pid,
        string $table
    ): void
    {
        foreach ($fieldsArray as $fieldString) {
            $fieldConfiguration = $this->explodeSingleFieldShowItemConfiguration($fieldString);
            $fieldName = $fieldConfiguration['fieldName'];
            if ($fieldName === '--palette--') {
                $this->createPaletteContentArray($fieldConfiguration['paletteName'] ?? '', $requestFields, $formData, $pid, $table);
            } else {
                if (!is_array($formData['processedTca']['columns'][$fieldName] ?? null)) {
                    continue;
                }
                $this->checkSingleField($formData, $fieldName, $requestFields, $pid, $table);
            }
        }
    }

    public function checkRequestModels(array $requestFields, array $models): array
    {
        foreach ($models as $type => $model) {
            if($type === 'text') {
                $textModelRequired = false;
                // iterate over requestFields and check if at least one text field is present
                foreach ($requestFields as $fields) {
                    if(array_key_exists('text', $fields)) {
                        foreach ($fields['text'] as $data) {
                            if(array_key_exists('renderType', $data) && in_array($data['renderType'], $this->consideredTextRenderTypes)) {
                                $textModelRequired = true;
                            }
                        }
                    }
                }
                if(!$textModelRequired) {
                    unset($models['text']);
                }
            }
            if($type === 'image') {
                $imageModelRequired = false;
                // iterate over requestFields and check if at least one text field is present
                foreach ($requestFields as $fields) {
                    if(array_key_exists('image', $fields)) {
                        foreach ($fields['image'] as $data) {
                            if(array_key_exists('renderType', $data) && in_array($data['renderType'], $this->consideredImageRenderTypes)) {
                                $imageModelRequired = true;
                            }
                        }
                    }
                }
                if(!$imageModelRequired) {
                    unset($models['image']);
                }
            }
        }
        return $models;
    }
}
