<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\TranslationRepository;
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
    /** @var list<string> */
    protected array $consideredTextRenderTypes = [
        'input',
        'text',
        'flex',
        'textTable',
    ];

    /** @var list<string> */
    private array $translatableMetadataFields = [
        'title',
        'subtitle',
        'seo_title',
        'description',
        'keywords',
        'og_title',
        'og_description',
        'twitter_title',
        'twitter_description',
    ];

    public function __construct(
        protected readonly ContentService $contentService,
        protected readonly UriBuilder $uriBuilder,
        protected readonly UuidService $uuidService,
        protected readonly SiteFinder $siteFinder,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly IconService $iconService,
        protected readonly SiteService $siteService,
        protected readonly FlexFormTranslationService $flexFormTranslationService,
        protected readonly PagesRepository $pagesRepository,
        protected readonly FlashMessageService $flashMessageService,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
        protected readonly LoggerInterface $logger,
        protected readonly TranslationRepository $translationRepository,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
        protected readonly LocalizationService $localizationService,
        protected readonly BackendUserService $backendUserService,
    ) {}

    /**
     * @param array<string, mixed> $defaultValues
     *
     * @return array<string, mixed>
     */
    public function fetchTranslationFields(?ServerRequestInterface $request, array $defaultValues, int $ceSrcLangUid, string $table): array
    {
        $request ??= $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (null === $request) {
            return [];
        }
        $formData = $this->getFormData($request, $defaultValues, $ceSrcLangUid, $table);
        $translateFields = [];
        $this->getAllFieldsFromTableTypes($table, $formData, $translateFields);

        return $this->contentService->cleanupRequestField($translateFields, $table);
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $translateFields
     */
    public function checkSingleField(
        array $formData,
        string $fieldName,
        array &$translateFields
    ): void {
        if (!is_array($formData['processedTca']['columns'][$fieldName] ?? null) || in_array($fieldName, ContentService::IGNORED_TCA_FIELDS)) {
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
            return;
        }
        if ('flex' === $parameterArray['fieldConf']['config']['type']) {
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
     * @param mixed $table
     * @param mixed $id
     * @param mixed $lUid_OnPage
     * @param mixed $returnUrl
     * @param mixed $pageId
     * @param mixed $flagIcon
     *
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
        $params['redirect'] = $returnUrl;
        $params['cmd'][$table][$id]['localize'] = $lUid_OnPage;
        $params['cmd']['localization'][0]['aiSuite']['srcLangIsoCode'] = $this->siteService->getIsoCodeByLanguageId($site->getDefaultLanguage()->getLanguageId(), $pageId);
        $params['cmd']['localization'][0]['aiSuite']['destLangIsoCode'] = $this->siteService->getIsoCodeByLanguageId($lUid_OnPage, $pageId);
        $params['cmd']['localization'][0]['aiSuite']['destLangId'] = $lUid_OnPage;
        $params['cmd']['localization'][0]['aiSuite']['srcLangId'] = $site->getDefaultLanguage()->getLanguageId();
        $params['cmd']['localization'][0]['aiSuite']['rootPageId'] = $this->siteService->getSiteRootPageId($pageId);
        $params['cmd']['localization'][0]['aiSuite']['translateAi'] = 'AI_SUITE_MODEL';
        $params['cmd']['localization'][0]['aiSuite']['uuid'] = $uuid;
        $params['cmd']['localization'][0]['aiSuite']['pageId'] = $pageId;
        $href = (string) $this->uriBuilder->buildUriFromRoute('tce_db', $params);
        $title = $this->localizationService->translate('aiSuite.translateRecord');

        $lC = $flagIcon
            ? $this->iconService->getIcon($flagIcon, 'small', 'tx-aisuite-extension')->render()
            : $this->iconService->getIcon('tx-aisuite-extension', 'small')->render();

        return '<a href="#" '
            .'class="btn btn-default t3js-action-localize ai-suite-record-localization" '
            .'data-href="'.htmlspecialchars($href).'" '
            .'data-page-id="'.$pageId.'" '
            .'data-uuid="'.$uuid.'" '
            .'title="'.$title.'">'
            .$lC.'</a> ';
    }

    /**
     * @param array<int, array<string, mixed>> $sysLanguages
     *
     * @return list<array<string, mixed>>
     */
    public function getAvailableTargetLanguages(array $sysLanguages, int $pageId): array
    {
        $availableTargetLanguages = [];

        foreach ($sysLanguages as $language) {
            $languageUid = (int) $language['uid'];
            if (0 === $languageUid || -1 === $languageUid) {
                continue;
            }
            if (!$this->pagesRepository->checkPageTranslationExists($pageId, $languageUid)) {
                $availableTargetLanguages[] = $language;
            }
        }

        return $availableTargetLanguages;
    }

    /**
     * @return array<string, mixed>
     */
    public function collectPageTranslatableContent(int $pageUid, int $sourceLanguageUid, string $translationScope, int $targetLanguageUid = 0, ?ServerRequestInterface $request = null): array
    {
        $translatableContent = [];

        switch ($translationScope) {
            case 'metadata':
                $translatableContent['pages'] = $this->collectPageMetadataFields($pageUid, $sourceLanguageUid);

                break;

            case 'content':
                $translatableContent = $this->collectPageContentElementFields($pageUid, $sourceLanguageUid, $targetLanguageUid, $request);

                break;

            case 'all':
                $translatableContent = $this->collectPageContentElementFields($pageUid, $sourceLanguageUid, $targetLanguageUid, $request);
                $translatableContent['pages'] = $this->collectPageMetadataFields($pageUid, $sourceLanguageUid);

                break;
        }

        if (empty($translatableContent)) {
            throw new \RuntimeException('No translatable content found for the specified scope '.$translationScope.' and page.');
        }

        if ('all' === $translationScope) {
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

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function collectTranslatableFieldsWithMapping(
        int $pageUid,
        int $sourceLanguageUid,
        int $targetLanguageUid,
        string $translationScope,
        ?ServerRequestInterface $request = null,
    ): array {
        $translateFields = [];

        // Page metadata
        if ('content' !== $translationScope) {
            $translatedPageUid = $this->findOrCreateLocalization('pages', $pageUid, $targetLanguageUid, 'l10n_parent');
            if (null !== $translatedPageUid) {
                $metadataFields = $this->collectPageMetadataFields($pageUid, $sourceLanguageUid);
                if (!empty($metadataFields)) {
                    $translateFields['pages'][$translatedPageUid] = $metadataFields;
                }
            }
        }

        // Content elements
        if ('metadata' !== $translationScope) {
            $contentElements = $this->translationRepository->getElementsOnPage($pageUid, $sourceLanguageUid);
            foreach ($contentElements as $contentElement) {
                $sourceUid = (int) $contentElement['uid'];
                $translatedUid = $this->findOrCreateLocalization('tt_content', $sourceUid, $targetLanguageUid, 'l18n_parent');
                if (null === $translatedUid) {
                    continue;
                }

                $fields = $this->fetchTranslationFields(
                    $request,
                    ['sys_language_uid' => $targetLanguageUid],
                    $sourceUid,
                    'tt_content',
                );
                $fields = array_filter($fields, static function ($field) {
                    return !\is_array($field) || isset($field['data']);
                });
                if (!empty($fields)) {
                    $translateFields['tt_content'][$translatedUid] = $fields;
                }
            }
        }

        if (empty($translateFields)) {
            throw new \RuntimeException('No translatable content found for the specified scope and page.');
        }

        return $translateFields;
    }

    /**
     * @param list<array<string, mixed>> $tasks Background tasks from BackgroundTaskRepository::findByParentUuid()
     *
     * @return array{applied: int, errors: string[]}
     */
    public function applyBatchTranslationResults(array $tasks): array
    {
        $applied = 0;
        $errors = [];

        foreach ($tasks as $task) {
            if ('finished' !== ($task['status'] ?? '')) {
                continue;
            }

            $answer = json_decode((string) ($task['answer'] ?? ''), true);
            $translationData = $answer['body']['translationResults'] ?? [];
            if (empty($translationData)) {
                continue;
            }

            $pageUid = (int) $task['table_uid'];
            $targetLanguageUid = (int) $task['sys_language_uid'];
            $translationScope = $task['column'] ?? 'all';

            try {
                $datamap = [];

                // Page metadata
                if ('content' !== $translationScope && isset($translationData['pages'])) {
                    $translatedPageUid = $this->findOrCreateLocalization('pages', $pageUid, $targetLanguageUid, 'l10n_parent');
                    if (null !== $translatedPageUid) {
                        foreach ($this->translatableMetadataFields as $field) {
                            if (isset($translationData['pages'][$field])) {
                                $datamap['pages'][$translatedPageUid][$field] = $translationData['pages'][$field];
                            }
                        }
                    }
                }

                // Content elements — keys are source UIDs, need mapping to translation UIDs
                $contentData = $translationData;
                unset($contentData['pages']);

                if ('metadata' !== $translationScope && !empty($contentData)) {
                    foreach ($contentData as $table => $elements) {
                        $parentField = 'tt_content' === $table ? 'l18n_parent' : 'l10n_parent';
                        foreach ($elements as $sourceUid => $fields) {
                            $translatedUid = $this->findOrCreateLocalization($table, (int) $sourceUid, $targetLanguageUid, $parentField);
                            if (null !== $translatedUid) {
                                $datamap[$table][$translatedUid] = $fields;
                            }
                        }
                    }
                }

                if (!empty($datamap)) {
                    $this->executeDataHandler($datamap, []);
                    ++$applied;

                    // Update page slug for translated page
                    if (isset($datamap['pages'])) {
                        $translatedPageUid = (int) array_key_first($datamap['pages']);
                        if ($translatedPageUid > 0) {
                            $this->updatePageSlug($translatedPageUid);
                        }
                    }
                }

                // Clean up processed task
                $this->backgroundTaskRepository->deleteByUuid($task['uuid']);
            } catch (\Throwable $e) {
                $errors[] = sprintf('Page %d: %s', $pageUid, $e->getMessage());
                $this->logger->error('Failed to apply batch translation', [
                    'uuid' => $task['uuid'],
                    'pageUid' => $pageUid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    public function getPageIdFromRequest(ServerRequestInterface $request): int
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $parsedBodyArray = is_array($parsedBody) ? $parsedBody : [];
        $directId = $queryParams['id'] ?? $parsedBodyArray['id'] ?? null;
        if (null !== $directId) {
            return (int) $directId;
        }

        $editPages = $queryParams['edit']['pages'] ?? $parsedBodyArray['edit']['pages'] ?? null;
        if ($editPages && is_array($editPages)) {
            $pageIds = array_keys($editPages);

            return (int) $pageIds[0];
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $backgroundTasks
     */
    public function addTranslationNotifications(array $backgroundTasks, int $pageId): string
    {
        $taskData = $backgroundTasks['translation'][$pageId] ?? null;
        if (!$taskData) {
            return '';
        }

        $status = $taskData['status'] ?? '';
        if ('' === $status) {
            return '';
        }

        $uuid = $taskData['uuid'] ?? '';
        $flashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages');

        $notificationConfig = [
            'pending' => [
                'message' => 'aiSuite.notification.translation.pending.message',
                'title' => 'aiSuite.notification.translation.pending.title',
                'severity' => ContextualFeedbackSeverity::NOTICE,
            ],
            'task-error' => [
                'message' => 'aiSuite.notification.translation.failed.message',
                'title' => 'aiSuite.notification.translation.failed.title',
                'severity' => ContextualFeedbackSeverity::ERROR,
            ],
        ];

        if (isset($notificationConfig[$status])) {
            $config = $notificationConfig[$status];
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->localizationService->translate($config['message']),
                $this->localizationService->translate($config['title']),
                $config['severity']
            );
            $flashMessageQueue->addMessage($message);

            if (!empty($uuid)) {
                return ('pending' === $status ? 'pending__' : 'error__').$uuid;
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
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
                    'message' => '',
                ];
            }

            foreach ($finishedTasks as $task) {
                try {
                    $this->processTranslationTask($task);
                    ++$processedCount;
                } catch (\Exception $e) {
                    ++$errorCount;
                    $this->logger->error('Error processing translation task', [
                        'uuid' => $task['uuid'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $success = 0 === $errorCount;
            $message = $this->buildResultMessage($processedCount, $errorCount);

            return [
                'success' => $success,
                'processedCount' => $processedCount,
                'errorCount' => $errorCount,
                'message' => $message,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error processing finished translation tasks for page', [
                'pageUid' => $pageUid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'processedCount' => $processedCount,
                'errorCount' => $errorCount + 1,
                'message' => 'Error processing translation tasks: '.$e->getMessage(),
            ];
        }
    }

    public function getLanguageService(): LanguageService
    {
        return $this->localizationService->getLanguageService();
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
                        'slug' => $slug,
                    ],
                ],
            ];

            $this->executeDataHandler($datamap, []);
        }
    }

    /**
     * @param array<string, mixed> $conf
     * @param array<string, mixed> $copyMappingArray
     * @param array<string, mixed> $row
     */
    public function copyRecord_procBasedOnFieldType(array &$copyMappingArray, string $table, int $uid, string $value, array $row, array $conf, int $language = 0): void
    {
        $relationFieldType = $this->getRelationFieldType($conf);
        if ($this->isReferenceField($conf) || 'mm' === $relationFieldType) {
            $this->copyRecord_processManyToMany($copyMappingArray, $table, $uid, $value, $conf, $language);
        } elseif (false !== $relationFieldType) {
            $this->copyRecord_processRelation($copyMappingArray, $table, $uid, $value, $row, $conf, $language);
        }
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $translateFields
     */
    protected function getAllFieldsFromTableTypes(string $table, array $formData, array &$translateFields, bool $extendendMode = true): void
    {
        $types = $GLOBALS['TCA'][$table]['types'] ?? [];

        if (empty($types)) {
            return;
        }

        if ('tt_content' === $table) {
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
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $translateFields
     */
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
                if ('--linebreak--' === $fieldName) {
                    continue;
                }
                $this->checkSingleField($formData, $fieldName, $translateFields);
            }
        }
    }

    /**
     * @param array<string, mixed> $defaultValues
     *
     * @return array<string, mixed>
     */
    protected function getFormData(ServerRequestInterface $request, array $defaultValues, int $ceSrcLangUid, string $table): array
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
     * @param list<string>         $fieldsArray
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $translateFields
     *
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
            if ('--palette--' === $fieldName) {
                $this->createPaletteContentArray($fieldConfiguration['paletteName'] ?? '', $translateFields, $formData, $table);
            } elseif (
                $this->extensionConfiguration->get('ai_suite', 'translateFlexFormFields')
                && ('pi_flexform' === $fieldName || ($formData['processedTca']['columns'][$fieldName]['config']['type'] ?? '') === 'flex')
            ) {
                $this->flexFormTranslationService->convertFlexFormToTranslateFields($formData, $translateFields, $fieldName);
            } else {
                if (!is_array($formData['processedTca']['columns'][$fieldName] ?? null)) {
                    continue;
                }
                $this->checkSingleField($formData, $fieldName, $translateFields);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
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
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    protected function collectPageContentElementFields(int $pageUid, int $sourceLanguageUid, int $targetLanguageUid = 0, ?ServerRequestInterface $request = null): array
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
                        $request,
                        [
                            'sys_language_uid' => $targetLanguageUid,
                        ],
                        (int) $sourceUid,
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
     * @param list<array<string, mixed>> $sourceElements
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    protected function filterUntranslatedContentElements(array $sourceElements, int $pageUid, int $targetLanguageUid): array
    {
        if (empty($sourceElements)) {
            return $sourceElements;
        }

        $targetElements = $this->translationRepository->getTranslatedElementsOnPage($pageUid, $targetLanguageUid);
        $translatedParentUids = array_column($targetElements, 'l18n_parent');

        return array_values(array_filter($sourceElements, function ($element) use ($translatedParentUids) {
            return !in_array((int) $element['uid'], $translatedParentUids);
        }));
    }

    /**
     * @param array<string, mixed> $task
     */
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
            if (0 === $affectedRows) {
                throw new \Exception($this->localizationService->translate('aiSuite.error.backgroundTask.notFound', [$task['uuid']]));
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing translation task: '.$e->getMessage(), [
                'uuid' => $task['uuid'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $translationData
     *
     * @throws Exception
     * @throws \Exception
     */
    protected function applyTranslationResult(array $task, array $translationData): void
    {
        $pageUid = (int) $task['table_uid'];
        $targetLanguageUid = (int) $task['sys_language_uid'];
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
                throw new \Exception('Unknown translation scope: '.$translationScope);
        }
        BackendUtility::setUpdateSignal('updatePageTree', $pageUid);
    }

    /**
     * @param array<string, mixed> $translationData
     *
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

    /**
     * @param array<string, mixed> $translationData
     */
    protected function applyContentElementTranslations(int $targetLanguageUid, array $translationData): void
    {
        $datamap = [];
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
                    $languageParentField = 'tt_content' === $table ? 'l18n_parent' : 'l10n_parent';
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
     * @param array<string, mixed> $translationData
     *
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

    protected function buildResultMessage(int $processedCount, int $errorCount): string
    {
        if (0 === $processedCount && 0 === $errorCount) {
            return '';
        }

        $messages = [];
        if ($processedCount > 0) {
            $messages[] = 1 === $processedCount
                ? 'Successfully processed 1 translation task'
                : "Successfully processed {$processedCount} translation tasks";
        }
        if ($errorCount > 0) {
            $messages[] = 1 === $errorCount
                ? '1 task failed'
                : "{$errorCount} tasks failed";
        }

        return implode(', ', $messages).'.';
    }

    /**
     * @param array<string, mixed> $cmdmap
     * @param array<string, mixed> $datamap
     *
     * @throws \Exception
     */
    protected function executeDataHandler(array $datamap, array $cmdmap): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, $cmdmap);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();

        if (!empty($dataHandler->errorLog)) {
            throw new \Exception('DataHandler error: '.implode(', ', $dataHandler->errorLog));
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    protected function executeLocalizationCommand(string $table, int $uid, int $targetLanguageUid): array
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $cmd = [
            $table => [
                $uid => [
                    'localize' => $targetLanguageUid,
                ],
            ],
        ];

        $dataHandler->start([], $cmd);
        $dataHandler->process_cmdmap();

        if (!empty($dataHandler->errorLog)) {
            throw new \Exception("Error creating {$table} translation: ".implode(', ', $dataHandler->errorLog));
        }

        return $dataHandler->copyMappingArray_merged;
    }

    /**
     * @param array<string, mixed> $copyMappingArray
     */
    protected function localize(array &$copyMappingArray, string $table, int $uid, int $language): void
    {
        if (!$this->tcaCompatibilityService->hasTable($table) || !$uid) {
            return;
        }

        if (!$this->tcaCompatibilityService->isLanguageAware($table)) {
            return;
        }

        $languageFieldName = $this->tcaCompatibilityService->getLanguageFieldName($table);
        $translationOriginPointerFieldName = $this->tcaCompatibilityService->getTranslationOriginPointerFieldName($table);
        if (null === $languageFieldName || null === $translationOriginPointerFieldName) {
            return;
        }

        // Getting workspace overlay if possible - this will localize versions in workspace if any
        $row = BackendUtility::getRecord($table, $uid);
        BackendUtility::workspaceOL($table, $row, $this->backendUserService->getBackendUser()?->workspace ?? 0);
        if (!is_array($row)) {
            return;
        }
        $pageRecord = [];
        if ('pages' === $table) {
            $pageRecord = $row;
        } elseif ((int) $row['pid'] > 0) {
            $pageRecord = BackendUtility::getRecord('pages', $row['pid']);
            if (!is_array($pageRecord)) {
                return;
            }
        }
        if ([] === $pageRecord && 0 === $row['pid'] && !($this->backendUserService->getBackendUser()?->isAdmin() || BackendUtility::isRootLevelRestrictionIgnored($table))
        ) {
            return;
        }

        [$pageId] = BackendUtility::getTSCpid($table, $uid, '');
        // Try to fetch the site language from the pages' associated site
        $siteLanguage = $this->getSiteLanguageForPage((int) $pageId, $language);
        if (null === $siteLanguage) {
            return;
        }

        // Make sure that records which are translated from another language than the default language have a correct
        // localization source set themselves, before translating them to another language.
        if (0 !== (int) $row[$translationOriginPointerFieldName]
            && $row[$languageFieldName] > 0) {
            $localizationParentRecord = BackendUtility::getRecord(
                $table,
                $row[$translationOriginPointerFieldName]
            );
            if (null === $localizationParentRecord || 0 !== (int) $localizationParentRecord[$languageFieldName]) {
                return;
            }
        }

        // Default language records must never have a localization parent as they are the origin of any translation.
        if (0 !== (int) $row[$translationOriginPointerFieldName]
            && 0 === (int) $row[$languageFieldName]) {
            return;
        }

        $overrideValues = [];
        $overrideValues[$languageFieldName] = $language;
        if (0 === (int) $row[$languageFieldName]) {
            $overrideValues[$translationOriginPointerFieldName] = $uid;
        }
        $translationSourceFieldName = $this->tcaCompatibilityService->getTranslationSourceFieldName($table);
        if (null !== $translationSourceFieldName) {
            $overrideValues[$translationSourceFieldName] = $uid;
        }
        $subSchemaDivisorFieldName = $this->tcaCompatibilityService->getSubSchemaDivisorFieldName($table);
        if (null !== $subSchemaDivisorFieldName) {
            $overrideValues[$subSchemaDivisorFieldName] = $row[$subSchemaDivisorFieldName] ?? null;
        }
        foreach ($this->tcaCompatibilityService->getPrefixLanguageTitleFields($table) as $fieldName) {
            if ('' !== (string) ($row[$fieldName] ?? '')) {
                $overrideValues[$fieldName] = $row[$fieldName] ?? null;
            }
        }
        foreach ($this->tcaCompatibilityService->getMMFieldsNeedingZeroOverride($table) as $fieldName) {
            $overrideValues[$fieldName] = 0;
        }

        if ('pages' !== $table) {
            $this->copyRecord($copyMappingArray, $table, $uid, $overrideValues, '', $language);
        }
    }

    /**
     * @param array<string, mixed> $copyMappingArray
     * @param array<string, mixed> $overrideValues
     */
    protected function copyRecord(array &$copyMappingArray, string $table, int $uid, array $overrideValues = [], string $excludeFields = '', int $language = 0, bool $ignoreLocalization = false): void
    {
        $uid = ($origUid = $uid);
        if (!$this->tcaCompatibilityService->hasTable($table) || 0 === $uid) {
            return;
        }

        $row = BackendUtility::getRecord($table, $uid);
        if (!is_array($row)) {
            return;
        }
        BackendUtility::workspaceOL($table, $row, $this->backendUserService->getBackendUser()?->workspace ?? 0);
        if (!is_array($row)) {
            return;
        }
        $pageRecord = [];
        if ('pages' === $table) {
            $pageRecord = $row;
        } elseif ((int) $row['pid'] > 0) {
            $pageRecord = BackendUtility::getRecord('pages', $row['pid']);
            if (!is_array($pageRecord)) {
                return;
            }
        }
        if ([] === $pageRecord && 0 === $row['pid'] && !($this->backendUserService->getBackendUser()?->isAdmin() || BackendUtility::isRootLevelRestrictionIgnored($table))
        ) {
            return;
        }

        $fullLanguageCheckNeeded = 'pages' !== $table;
        $backendUser = $this->backendUserService->getBackendUser();
        if (!$ignoreLocalization && ($language <= 0 || !$backendUser?->checkLanguageAccess($language)) && !$backendUser?->recordEditAccessInternals($table, $row, false, null, $fullLanguageCheckNeeded)) {
            return;
        }

        $nonFields = array_unique(GeneralUtility::trimExplode(',', 'uid,perms_userid,perms_groupid,perms_user,perms_group,perms_everybody,t3ver_oid,t3ver_wsid,t3ver_state,t3ver_stage,'.$excludeFields, true));
        BackendUtility::workspaceOL($table, $row, $this->backendUserService->getBackendUser()?->workspace ?? 0);
        if (!is_array($row)) {
            return;
        }
        if (BackendUtility::isTableWorkspaceEnabled($table)
            && ($this->backendUserService->getBackendUser()?->workspace ?? 0) > 0
            && VersionState::DELETE_PLACEHOLDER === VersionState::tryFrom($row['t3ver_state'] ?? 0)
        ) {
            return;
        }
        $row = BackendUtility::purgeComputedPropertiesFromRecord($row);

        foreach ($row as $field => $value) {
            if (!in_array($field, $nonFields, true)) {
                if (array_key_exists($field, $overrideValues)) {
                    continue;
                }
                $conf = $this->tcaCompatibilityService->getFieldConfiguration($table, $field);
                $this->copyRecord_procBasedOnFieldType($copyMappingArray, $table, $uid, (string) ($value ?? ''), $row, $conf, $language);
            }
        }
        $copyMappingArray[$table][$origUid] = 1;
    }

    protected function getSiteLanguageForPage(int $pageId, int $languageId): ?SiteLanguage
    {
        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);

            return $site->getLanguageById($languageId);
        } catch (\InvalidArgumentException|SiteNotFoundException $e) {
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

    /**
     * @param array<string, mixed> $conf
     * @param array<string, mixed> $copyMappingArray
     * @param array<string, mixed> $row
     */
    protected function copyRecord_processRelation(
        array &$copyMappingArray,
        string $table,
        int $uid,
        string $value,
        array $row,
        array $conf,
        int $language
    ): void {
        $dbAnalysis = $this->createRelationHandlerInstance();
        $dbAnalysis->start($value, $conf['foreign_table'], '', $uid, $table, $conf);
        $languageFieldName = $this->tcaCompatibilityService->getLanguageFieldName($table);
        foreach ($dbAnalysis->itemArray as $k => $v) {
            // If language is set and differs from original record, this isn't a copy action but a localization of our parent/ancestor:
            if ($language > 0 && $this->tcaCompatibilityService->isLanguageAware($table) && null !== $languageFieldName && $language != ($row[$languageFieldName] ?? 0)) {
                // Children should be localized when the parent gets localized the first time, just do it:
                $this->localize($copyMappingArray, $v['table'], $v['id'], $language);
            }
        }
    }

    /**
     * @param array<string, mixed> $conf
     * @param array<string, mixed> $copyMappingArray
     */
    protected function copyRecord_processManyToMany(array &$copyMappingArray, string $table, int $uid, string $value, array $conf, int $language): void
    {
        $allowedTables = 'group' === $conf['type'] ? $conf['allowed'] : $conf['foreign_table'];
        $allowedTablesArray = GeneralUtility::trimExplode(',', $allowedTables, true);
        $mmTable = !empty($conf['MM']) ? $conf['MM'] : '';

        $dbAnalysis = $this->createRelationHandlerInstance();
        $dbAnalysis->start($value, $allowedTables, $mmTable, $uid, $table, $conf);

        // Check if referenced records of select or group fields should also be localized in general.
        // A further check is done in the loop below for each table name.
        if ($language > 0 && '' === $mmTable && !empty($conf['localizeReferencesAtParentLocalization'])) {
            // Check whether allowed tables can be localized.
            $localizeTables = [];
            foreach ($allowedTablesArray as $allowedTable) {
                $localizeTables[$allowedTable] = $this->tcaCompatibilityService->isLanguageAware($allowedTable);
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
        $relationHandler->setWorkspaceId($this->backendUserService->getBackendUser()?->workspace ?? 0);
        $relationHandler->setUseLiveReferenceIds($isWorkspacesLoaded);
        $relationHandler->setUseLiveParentIds($isWorkspacesLoaded);

        return $relationHandler;
    }

    /**
     * @param array<string, mixed> $conf
     */
    protected function getRelationFieldType(array $conf): bool|string
    {
        if (
            empty($conf['foreign_table'])
            || !in_array($conf['type'] ?? '', ['inline', 'file'], true)
            || ('file' === $conf['type'] && !($conf['foreign_field'] ?? false))
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

    /**
     * @param array<string, mixed> $conf
     */
    protected function isReferenceField(array $conf): bool
    {
        if (!isset($conf['type'])) {
            return false;
        }

        return ('group' === $conf['type']) || (('select' === $conf['type'] || 'category' === $conf['type']) && !empty($conf['foreign_table']));
    }

    private function findOrCreateLocalization(string $table, int $sourceUid, int $targetLanguageUid, string $parentField): ?int
    {
        $existing = $this->translationRepository->getRecordTranslation($sourceUid, $targetLanguageUid, $table, $parentField);
        if (null !== $existing) {
            return (int) $existing['uid'];
        }

        $dh = GeneralUtility::makeInstance(DataHandler::class);
        $dh->start([], [$table => [$sourceUid => ['localize' => $targetLanguageUid]]]);
        $dh->process_cmdmap();

        $translatedUid = $dh->copyMappingArray_merged[$table][$sourceUid] ?? null;

        return null !== $translatedUid ? (int) $translatedUid : null;
    }
}
