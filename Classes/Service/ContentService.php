<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentService implements SingletonInterface
{
    public const IGNORED_TCA_FIELDS = [
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

    /** @var list<string> */
    public array $consideredImageRenderTypes = [
        'file',
    ];

    /** @var list<string> */
    protected array $consideredTextRenderTypes = [
        'input',
        'text',
    ];

    /** @var array<string, mixed> */
    protected array $ignoreFieldsByRecord = [
        'tx_news_domain_model_news' => [
            'tx_news_domain_model_link',
            'tt_content',
        ],
    ];

    protected int $maxDepth = 5;

    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly LocalizationService $localizationService,
    ) {}

    /**
     * @param array<string, mixed> $requestFields
     *
     * @return array<string, mixed>
     */
    public function cleanupRequestField(array $requestFields, string $table): array
    {
        if (array_key_exists($table, $this->ignoreFieldsByRecord)) {
            $ignoreFields = $this->ignoreFieldsByRecord[$table];
            foreach ($ignoreFields as $ignoreField) {
                unset($requestFields[$ignoreField]);
            }
        }

        return $requestFields;
    }

    /**
     * @param array<string, mixed> $defaultValues
     *
     * @return array<string, mixed>
     */
    public function fetchRequestFields(ServerRequestInterface $request, array $defaultValues, string $cType, int $pid, string $table): array
    {
        $formData = $this->getFormData($request, $defaultValues, $pid, $table);

        $requestFields[$table] = [
            'label' => 'General fields',
            'text' => [],
            'image' => [],
        ];

        $itemList = $GLOBALS['TCA'][$table]['types'][$cType]['showitem'];
        $fieldsArray = GeneralUtility::trimExplode(',', $itemList, true);
        $depthTracker = [];
        $this->iterateOverFieldsArray($fieldsArray, $requestFields, $formData, $pid, $table, $depthTracker);

        return $this->cleanupRequestField($requestFields, $table);
    }

    /**
     * @param array<string, mixed> $depthTracker
     * @param array<string, mixed> $formData
     * @param array<mixed>         $requestFields
     */
    public function checkSingleField(
        array $formData,
        string $fieldName,
        array &$requestFields,
        int $pid,
        string $table,
        array &$depthTracker
    ): void {
        if (!is_array($formData['processedTca']['columns'][$fieldName] ?? null) || in_array($fieldName, self::IGNORED_TCA_FIELDS)) {
            return;
        }
        $parameterArray = [];
        $parameterArray['fieldConf'] = $formData['processedTca']['columns'][$fieldName];

        $fieldIsExcluded = $parameterArray['fieldConf']['exclude'] ?? false;
        $fieldNotExcludable = $this->backendUserService->getBackendUser()?->check('non_exclude_fields', $formData['tableName'].':'.$fieldName) ?? false;
        if ($fieldIsExcluded && !$fieldNotExcludable) {
            return;
        }

        $tsConfig = $formData['pageTsConfig']['TCEFORM.'][$formData['tableName'].'.'][$fieldName.'.'] ?? [];
        $parameterArray['fieldTSConfig'] = is_array($tsConfig) ? $tsConfig : [];

        if ($parameterArray['fieldTSConfig']['disabled'] ?? false) {
            return;
        }

        $parameterArray['fieldConf']['config'] = FormEngineUtility::overrideFieldConf($parameterArray['fieldConf']['config'], $parameterArray['fieldTSConfig']);

        if ('inline' === $parameterArray['fieldConf']['config']['type']) {
            $foreignTable = $parameterArray['fieldConf']['config']['foreign_table'];
            $requestFields[$foreignTable]['label'] = $parameterArray['fieldConf']['label'];
            $requestFields[$foreignTable]['foreignField'] = $table;
            $this->fetchIrreRequestFields($formData['request'], $formData['defaultValues'], $requestFields, $foreignTable, $pid, $depthTracker);
        }
        if (!empty($parameterArray['fieldConf']['config']['renderType'])) {
            $renderType = $parameterArray['fieldConf']['config']['renderType'];
        } else {
            $renderType = $parameterArray['fieldConf']['config']['type'];
        }
        if (in_array($renderType, $this->consideredTextRenderTypes)) {
            $requestFields[$table]['text'][$fieldName] = [
                'label' => $parameterArray['fieldConf']['label'],
                'renderType' => $renderType,
            ];
            if (true === (bool) ($parameterArray['fieldConf']['config']['enableRichtext'] ?? false)
                && is_array($parameterArray['fieldConf']['config']['richtextConfiguration'] ?? null)
                && !($parameterArray['fieldConf']['config']['richtextConfiguration']['disabled'] ?? false)
            ) {
                $data = [
                    'parameterArray' => $parameterArray,
                    'databaseRow' => $formData['databaseRow'],
                    'systemLanguageRows' => $formData['systemLanguageRows'],
                    'tableName' => $table,
                    'fieldName' => $fieldName,
                    'effectivePid' => $pid,
                    'recordTypeValue' => 'text',
                ];
                $requestFields[$table]['text'][$fieldName]['rteConfig'] = json_encode($data);
            }
        }
        if (in_array($renderType, $this->consideredImageRenderTypes)) {
            if (array_key_exists('allowed', $parameterArray['fieldConf']['config'])
                && (str_contains($parameterArray['fieldConf']['config']['allowed'], 'jpg') || str_contains($parameterArray['fieldConf']['config']['allowed'], 'jpeg'))) {
                $requestFields[$table]['image'][$fieldName] = [
                    'label' => $parameterArray['fieldConf']['label'],
                    'renderType' => $renderType,
                ];
            } else {
                $allowedFileExtensions = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
                if (str_contains($allowedFileExtensions, 'jpg') || str_contains($allowedFileExtensions, 'jpeg')) {
                    $requestFields[$table]['image'][$fieldName] = [
                        'label' => $parameterArray['fieldConf']['label'],
                        'renderType' => $renderType,
                    ];
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $models
     * @param array<string, mixed> $requestFields
     *
     * @return array<string, mixed>
     */
    public function checkRequestModels(array $requestFields, array $models): array
    {
        foreach ($models as $type => $model) {
            if ('text' === $type) {
                $textModelRequired = false;
                foreach ($requestFields as $fields) {
                    if (array_key_exists('text', $fields)) {
                        foreach ($fields['text'] as $data) {
                            if (array_key_exists('renderType', $data) && in_array($data['renderType'], $this->consideredTextRenderTypes)) {
                                $textModelRequired = true;
                            }
                        }
                    }
                }
                if (!$textModelRequired) {
                    unset($models['text']);
                }
            }
            if ('image' === $type) {
                $imageModelRequired = false;
                foreach ($requestFields as $fields) {
                    if (array_key_exists('image', $fields)) {
                        foreach ($fields['image'] as $data) {
                            if (array_key_exists('renderType', $data) && in_array($data['renderType'], $this->consideredImageRenderTypes)) {
                                $imageModelRequired = true;
                            }
                        }
                    }
                }
                if (!$imageModelRequired) {
                    unset($models['image']);
                }
            }
        }

        return $models;
    }

    /**
     * @return array<string, mixed>
     */
    protected function explodeSingleFieldShowItemConfiguration(string $field): array
    {
        $fieldArray = GeneralUtility::trimExplode(';', $field);
        if (empty($fieldArray[0])) {
            throw new \RuntimeException($this->localizationService->translate('aiSuite.error.field.mustNotBeEmpty'), 1426448465);
        }

        return [
            'fieldName' => $fieldArray[0],
            'fieldLabel' => !empty($fieldArray[1]) ? $fieldArray[1] : null,
            'paletteName' => !empty($fieldArray[2]) ? $fieldArray[2] : null,
        ];
    }

    /**
     * @param array<string, mixed> $depthTracker
     * @param array<string, mixed> $formData
     * @param array<mixed>         $requestFields
     */
    protected function createPaletteContentArray(
        string $paletteName,
        array &$requestFields,
        array $formData,
        int $pid,
        string $table,
        array &$depthTracker
    ): void {
        if (empty($paletteName) || empty($GLOBALS['TCA'][$table]['palettes'][$paletteName]['showitem'])) {
            return;
        }
        $fieldsArray = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['palettes'][$paletteName]['showitem'], true);
        foreach ($fieldsArray as $fieldString) {
            $fieldArray = $this->explodeSingleFieldShowItemConfiguration($fieldString);
            $fieldName = $fieldArray['fieldName'];
            if ('--linebreak--' === $fieldName) {
                continue;
            }
            $this->checkSingleField($formData, $fieldName, $requestFields, $pid, $table, $depthTracker);
        }
    }

    /**
     * @param array<string, mixed> $defaultValues
     * @param array<string, mixed> $depthTracker
     * @param array<mixed>         $requestFields
     */
    protected function fetchIrreRequestFields(
        ServerRequestInterface $request,
        array $defaultValues,
        array &$requestFields,
        string $table,
        int $pid,
        array &$depthTracker
    ): void {
        $trackingKey = $table.'-'.$pid;

        if (!isset($depthTracker[$trackingKey])) {
            $depthTracker[$trackingKey] = 0;
        }

        if ($depthTracker[$trackingKey] >= $this->maxDepth) {
            return;
        }

        ++$depthTracker[$trackingKey];

        $formData = $this->getFormData($request, $defaultValues, $pid, $table);

        $showItemKey = array_key_first($GLOBALS['TCA'][$table]['types']);
        $itemList = $GLOBALS['TCA'][$table]['types'][$showItemKey]['showitem'];
        $fieldsArray = GeneralUtility::trimExplode(',', $itemList, true);
        $this->iterateOverFieldsArray($fieldsArray, $requestFields, $formData, $pid, $table, $depthTracker);
    }

    /**
     * @param array<string, mixed> $defaultValues
     *
     * @return array<string, mixed>
     */
    protected function getFormData(ServerRequestInterface $request, array $defaultValues, int $pid, string $table): array
    {
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

    /**
     * @param array<string, mixed> $depthTracker
     * @param list<string>         $fieldsArray
     * @param array<string, mixed> $formData
     * @param array<mixed>         $requestFields
     */
    protected function iterateOverFieldsArray(
        array $fieldsArray,
        array &$requestFields,
        array $formData,
        int $pid,
        string $table,
        array &$depthTracker
    ): void {
        foreach ($fieldsArray as $fieldString) {
            $fieldConfiguration = $this->explodeSingleFieldShowItemConfiguration($fieldString);
            $fieldName = $fieldConfiguration['fieldName'];
            if ('--palette--' === $fieldName) {
                $this->createPaletteContentArray($fieldConfiguration['paletteName'] ?? '', $requestFields, $formData, $pid, $table, $depthTracker);
            } else {
                if (!is_array($formData['processedTca']['columns'][$fieldName] ?? null)) {
                    continue;
                }
                $this->checkSingleField($formData, $fieldName, $requestFields, $pid, $table, $depthTracker);
            }
        }
    }
}
