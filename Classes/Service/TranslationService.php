<?php

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
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
    protected PagesRepository $pagesRepository;
    protected FlashMessageService $flashMessageService;
    protected BackgroundTaskRepository $backgroundTaskRepository;
    protected LoggerInterface $logger;
    protected ConnectionPool $connectionPool;

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
        ConnectionPool $connectionPool
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
        $this->connectionPool = $connectionPool;
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

        //if ($parameterArray['fieldConf']['config']['type'] === 'inline' || $parameterArray['fieldConf']['config']['type'] === 'file') {
        if ($parameterArray['fieldConf']['config']['type'] === 'inline') {
            $this->processInlineFieldForTranslation($formData, $fieldName, $parameterArray['fieldConf'], $translateFields);
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
                if(array_key_exists('cType', $containerValue) && $containerValue['cType'] === $cType) {
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

    public function getFileReferencesForTranslation(int $pageId, int $languageId): array
    {
        $fileReferences = $this->pagesRepository->getFileReferencesOnPage($pageId, $languageId);
        $translatableFields = $this->collectFileReferenceTranslationFields($fileReferences);

        return [
            'count' => count($fileReferences),
            'translatableCount' => count($translatableFields),
            'hasTranslatableContent' => !empty($translatableFields),
        ];
    }

    public function collectFileReferenceTranslationFields(array $fileReferences): array
    {
        $translationFields = [];

        foreach ($fileReferences as $fileRef) {
            $fields = $this->fetchTranslationFields(
                $GLOBALS['TYPO3_REQUEST'],
                [],
                (int)$fileRef['uid'],
                'sys_file_reference'
            );

            if (!empty($fields)) {
                $translationFields[$fileRef['uid']] = $fields;
            }
        }

        return $translationFields;
    }

    public function collectPageTranslatableContent(int $pageUid, int $sourceLanguageUid, string $translationScope, int $targetLanguageUid = 0): array
    {
        $translatableContent = [];

        switch ($translationScope) {
            case 'metadata':
                $translatableContent['metadata'] = $this->collectPageMetadataFields($pageUid, $sourceLanguageUid);
                break;
            case 'content':
                $translatableContent['content'] = $this->collectPageContentElementFields($pageUid, $sourceLanguageUid, $targetLanguageUid);
                break;
            case 'all':
                $translatableContent['metadata'] = $this->collectPageMetadataFields($pageUid, $sourceLanguageUid);
                $translatableContent['content'] = $this->collectPageContentElementFields($pageUid, $sourceLanguageUid, $targetLanguageUid);
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
            if (!empty($pageIds)) {
                return (int)$pageIds[0];
            }
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
        if(!str_starts_with($xlfKey, 'LLL:')) {
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

        $inlineFields = $this->collectInlineChildFields($parentFormData, $fieldName, $foreignTable);

        if (!empty($inlineFields)) {
            $translateFields[$fieldName] = $inlineFields;
        }
    }

    protected function collectInlineChildFields(array $parentFormData, string $parentFieldName, string $foreignTable): array
    {
        $childFields = [];
        $childUidList = isset($parentFormData['databaseRow'][$parentFieldName])  ? $parentFormData['databaseRow'][$parentFieldName] : [];
        $childUids = explode(',', $childUidList);
        if (!is_array($childUids) || empty($childUids)) {
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
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    protected function collectPageContentElementFields(int $pageUid, int $sourceLanguageUid, int $targetLanguageUid = 0): array
    {
        $contentElements = $this->getContentElementsOnPage($pageUid, $sourceLanguageUid);

        if ($targetLanguageUid > 0) {
            $contentElements = $this->filterUntranslatedContentElements($contentElements, $pageUid, $targetLanguageUid);
        }

        $translatableContent = [];

        foreach ($contentElements as $contentElement) {
            $contentFields = $this->fetchTranslationFields(
                $GLOBALS['TYPO3_REQUEST'],
                [
                    'CType' => $contentElement['CType'],
                    'pid' => $contentElement['pid'],
                    'sys_language_uid' => $sourceLanguageUid,
                ],
                (int)$contentElement['uid'],
                'tt_content'
            );

            if (!empty($contentFields)) {
                $translatableContent[(int)$contentElement['uid']] = [
                    'CType' => $contentElement['CType'] ?? '',
                    'fields' => $contentFields
                ];
            }
        }

        return $translatableContent;
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     * @throws DBALException
     */
    protected function filterUntranslatedContentElements(array $sourceElements, int $pageUid, int $targetLanguageUid): array
    {
        if (empty($sourceElements)) {
            return $sourceElements;
        }

        $queryBuilder = $this->createContentQueryBuilder();
        $targetElements = $queryBuilder
            ->select('l18n_parent')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($targetLanguageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $translatedParentUids = array_column($targetElements, 'l18n_parent');

        return array_filter($sourceElements, function($element) use ($translatedParentUids) {
            return !in_array((int)$element['uid'], $translatedParentUids);
        });
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function getContentElementsOnPage(int $pageUid, int $languageUid): array
    {
        $queryBuilder = $this->createContentQueryBuilder();
        return $queryBuilder
            ->select('uid', 'pid', 'CType', 'header')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(-1, Connection::PARAM_INT))
                )
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    protected function processTranslationTask(array $task): void
    {
        try {
            $taskAnswer = json_decode($task['answer'], true);
            $translationData = $taskAnswer['body']['translationResults'] ?? [];
            if (!$this->isValidTranslationResult($translationData)) {
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
            $this->createPageTranslation($pageUid, $targetLanguageUid);
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
    protected function createPageTranslation(int $pageUid, int $targetLanguageUid): void
    {
        $this->executeLocalizationCommand('pages', $pageUid, $targetLanguageUid);
    }

    /**
     * @throws \Exception
     */
    protected function applyPageMetadataTranslation(int $translatedPageUid, array $translationData): void
    {
        $datamap = [];
        foreach ($this->translatableMetadataFields as $field) {
            if (isset($translationData['metadata'][$field])) {
                $datamap['pages'][$translatedPageUid][$field] = $translationData['metadata'][$field];
            }
        }

        if (!empty($datamap)) {
            $this->executeDataHandler($datamap, []);
        }
    }

    protected function applyContentElementTranslations(int $targetLanguageUid, array $translationData): void
    {
        if (!isset($translationData['content']) || !is_array($translationData['content'])) {
            return;
        }

        foreach ($translationData['content'] as $sourceContentUid => $contentTranslation) {
            try {
                $this->translateContentElement((int)$sourceContentUid, $targetLanguageUid, $contentTranslation);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to translate content element', [
                    'sourceContentUid' => $sourceContentUid,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function translateContentElement(int $sourceContentUid, int $targetLanguageUid, array $translationData): void
    {
        $existingTranslation = $this->getContentElementTranslation($sourceContentUid, $targetLanguageUid);

        if ($existingTranslation) {
            $translationUidArray = [
                'tt_content' => [ $sourceContentUid => $existingTranslation['uid'] ]
            ];
            $this->updateContentElementTranslation($translationUidArray, $translationData['fields']);
        } else {
            $translationUidArray = $this->createContentElementTranslation($sourceContentUid, $targetLanguageUid);
            if (count($translationUidArray) > 0) {
                $this->updateContentElementTranslation($translationUidArray, $translationData['fields']);
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function createContentElementTranslation(int $sourceContentUid, int $targetLanguageUid): array
    {
        return $this->executeLocalizationCommand('tt_content', $sourceContentUid, $targetLanguageUid);
    }

    /**
     * @throws \Exception
     */
    protected function updateContentElementTranslation(array $translatedContentArray, array $fieldData): void
    {
        $datamap = [];

        if (isset($translatedContentArray['tt_content'])) {
            $ttContentFields = [];
            foreach ($fieldData as $fieldName => $fieldValue) {
                if (!isset($translatedContentArray[$fieldName])) {
                    $ttContentFields[$fieldName] = $fieldValue;
                }
            }

            foreach ($translatedContentArray['tt_content'] as $translatedUid) {
                if (!empty($ttContentFields)) {
                    $datamap['tt_content'][$translatedUid] = $ttContentFields;
                }
            }
        }

        foreach ($translatedContentArray as $tableName => $records) {
            if ($tableName === 'tt_content') {
                continue;
            }

            if (isset($fieldData[$tableName]) && is_array($fieldData[$tableName])) {
                $datamap[$tableName] = [];

                foreach ($fieldData[$tableName] as $sourceUid => $tableFieldData) {
                    if (isset($records[$sourceUid])) {
                        $translatedUid = $records[$sourceUid];
                        $datamap[$tableName][$translatedUid] = $tableFieldData;
                    }
                }
            }
        }

        if (!empty($datamap)) {
            $this->executeDataHandler($datamap, []);
        }
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws DBALException
     */
    protected function getContentElementTranslation(int $sourceContentUid, int $targetLanguageUid): ?array
    {
        $queryBuilder = $this->createContentQueryBuilder();
        $result = $queryBuilder
            ->select('uid', 'l18n_parent')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('l18n_parent', $queryBuilder->createNamedParameter($sourceContentUid)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($targetLanguageUid)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @throws \Exception
     */
    protected function applyCompletePageTranslation(int $translatedPageUid, int $targetLanguageUid, array $translationData): void
    {
        if (isset($translationData['metadata'])) {
            $this->applyPageMetadataTranslation($translatedPageUid, $translationData);
        }
        if (isset($translationData['content'])) {
            $this->applyContentElementTranslations($targetLanguageUid, $translationData);
        }
    }

    protected function isValidTranslationResult(array $translationData): bool
    {
        if (empty($translationData)) {
            return false;
        }

        $validScopes = ['metadata', 'content'];
        foreach ($validScopes as $scope) {
            if (isset($translationData[$scope]) && is_array($translationData[$scope])) {
                return true;
            }
        }

        return false;
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

    protected function createContentQueryBuilder(string $table = 'tt_content'): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder;
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
}
