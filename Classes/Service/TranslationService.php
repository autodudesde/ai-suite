<?php

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\TranslationRepository;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

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
    protected PagesRepository $pagesRepository;
    protected FlashMessageService $flashMessageService;
    protected BackgroundTaskRepository $backgroundTaskRepository;
    protected LoggerInterface $logger;
    protected TranslationRepository $translationRepository;

    private array $translatableMetadataFields = [
        'title',
        'subtitle',
        'seo_title',
        'description',
        'keywords',
        'og_title',
        'og_description',
        'twitter_title',
        'twitter_description'
    ];

    public function __construct(
        ContentService $contentService,
        UriBuilder $uriBuilder,
        UuidService $uuidService,
        SiteFinder $siteFinder,
        ExtensionConfiguration $extensionConfiguration,
        IconFactory $iconFactory,
        SiteService $siteService,
        FlexFormTranslationService $flexFormTranslationService,
        PagesRepository $pagesRepository,
        FlashMessageService $flashMessageService,
        BackgroundTaskRepository $backgroundTaskRepository,
        LoggerInterface $logger,
        TranslationRepository $translationRepository,
    ) {
        $this->contentService = $contentService;
        $this->uriBuilder = $uriBuilder;
        $this->uuidService = $uuidService;
        $this->siteFinder = $siteFinder;
        $this->extensionConfiguration = $extensionConfiguration;
        $this->iconFactory = $iconFactory;
        $this->siteService = $siteService;
        $this->flexFormTranslationService = $flexFormTranslationService;
        $this->pagesRepository = $pagesRepository;
        $this->flashMessageService = $flashMessageService;
        $this->backgroundTaskRepository = $backgroundTaskRepository;
        $this->logger = $logger;
        $this->translationRepository = $translationRepository;
    }

    public function fetchTranslationFields(ServerRequestInterface $request, array $defaultValues, int $ceSrcLangUid, string $table): array
    {
        $formData = $this->getFormData($request, $defaultValues, $ceSrcLangUid, $table);
        $translateFields = [];
        $this->getAllFieldsFromTableTypes($table, $formData, $translateFields);
        return $this->contentService->cleanupRequestField($translateFields, $table);
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
            $containerConfiguration = $formData['processedTca']['containerConfiguration'] ?? [];
            $isContainerElement = false;
            $cType = $formData['databaseRow']['CType'][0] ?? '';
            foreach ($containerConfiguration as $containerValue) {
                if (array_key_exists('cType', $containerValue) && $containerValue['cType'] === $cType) {
                    $isContainerElement = true;
                }
            }
            if (!empty($fieldValue) || $isContainerElement) {
                $translateFields[$fieldName] = $fieldValue;
            }
        }
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
            $icon = $this->iconFactory->getIcon($flagIcon, 'small', 'tx-aisuite-extension');
            $lC = $icon->render();
        } else {
            $lC = $this->iconFactory
                ->getIcon('tx-aisuite-extension', 'small')
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

    public function getAvailableTargetLanguages(array $sysLanguages, int $pageId): array
    {
        $availableTargetLanguages = [];

        foreach ($sysLanguages as $language) {
            $languageUid = (int)$language['uid'];
            if ($languageUid === 0 || $languageUid === -1) {
                continue;
            }
            if (!$this->pagesRepository->checkPageTranslationExists($pageId, $languageUid)) {
                $availableTargetLanguages[] = $language;
            }
        }

        return $availableTargetLanguages;
    }

    public function collectPageTranslatableContent(int $pageUid, int $sourceLanguageUid, string $translationScope, int $targetLanguageUid = 0): array
    {
        $translatableContent = [];

        switch ($translationScope) {
            case 'metadata':
                $translatableContent['pages'] = $this->collectPageMetadataFields($pageUid, $sourceLanguageUid);
                break;
            case 'content':
                $translatableContent[] = $this->collectPageContentElementFields($pageUid, $sourceLanguageUid, $targetLanguageUid);
                break;
            case 'all':
                $translatableContent = $this->collectPageContentElementFields($pageUid, $sourceLanguageUid, $targetLanguageUid);
                $translatableContent['pages'] = $this->collectPageMetadataFields($pageUid, $sourceLanguageUid);
                break;
        }

        if (empty($translatableContent)) {
            throw new \RuntimeException('No translatable content found for the specified scope ' . $translationScope . ' and page.');
        }

        if ($translationScope === 'all') {
            $hasContent = false;
            foreach ($translatableContent as $section) {
                if (!empty($section)) {
                    $hasContent = true;
                    break;
                }
            }
            if (!$hasContent) {
                throw new \RuntimeException('No translatable content found in any section (metadata, content).');
            }
        }

        return $translatableContent;
    }

    public function getPageIdFromRequest(ServerRequestInterface $request): int
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $directId = $queryParams['id'] ?? $parsedBody['id'] ?? null;
        if ($directId !== null) {
            return (int)$directId;
        }

        $editPages = $queryParams['edit']['pages'] ?? $parsedBody['edit']['pages'] ?? null;
        if ($editPages && is_array($editPages)) {
            $pageIds = array_keys($editPages);
            return (int)$pageIds[0];
        }

        return 0;
    }

    public function addTranslationNotifications(array $backgroundTasks, int $pageId): string
    {
        $taskData = $backgroundTasks['translation'][$pageId] ?? null;
        if (!$taskData) {
            return '';
        }

        $status = $taskData['status'] ?? '';
        if ($status === '') {
            return '';
        }

        $uuid = $taskData['uuid'] ?? '';
        $flashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages');

        $notificationConfig = [
            'pending' => [
                'message' => 'AiSuite.notification.translation.pending.message',
                'title' => 'AiSuite.notification.translation.pending.title',
                'severity' => ContextualFeedbackSeverity::NOTICE
            ],
            'task-error' => [
                'message' => 'AiSuite.notification.translation.failed.message',
                'title' => 'AiSuite.notification.translation.failed.title',
                'severity' => ContextualFeedbackSeverity::ERROR
            ]
        ];

        if (isset($notificationConfig[$status])) {
            $config = $notificationConfig[$status];
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->translate($config['message']),
                $this->translate($config['title']),
                $config['severity']
            );
            $flashMessageQueue->addMessage($message);

            if (!empty($uuid)) {
                return ($status === 'pending' ? 'pending__' : 'error__') . $uuid;
            }
        }

        return '';
    }

    public function processFinishedTranslationTasksForPage(int $pageUid): array
    {
        $processedCount = 0;
        $errorCount = 0;

        try {
            $finishedTasks = $this->backgroundTaskRepository->findTranslationTasksForPage($pageUid, 'finished');

            if (empty($finishedTasks)) {
                return [
                    'success' => true,
                    'processedCount' => 0,
                    'errorCount' => 0,
                    'message' => ''
                ];
            }

            foreach ($finishedTasks as $task) {
                try {
                    $this->processTranslationTask($task);
                    $processedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->logger->error('Error processing translation task', [
                        'uuid' => $task['uuid'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $success = $errorCount === 0;
            $message = $this->buildResultMessage($processedCount, $errorCount);

            return [
                'success' => $success,
                'processedCount' => $processedCount,
                'errorCount' => $errorCount,
                'message' => $message
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error processing finished translation tasks for page', [
                'pageUid' => $pageUid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'processedCount' => $processedCount,
                'errorCount' => $errorCount + 1,
                'message' => 'Error processing translation tasks: ' . $e->getMessage()
            ];
        }
    }

    public function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    public function translate(string $xlfKey, array $arguments = []): string
    {
        $xlfPrefix = '';
        if (!str_starts_with($xlfKey, 'LLL:')) {
            $xlfPrefix = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:';
        }
        return sprintf($this->getLanguageService()->sL($xlfPrefix . $xlfKey), ...$arguments);
    }

    protected function getAllFieldsFromTableTypes(string $table, array $formData, array &$translateFields, bool $extendendMode = true): void
    {
        $types = $GLOBALS['TCA'][$table]['types'] ?? [];

        if (empty($types)) {
            return;
        }

        if ($table === 'tt_content') {
            $cType = $formData['databaseRow']['CType'][0] ?? '';
            if (!empty($cType) && isset($types[$cType]['showitem'])) {
                $itemList = $types[$cType]['showitem'];
                $allFields = GeneralUtility::trimExplode(',', $itemList, true);
                $this->iterateOverFieldsArray($allFields, $translateFields, $formData, $table);
            }
        } else {
            foreach ($types as $typeConfig) {
                if (!empty($typeConfig['showitem'])) {
                    $typeFields = GeneralUtility::trimExplode(',', $typeConfig['showitem'], true);
                    $this->iterateOverFieldsArray($typeFields, $translateFields, $formData, $table);
                }
            }
        }
    }

    protected function explodeSingleFieldShowItemConfiguration($field): array
    {
        $fieldArray = GeneralUtility::trimExplode(';', $field);
        if (empty($fieldArray[0])) {
            throw new \RuntimeException($this->translate('tx_aisuite.error.field.mustNotBeEmpty'), 1426448465);
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

    protected function processInlineFieldForTranslation(
        array $parentFormData,
        string $fieldName,
        array $fieldConf,
        array &$translateFields
    ): void {
        $foreignTable = $fieldConf['config']['foreign_table'] ?? '';
        if (empty($foreignTable)) {
            return;
        }
        if (!array_key_exists($foreignTable, $translateFields)) {
            $translateFields[$foreignTable] = [];
        }
        $inlineFields = $this->collectInlineChildFields($parentFormData, $fieldName, $foreignTable);

        if (!empty($inlineFields)) {
            $translateFields[$foreignTable][$fieldName] = $inlineFields;
        }
    }

    protected function collectInlineChildFields(array $parentFormData, string $parentFieldName, string $foreignTable): array
    {
        $childFields = [];
        $childUidList = isset($parentFormData['databaseRow'][$parentFieldName]) ? $parentFormData['databaseRow'][$parentFieldName] : [];
        $childUids = explode(',', $childUidList);
        if (!is_array($childUids)) {
            return $childFields;
        }

        foreach ($childUids as $childUid) {
            if (empty($childUid)) {
                continue;
            }

            try {
                $childFormData = $this->getFormData(
                    $parentFormData['request'],
                    [],
                    (int)$childUid,
                    $foreignTable
                );

                $childTranslateFields = [];
                $itemList = $this->getShowItemListForTable($foreignTable, $childFormData);
                $fieldsArray = GeneralUtility::trimExplode(',', $itemList, true);
                $this->iterateOverFieldsArray($fieldsArray, $childTranslateFields, $childFormData, $foreignTable);

                if (!empty($childTranslateFields)) {
                    $childFields[$childUid] = $childTranslateFields;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $childFields;
    }

    protected function getShowItemListForTable(string $table, array $formData): string
    {
        if ($table === 'tt_content') {
            return $GLOBALS['TCA'][$table]['types'][$formData['databaseRow']['CType'][0] ?? 'text']['showitem'] ?? '';
        } elseif ($table === 'sys_file_reference') {
            return $GLOBALS['TCA'][$table]['types'][2]['showitem'] ?? '';
        } else {
            $types = $GLOBALS['TCA'][$table]['types'] ?? [];
            if (empty($types)) {
                return '';
            }
            $firstKey = array_key_first($types);
            return $types[$firstKey]['showitem'] ?? '';
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

    protected function collectPageMetadataFields(int $pageUid, int $sourceLanguageUid): array
    {
        $pageRecord = $this->pagesRepository->getPageRecord($pageUid, $sourceLanguageUid);
        if (!$pageRecord) {
            return [];
        }

        $metadataFields = [];
        foreach ($this->translatableMetadataFields as $field) {
            if (!empty($pageRecord[$field])) {
                $metadataFields[$field] = $pageRecord[$field];
            }
        }

        return $metadataFields;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    protected function collectPageContentElementFields(int $pageUid, int $sourceLanguageUid, int $targetLanguageUid = 0): array
    {
        $contentElements = $this->translationRepository->getElementsOnPage($pageUid, $sourceLanguageUid);

        if ($targetLanguageUid > 0) {
            $contentElements = $this->filterUntranslatedContentElements($contentElements, $pageUid, $targetLanguageUid);
        }

        $translatableContent = [];

        foreach ($contentElements as $contentElement) {
            $copyMappingArray = [];
            $this->localize($copyMappingArray, 'tt_content', $contentElement['uid'], $targetLanguageUid);
            foreach ($copyMappingArray as $table => $uidMapping) {
                if (!array_key_exists($table, $translatableContent)) {
                    $translatableContent[$table] = [];
                }
                foreach ($uidMapping as $sourceUid => $translatedUid) {
                    $fields = $this->fetchTranslationFields(
                        $GLOBALS['TYPO3_REQUEST'],
                        [
                            'sys_language_uid' => $targetLanguageUid,
                        ],
                        (int)$sourceUid,
                        $table
                    );
                    if (count($fields) > 0) {
                        $translatableContent[$table][$sourceUid] = $fields;
                    }
                }
            }
        }

        return $translatableContent;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    protected function filterUntranslatedContentElements(array $sourceElements, int $pageUid, int $targetLanguageUid): array
    {
        if (empty($sourceElements)) {
            return $sourceElements;
        }

        $targetElements = $this->translationRepository->getTranslatedElementsOnPage($pageUid, $targetLanguageUid);
        $translatedParentUids = array_column($targetElements, 'l18n_parent');

        return array_filter($sourceElements, function ($element) use ($translatedParentUids) {
            return !in_array((int)$element['uid'], $translatedParentUids);
        });
    }

    protected function processTranslationTask(array $task): void
    {
        try {
            $taskAnswer = json_decode($task['answer'], true);
            $translationData = $taskAnswer['body']['translationResults'] ?? [];
            if (empty($translationData)) {
                $this->backgroundTaskRepository->deleteByUuid($task['uuid']);
                throw new \Exception('Invalid translation result format');
            }
            $this->applyTranslationResult($task, $translationData);
            $this->logger->info('Successfully processed translation task', ['uuid' => $task['uuid']]);
            $affectedRows = $this->backgroundTaskRepository->deleteByUuid($task['uuid']);
            if ($affectedRows === 0) {
                throw new \Exception($this->translate('tx_aisuite.error.backgroundTask.notFound', [$task['uuid']]));
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing translation task: ' . $e->getMessage(), [
                'uuid' => $task['uuid'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    protected function applyTranslationResult(array $task, array $translationData): void
    {
        $pageUid = (int)$task['table_uid'];
        $targetLanguageUid = (int)$task['sys_language_uid'];
        $translationScope = $task['column']; // This contains the translation scope

        $existingTranslation = $this->pagesRepository->checkPageTranslationExists($pageUid, $targetLanguageUid);

        if (!$existingTranslation) {
            $this->executeLocalizationCommand('pages', $pageUid, $targetLanguageUid);
        }

        $translatedPageUid = $this->pagesRepository->getPageTranslationUid($pageUid, $targetLanguageUid);

        if (!$translatedPageUid) {
            throw new \Exception('Could not create or find page translation');
        }

        switch ($translationScope) {
            case 'metadata':
                $this->applyPageMetadataTranslation($translatedPageUid, $translationData);
                $this->updatePageSlug($translatedPageUid);
                break;
            case 'content':
                $this->applyContentElementTranslations($targetLanguageUid, $translationData);
                break;
            case 'all':
                $this->applyCompletePageTranslation($translatedPageUid, $targetLanguageUid, $translationData);
                $this->updatePageSlug($translatedPageUid);
                break;
            default:
                throw new \Exception('Unknown translation scope: ' . $translationScope);
        }
        BackendUtility::setUpdateSignal('updatePageTree', $pageUid);
    }

    /**
     * @throws \Exception
     */
    protected function applyPageMetadataTranslation(int $translatedPageUid, array $translationData): void
    {
        $datamap = [];
        foreach ($this->translatableMetadataFields as $field) {
            if (isset($translationData['pages'][$field])) {
                $datamap['pages'][$translatedPageUid][$field] = $translationData['pages'][$field];
            }
        }

        if (!empty($datamap)) {
            $this->executeDataHandler($datamap, []);
        }
    }

    protected function applyContentElementTranslations(int $targetLanguageUid, array $translationData): void
    {
        $datamap =  [];
        $alreadyTranslatedUids = [];

        $sortedTranslationData = [];
        if (isset($translationData['tt_content'])) {
            $sortedTranslationData['tt_content'] = $translationData['tt_content'];
            unset($translationData['tt_content']);
        }
        $sortedTranslationData = array_merge($sortedTranslationData, $translationData);

        foreach ($sortedTranslationData as $table => $elements) {
            foreach ($elements as $sourceUid => $element) {
                try {
                    $languageParentField = $table === 'tt_content' ? 'l18n_parent' : 'l10n_parent';
                    $existingTranslation = $this->translationRepository->getRecordTranslation($sourceUid, $targetLanguageUid, $table, $languageParentField);
                    if ($existingTranslation) {
                        if (!array_key_exists($table, $datamap)) {
                            $datamap[$table] = [];
                        }
                        $datamap[$table][$existingTranslation['uid']] = $sortedTranslationData[$table][$sourceUid];
                        $alreadyTranslatedUids[$table][$sourceUid] = $existingTranslation['uid'];
                    } else {
                        if (array_key_exists($table, $alreadyTranslatedUids) && array_key_exists($sourceUid, $alreadyTranslatedUids[$table])) {
                            continue;
                        }
                        $tranlatedUidMapping = $this->executeLocalizationCommand($table, $sourceUid, $targetLanguageUid);
                        foreach ($tranlatedUidMapping as $table => $uidMapping) {
                            if (!array_key_exists($table, $datamap)) {
                                $datamap[$table] = [];
                            }
                            foreach ($uidMapping as $srcUid => $destUid) {
                                if (isset($sortedTranslationData[$table][$srcUid])) {
                                    $datamap[$table][$destUid] = $sortedTranslationData[$table][$srcUid];
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to translate content element', ['error' => $e->getMessage()]);
                }
            }
        }
        if (count($datamap) > 0) {
            $this->executeDataHandler($datamap, []);
        }
    }

    /**
     * @throws \Exception
     */
    protected function applyCompletePageTranslation(int $translatedPageUid, int $targetLanguageUid, array $translationData): void
    {
        if (isset($translationData['pages'])) {
            $this->applyPageMetadataTranslation($translatedPageUid, $translationData);
        }
        unset($translationData['pages']);
        if (!empty($translationData)) {
            $this->applyContentElementTranslations($targetLanguageUid, $translationData);
        }
    }

    /**
     * @throws \Exception
     */
    public function updatePageSlug(int $pageUid): void
    {
        $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'];
        $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, 'pages', 'slug', $fieldConfig);
        $pageRecord = BackendUtility::getRecord('pages', $pageUid);

        if ($pageRecord) {
            $slug = $slugHelper->generate($pageRecord, $pageRecord['pid']);

            $datamap = [
                'pages' => [
                    $pageUid => [
                        'slug' => $slug
                    ]
                ]
            ];

            $this->executeDataHandler($datamap, []);
        }
    }

    protected function buildResultMessage(int $processedCount, int $errorCount): string
    {
        if ($processedCount === 0 && $errorCount === 0) {
            return '';
        }

        $messages = [];
        if ($processedCount > 0) {
            $messages[] = $processedCount === 1
                ? 'Successfully processed 1 translation task'
                : "Successfully processed {$processedCount} translation tasks";
        }
        if ($errorCount > 0) {
            $messages[] = $errorCount === 1
                ? '1 task failed'
                : "{$errorCount} tasks failed";
        }

        return implode(', ', $messages) . '.';
    }

    /**
     * @throws \Exception
     */
    protected function executeDataHandler(array $datamap, array $cmdmap): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, $cmdmap);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();

        if (!empty($dataHandler->errorLog)) {
            throw new \Exception('DataHandler error: ' . implode(', ', $dataHandler->errorLog));
        }
    }

    /**
     * @throws \Exception
     */
    protected function executeLocalizationCommand(string $table, int $uid, int $targetLanguageUid): array
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $cmd = [
            $table => [
                $uid => [
                    'localize' => $targetLanguageUid
                ]
            ]
        ];

        $dataHandler->start([], $cmd);
        $dataHandler->process_cmdmap();

        if (!empty($dataHandler->errorLog)) {
            throw new \Exception("Error creating {$table} translation: " . implode(', ', $dataHandler->errorLog));
        }

        return $dataHandler->copyMappingArray_merged ?? [];
    }

    /**
     * DATAHANDLER FUNCTIONS
     */

    protected function localize(&$copyMappingArray, $table, $uid, $language): void
    {
        $uid = (int)$uid;

        if (!$GLOBALS['TCA'][$table] || !$uid) {
            return;
        }

        if (empty($GLOBALS['TCA'][$table]['ctrl']['languageField']) || empty($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])) {
            return;
        }

        // Getting workspace overlay if possible - this will localize versions in workspace if any
        $row = BackendUtility::getRecordWSOL($table, $uid);
        if (!is_array($row)) {
            return;
        }
        $pageRecord = [];
        if ($table === 'pages') {
            $pageRecord = $row;
        } elseif ((int)$row['pid'] > 0) {
            $pageRecord = BackendUtility::getRecord('pages', $row['pid']);
            if (!is_array($pageRecord)) {
                return;
            }
        }
        if (($pageRecord === [] && $row['pid'] === 0 && !($GLOBALS['BE_USER']->isAdmin() || BackendUtility::isRootLevelRestrictionIgnored($table)))
        ) {
            return;
        }

        [$pageId] = BackendUtility::getTSCpid($table, $uid, '');
        // Try to fetch the site language from the pages' associated site
        $siteLanguage = $this->getSiteLanguageForPage((int)$pageId, (int)$language);
        if ($siteLanguage === null) {
            return;
        }

        // Make sure that records which are translated from another language than the default language have a correct
        // localization source set themselves, before translating them to another language.
        if ((int)$row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] !== 0
            && $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] > 0) {
            $localizationParentRecord = BackendUtility::getRecord(
                $table,
                $row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']]
            );
            if ((int)$localizationParentRecord[$GLOBALS['TCA'][$table]['ctrl']['languageField']] !== 0) {
                return;
            }
        }

        // Default language records must never have a localization parent as they are the origin of any translation.
        if ((int)$row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] !== 0
            && (int)$row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] === 0) {
            return;
        }

        $overrideValues = [];
        $overrideValues[$GLOBALS['TCA'][$table]['ctrl']['languageField']] = (int)$language;
        if ((int)$row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] === 0) {
            $overrideValues[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] = $uid;
        }
        if (isset($GLOBALS['TCA'][$table]['ctrl']['translationSource'])) {
            $overrideValues[$GLOBALS['TCA'][$table]['ctrl']['translationSource']] = $uid;
        }
        if (isset($GLOBALS['TCA'][$table]['ctrl']['type'])) {
            $overrideValues[$GLOBALS['TCA'][$table]['ctrl']['type']] = $row[$GLOBALS['TCA'][$table]['ctrl']['type']] ?? null;
        }
        foreach ($GLOBALS['TCA'][$table]['columns'] as $fN => $fCfg) {
            if (isset($fCfg['l10n_mode'], $fCfg['config']['type'])
                && $fCfg['l10n_mode'] === 'prefixLangTitle'
                && (
                    $fCfg['config']['type'] === 'text'
                    || $fCfg['config']['type'] === 'input'
                    || $fCfg['config']['type'] === 'email'
                    || $fCfg['config']['type'] === 'link'
                )
                && (string)$row[$fN] !== ''
            ) {
                $overrideValues[$fN] = $row[$fN];
            }
            if (($fCfg['config']['MM'] ?? false) && !empty($fCfg['config']['MM_oppositeUsage'])) {
                $overrideValues[$fN] = 0;
            }
        }

        if ($table !== 'pages') {
            $this->copyRecord($copyMappingArray, $table, $uid, $overrideValues, '', $language);
        }
    }

    protected function copyRecord(&$copyMappingArray, $table, $uid, $overrideValues = [], $excludeFields = '', $language = 0, $ignoreLocalization = false): void
    {
        $uid = ($origUid = (int)$uid);
        if (empty($GLOBALS['TCA'][$table]) || $uid === 0) {
            return;
        }

        $row = BackendUtility::getRecord($table, $uid);
        if (!is_array($row)) {
            return;
        }
        BackendUtility::workspaceOL($table, $row, $GLOBALS['BE_USER']->workspace);
        $pageRecord = [];
        if ($table === 'pages') {
            $pageRecord = $row;
        } elseif ((int)$row['pid'] > 0) {
            $pageRecord = BackendUtility::getRecord('pages', $row['pid']);
            if (!is_array($pageRecord)) {
                return;
            }
        }
        if (($pageRecord === [] && $row['pid'] === 0 && !($GLOBALS['BE_USER']->isAdmin() || BackendUtility::isRootLevelRestrictionIgnored($table)))
        ) {
            return;
        }

        $fullLanguageCheckNeeded = $table !== 'pages';
        if (!$ignoreLocalization && ($language <= 0 || !$GLOBALS['BE_USER']->checkLanguageAccess($language)) && !$GLOBALS['BE_USER']->recordEditAccessInternals($table, $row, false, null, $fullLanguageCheckNeeded)) {
            return;
        }

        $nonFields = array_unique(GeneralUtility::trimExplode(',', 'uid,perms_userid,perms_groupid,perms_user,perms_group,perms_everybody,t3ver_oid,t3ver_wsid,t3ver_state,t3ver_stage,' . $excludeFields, true));
        BackendUtility::workspaceOL($table, $row, $GLOBALS['BE_USER']->workspace);
        if (BackendUtility::isTableWorkspaceEnabled($table)
            && $GLOBALS['BE_USER']->workspace > 0
            && VersionState::cast($row['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER)
        ) {
            return;
        }
        $row = BackendUtility::purgeComputedPropertiesFromRecord($row);

        foreach ($row as $field => $value) {
            if (!in_array($field, $nonFields, true)) {
                $conf = $GLOBALS['TCA'][$table]['columns'][$field]['config'] ?? [];
                if (array_key_exists($field, $overrideValues)) {
                    continue;
                } else {
                    $this->copyRecord_procBasedOnFieldType($copyMappingArray, $table, $uid, $value, $row, $conf, $language);
                }
            }
        }
        $copyMappingArray[$table][$origUid] = 1;
    }

    protected function getSiteLanguageForPage(int $pageId, int $languageId): ?SiteLanguage
    {
        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
            return $site->getLanguageById($languageId);
        } catch (SiteNotFoundException | \InvalidArgumentException $e) {
            $sites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
            foreach ($sites as $site) {
                try {
                    return $site->getLanguageById($languageId);
                } catch (\InvalidArgumentException $e) {
                    continue;
                }
            }
        }

        return null;
    }

    public function copyRecord_procBasedOnFieldType(&$copyMappingArray, $table, $uid, $value, $row, $conf, $language = 0): void
    {
        $relationFieldType = $this->getRelationFieldType($conf);
        if ($this->isReferenceField($conf) || $relationFieldType === 'mm') {
            $this->copyRecord_processManyToMany($copyMappingArray, $table, $uid, $value, $conf, $language);
        } elseif ($relationFieldType !== false) {
            $this->copyRecord_processRelation($copyMappingArray, $table, $uid, $value, $row, $conf, $language);
        }
    }

    protected function copyRecord_processRelation(
        &$copyMappingArray,
        $table,
        $uid,
        $value,
        $row,
        $conf,
        $language
    ) {
        $dbAnalysis = $this->createRelationHandlerInstance();
        $dbAnalysis->start($value, $conf['foreign_table'], '', $uid, $table, $conf);
        foreach ($dbAnalysis->itemArray as $k => $v) {
            // If language is set and differs from original record, this isn't a copy action but a localization of our parent/ancestor:
            if ($language > 0 && BackendUtility::isTableLocalizable($table) && $language != $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]) {
                // Children should be localized when the parent gets localized the first time, just do it:
                $this->localize($copyMappingArray, $v['table'], $v['id'], $language);
            }
        }
    }

    protected function copyRecord_processManyToMany(&$copyMappingArray, $table, $uid, $value, $conf, $language)
    {
        $allowedTables = $conf['type'] === 'group' ? $conf['allowed'] : $conf['foreign_table'];
        $allowedTablesArray = GeneralUtility::trimExplode(',', $allowedTables, true);
        $mmTable = !empty($conf['MM']) ? $conf['MM'] : '';

        $dbAnalysis = $this->createRelationHandlerInstance();
        $dbAnalysis->start($value, $allowedTables, $mmTable, $uid, $table, $conf);

        // Check if referenced records of select or group fields should also be localized in general.
        // A further check is done in the loop below for each table name.
        if ($language > 0 && $mmTable === '' && !empty($conf['localizeReferencesAtParentLocalization'])) {
            // Check whether allowed tables can be localized.
            $localizeTables = [];
            foreach ($allowedTablesArray as $allowedTable) {
                $localizeTables[$allowedTable] = BackendUtility::isTableLocalizable($allowedTable);
            }

            foreach ($dbAnalysis->itemArray as $index => $item) {
                // No action required, if referenced tables cannot be localized (current value will be used).
                if (empty($localizeTables[$item['table']])) {
                    continue;
                }

                // Since select or group fields can reference many records, check whether there's already a localization.
                $recordLocalization = BackendUtility::getRecordLocalization($item['table'], $item['id'], $language);
                if (!$recordLocalization) {
                    $this->localize($copyMappingArray, $item['table'], $item['id'], $language);
                }
            }
        }
    }

    protected function createRelationHandlerInstance(): RelationHandler
    {
        $isWorkspacesLoaded = ExtensionManagementUtility::isLoaded('workspaces');
        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);
        $relationHandler->setWorkspaceId($GLOBALS['BE_USER']->workspace);
        $relationHandler->setUseLiveReferenceIds($isWorkspacesLoaded);
        $relationHandler->setUseLiveParentIds($isWorkspacesLoaded);
        return $relationHandler;
    }

    protected function getRelationFieldType($conf): bool|string
    {
        if (
            empty($conf['foreign_table'])
            || !in_array($conf['type'] ?? '', ['inline', 'file'], true)
            || ($conf['type'] === 'file' && !($conf['foreign_field'] ?? false))
        ) {
            return false;
        }
        if ($conf['foreign_field'] ?? false) {
            return 'field';
        }
        if ($conf['MM'] ?? false) {
            return 'mm';
        }
        return 'list';
    }

    protected function isReferenceField($conf): bool
    {
        if (!isset($conf['type'])) {
            return false;
        }
        return ($conf['type'] === 'group') || (($conf['type'] === 'select' || $conf['type'] === 'category') && !empty($conf['foreign_table']));
    }
}
