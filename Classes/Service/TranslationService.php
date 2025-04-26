<?php

namespace AutoDudes\AiSuite\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationService
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

    protected ContentService $contentService;

    protected UriBuilder $uriBuilder;

    protected UuidService $uuidService;

    protected SiteFinder $siteFinder;

    protected ExtensionConfiguration $extensionConfiguration;

    protected IconFactory $iconFactory;

    protected SiteService $siteService;

    protected FlexFormTranslationService $flexFormTranslationService;

    public function __construct(
        ContentService $contentService,
        UriBuilder $uriBuilder,
        UuidService $uuidService,
        SiteFinder $siteFinder,
        ExtensionConfiguration $extensionConfiguration,
        IconFactory $iconFactory,
        SiteService $siteService,
        FlexFormTranslationService $flexFormTranslationService
    ) {
        $this->contentService = $contentService;
        $this->uriBuilder = $uriBuilder;
        $this->uuidService = $uuidService;
        $this->siteFinder = $siteFinder;
        $this->extensionConfiguration = $extensionConfiguration;
        $this->iconFactory = $iconFactory;
        $this->siteService = $siteService;
        $this->flexFormTranslationService = $flexFormTranslationService;
    }

    public function fetchTranslationtFields(ServerRequestInterface $request, array $defaultValues, int $ceSrcLangUid, string $table): array
    {
        $formData = $this->getFormData($request, $defaultValues, $ceSrcLangUid, $table);

        $translateFields = [];

        if ($table === 'tt_content') {
            $itemList = $GLOBALS['TCA'][$table]['types'][$formData['databaseRow']['CType'][0]]['showitem'];
        } elseif ($table === 'sys_file_reference') {
            $itemList = $GLOBALS['TCA'][$table]['types'][2]['showitem'];
        } else {
            $types = $GLOBALS['TCA'][$table]['types'];
            $firstKey = array_key_first($types);
            $itemList = $types[$firstKey]['showitem'];
        }

        $fieldsArray = GeneralUtility::trimExplode(',', $itemList, true);
        $this->iterateOverFieldsArray($fieldsArray, $translateFields, $formData, $table);
        return $this->contentService->cleanupRequestField($translateFields, $table);
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

    protected function createPaletteContentArray(
        string $paletteName,
        array &$translateFields,
        array $formData,
        string $table
    ): void {
        if (!empty($paletteName)) {
            $fieldsArray = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['palettes'][$paletteName]['showitem'], true);
            foreach ($fieldsArray as $fieldString) {
                $fieldArray = $this->explodeSingleFieldShowItemConfiguration($fieldString);
                $fieldName = $fieldArray['fieldName'];
                if ($fieldName === '--linebreak--') {
                    continue;
                } else {
                    $this->checkSingleField($formData, $fieldName, $translateFields);
                }
            }
        }
    }

    public function checkSingleField(
        array $formData,
        string $fieldName,
        array &$translateFields
    ): void {
        if (!is_array($formData['processedTca']['columns'][$fieldName] ?? null) || in_array($fieldName, $this->ignoredTcaFields)) {
            return;
        }
        $parameterArray = [];
        $parameterArray['fieldConf'] = $formData['processedTca']['columns'][$fieldName];

        $fieldIsExcluded = $parameterArray['fieldConf']['exclude'] ?? false;
        $fieldNotExcludable = $GLOBALS['BE_USER']->check('non_exclude_fields', $formData['tableName'] . ':' . $fieldName);
        if ($fieldIsExcluded && !$fieldNotExcludable) {
            return;
        }

        $tsConfig = $formData['pageTsConfig']['TCEFORM.'][$formData['tableName'] . '.'][$fieldName . '.'] ?? [];
        $parameterArray['fieldTSConfig'] = is_array($tsConfig) ? $tsConfig : [];

        if ($parameterArray['fieldTSConfig']['disabled'] ?? false) {
            return;
        }

        $parameterArray['fieldConf']['config'] = FormEngineUtility::overrideFieldConf($parameterArray['fieldConf']['config'], $parameterArray['fieldTSConfig']);

        if ($parameterArray['fieldConf']['config']['type'] === 'inline') {
            return;
        }
        if (!empty($parameterArray['fieldConf']['config']['renderType'])) {
            $renderType = $parameterArray['fieldConf']['config']['renderType'];
        } else {
            $renderType = $parameterArray['fieldConf']['config']['type'];
        }
        if (in_array($renderType, $this->consideredTextRenderTypes)) {
            $fieldValue = $formData['databaseRow'][$fieldName] ?? '';
            if (!empty($fieldValue)) {
                $translateFields[$fieldName] = $fieldValue;
            }
        }
    }

    protected function getFormData(ServerRequestInterface $request, array $defaultValues, int $ceSrcLangUid, string $table)
    {
        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class);
        $formDataCompilerInput = [
            'request' => $request,
            'tableName' => $table,
            'vanillaUid' => $ceSrcLangUid,
            'command' => 'edit',
            'returnUrl' => '',
            'defaultValues' => $defaultValues,
        ];
        return $formDataCompiler->compile($formDataCompilerInput, GeneralUtility::makeInstance(TcaDatabaseRecord::class));
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    protected function iterateOverFieldsArray(
        array $fieldsArray,
        array &$translateFields,
        array $formData,
        string $table
    ): void {
        foreach ($fieldsArray as $fieldString) {
            $fieldConfiguration = $this->explodeSingleFieldShowItemConfiguration($fieldString);
            $fieldName = $fieldConfiguration['fieldName'];
            if ($fieldName === '--palette--') {
                $this->createPaletteContentArray($fieldConfiguration['paletteName'] ?? '', $translateFields, $formData, $table);
            } elseif ($fieldName === 'pi_flexform' && $this->extensionConfiguration->get('ai_suite', 'translateFlexFormFields')) {
                $this->flexFormTranslationService->convertFlexFormToTranslateFields($formData, $translateFields);
            } else {
                if (!is_array($formData['processedTca']['columns'][$fieldName] ?? null)) {
                    continue;
                }
                $this->checkSingleField($formData, $fieldName, $translateFields);
            }
        }
    }

    public function isTranslatable(int $pageId, int $languageId): bool
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
            $sourceLanguageIsoCode = $this->siteService->getIsoCodeByLanguageId($site->getDefaultLanguage()->getLanguageId(), $pageId);
            $targetLanguageIsoCode = $this->siteService->getIsoCodeByLanguageId($languageId, $pageId);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws RouteNotFoundException
     * @throws SiteNotFoundException
     */
    public function buildTranslateButton(
        $table,
        $id,
        $lUid_OnPage,
        $returnUrl,
        $pageId,
        $flagIcon = ''
    ): string {
        $params = [];
        $uuid = $this->uuidService->generateUuid();
        $site = $this->siteFinder->getSiteByPageId($pageId);
        $openTranslatedRecordInEditMode = $this->extensionConfiguration->get('ai_suite', 'openTranslatedRecordInEditMode');
        if ($openTranslatedRecordInEditMode) {
            $redirectUrl = (string)$this->uriBuilder->buildUriFromRoute(
                'record_edit',
                [
                    'justLocalized' => $table . ':' . $id . ':' . $lUid_OnPage,
                    'returnUrl' => $returnUrl,
                ]
            );
            $params['redirect'] = $redirectUrl;
        } else {
            $params['redirect'] = $returnUrl;
        }
        $params['cmd'][$table][$id]['localize'] = $lUid_OnPage;
        $params['cmd']['localization'][0]['aiSuite']['srcLangIsoCode'] = $this->siteService->getIsoCodeByLanguageId($site->getDefaultLanguage()->getLanguageId(), $pageId);
        $params['cmd']['localization'][0]['aiSuite']['destLangIsoCode'] = $this->siteService->getIsoCodeByLanguageId($lUid_OnPage, $pageId);
        $params['cmd']['localization'][0]['aiSuite']['destLangId'] = $lUid_OnPage;
        $params['cmd']['localization'][0]['aiSuite']['srcLangId'] = $site->getDefaultLanguage()->getLanguageId();
        $params['cmd']['localization'][0]['aiSuite']['rootPageId'] = $this->siteService->getSiteRootPageId($pageId);
        $params['cmd']['localization'][0]['aiSuite']['translateAi'] = 'AI_SUITE_MODEL';
        $params['cmd']['localization'][0]['aiSuite']['uuid'] = $uuid;
        $href = (string)$this->uriBuilder->buildUriFromRoute('tce_db', $params);
        $title = $this->translate('aiSuite.translateRecord');

        if ($flagIcon) {
            $icon = $this->iconFactory->getIcon($flagIcon, 'small', 'tx-aisuite-localization');
            $lC = $icon->render();
        } else {
            $lC = $this->iconFactory
                ->getIcon('tx-aisuite-localization', 'small')
                ->render();
        }

        return '<a href="#" '
            . 'class="btn btn-default t3js-action-localize ai-suite-record-localization" '
            . 'data-href="' . htmlspecialchars($href) . '" '
            . 'data-page-id="' . $pageId . '" '
            . 'data-uuid="' . $uuid . '" '
            . 'title="' . $title . '">'
            . $lC . '</a> ';
    }
    public function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    public function translate(string $xlfKey, array $arguments = []): string
    {
        $xlfPrefix = '';
        if(!str_starts_with($xlfKey, 'LLL:')) {
            $xlfPrefix = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:';
        }
        return sprintf($this->getLanguageService()->sL($xlfPrefix . $xlfKey), ...$arguments);
    }
}
