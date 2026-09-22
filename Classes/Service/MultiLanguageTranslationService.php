<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\BackgroundTask;
use AutoDudes\AiSuite\Domain\Model\Dto\ProvenanceContext;
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\ContentRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\TranslationRepository;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MultiLanguageTranslationService
{
    public const SCOPE = 'content-element-translation';

    public const MODE_DIRECT = 'direct';
    public const MODE_PAGE_MODULE = 'pageModule';
    public const MODE_CLI = 'cli';

    public const NOTICE_MISSING_FEATURE_PERMISSION = 'missingFeaturePermission';
    public const NOTICE_FREE_MODE_SKIPPED = 'freeModeSkipped';
    public const NOTICE_PAGE_NOT_TRANSLATED = 'pageNotTranslated';
    public const NOTICE_WORKSPACE_SKIPPED = 'workspaceSkipped';
    public const NOTICE_QUEUED = 'queued';
    public const NOTICE_NO_TRANSLATABLE_FIELDS = 'noTranslatableFields';
    public const NOTICE_REQUEST_FAILED = 'requestFailed';
    public const NOTICE_EMPTY_RESULT = 'emptyResult';

    private const NOTICE_SEVERITIES = [
        self::NOTICE_MISSING_FEATURE_PERMISSION => ContextualFeedbackSeverity::WARNING,
        self::NOTICE_REQUEST_FAILED => ContextualFeedbackSeverity::ERROR,
        self::NOTICE_EMPTY_RESULT => ContextualFeedbackSeverity::WARNING,
        self::NOTICE_NO_TRANSLATABLE_FIELDS => ContextualFeedbackSeverity::WARNING,
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $notices = [];

    public function __construct(
        protected readonly TranslationService $translationService,
        protected readonly GlobalInstructionService $globalInstructionService,
        protected readonly SendRequestService $sendRequestService,
        protected readonly WorkflowProcessingService $workflowProcessingService,
        protected readonly SiteService $siteService,
        protected readonly PagesRepository $pagesRepository,
        protected readonly ContentRepository $contentRepository,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
        protected readonly UuidService $uuidService,
        protected readonly BackendUserService $backendUserService,
        protected readonly CliCommandAvailabilityService $cliCommandAvailabilityService,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly LocalizationService $localizationService,
        protected readonly FlashMessageService $flashMessageService,
        protected readonly LoggerInterface $logger,
        protected readonly ProvenanceCaptureService $provenanceCapture,
        protected readonly TranslationRepository $translationRepository,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
    ) {}

    /**
     * @param null|list<string> $changedFields
     */
    public function translateContentElementToAllLanguages(int $sourceUid, int $pageId, ?ServerRequestInterface $request = null, ?array $changedFields = null): void
    {
        if (!$this->backendUserService->checkPermissions('tx_aisuite_features:enable_auto_translation')) {
            $this->logger->warning('Auto translation skipped: missing permission for automatic translation', [
                'sourceUid' => $sourceUid,
            ]);
            $this->addNotice(self::NOTICE_MISSING_FEATURE_PERMISSION);

            return;
        }

        $extConf = $this->getExtensionConfiguration();
        $model = (string) ($extConf['autoTranslateModel'] ?? 'ChatGPT');
        if ('' === $model || !$this->backendUserService->checkPermissions('tx_aisuite_models:'.$model)) {
            $this->logger->warning('Auto translation skipped: missing permission for configured model', [
                'model' => $model,
                'sourceUid' => $sourceUid,
            ]);
            $this->addMissingModelPermissionNotification($model);

            return;
        }

        $targetLanguageUids = [];
        foreach ($this->siteService->getNonDefaultLanguageUids($pageId) as $languageUid) {
            if (!$this->pagesRepository->checkPageTranslationExists($pageId, $languageUid)) {
                $this->skipLanguage(self::NOTICE_PAGE_NOT_TRANSLATED, LogLevel::NOTICE, $sourceUid, $pageId, $languageUid);

                continue;
            }
            if ($this->contentRepository->hasFreeModeTranslation($sourceUid, $languageUid)) {
                $this->skipLanguage(self::NOTICE_FREE_MODE_SKIPPED, LogLevel::WARNING, $sourceUid, $pageId, $languageUid);

                continue;
            }
            $targetLanguageUids[] = $languageUid;
        }
        if ([] === $targetLanguageUids) {
            $this->logger->notice('Auto translation skipped: no target language qualifies', [
                'sourceUid' => $sourceUid,
                'pageId' => $pageId,
            ]);

            return;
        }

        try {
            $sourceIsoCode = $this->siteService->getIsoCodeByLanguageIdIncludingDisabled(0, $pageId);
        } catch (\Throwable $e) {
            $this->logger->warning('Auto translation skipped: could not resolve source language ISO code', [
                'pageId' => $pageId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $mode = (string) ($extConf['autoTranslateMode'] ?? self::MODE_PAGE_MODULE);

        $this->provenanceCapture->begin(
            ProvenanceContext::translated(ProvenanceContext::FEATURE_TRANSLATION, $model)
        );

        try {
            if (self::MODE_DIRECT === $mode) {
                $this->translateDirectly($sourceUid, $pageId, $sourceIsoCode, $targetLanguageUids, $model, $request, $changedFields);

                return;
            }

            $this->enqueueTranslationTasks($sourceUid, $pageId, $sourceIsoCode, $targetLanguageUids, $model, $mode, $request, $changedFields);
        } finally {
            $this->provenanceCapture->end();
        }
    }

    public function notifyBulkSaveLimitExceeded(int $changedElements, int $cap): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->localizationService->translate('aiSuite.autoTranslation.bulkLimit.message', [$changedElements, $cap]),
            $this->localizationService->translate('aiSuite.autoTranslation.bulkLimit.title'),
            ContextualFeedbackSeverity::WARNING,
            true
        );
        $this->queueOnce($flashMessage);
    }

    public function addNotice(string $notice, ?string $language = null): void
    {
        $this->notices[$notice] ??= [];
        if (null !== $language && !in_array($language, $this->notices[$notice], true)) {
            $this->notices[$notice][] = $language;
        }
    }

    public function flushNotices(): void
    {
        $notices = $this->notices;
        $this->notices = [];

        foreach ($notices as $notice => $languages) {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->localizationService->translate('aiSuite.autoTranslation.'.$notice.'.message', [implode(', ', $languages)]),
                $this->localizationService->translate('aiSuite.autoTranslation.'.$notice.'.title'),
                self::NOTICE_SEVERITIES[$notice] ?? ContextualFeedbackSeverity::INFO,
                true
            );
            $this->queueOnce($flashMessage);
        }
    }

    /**
     * @param list<int>         $targetLanguageUids
     * @param null|list<string> $changedFields
     */
    protected function translateDirectly(int $sourceUid, int $pageId, string $sourceIsoCode, array $targetLanguageUids, string $model, ?ServerRequestInterface $request, ?array $changedFields = null): void
    {
        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'translation', $pageId);
        $translatedLanguages = [];

        foreach ($targetLanguageUids as $targetLanguageUid) {
            try {
                $targetIsoCode = $this->siteService->getIsoCodeByLanguageIdIncludingDisabled($targetLanguageUid, $pageId);
                $translateFields = $this->translationService->prepareContentElementForTranslation($sourceUid, $targetLanguageUid, $request, $changedFields);
                if ([] === $translateFields) {
                    $this->reportMissingTranslatableFields($sourceUid, $targetIsoCode);

                    continue;
                }

                $answer = $this->sendRequestService->sendDataRequest(
                    'translate',
                    [
                        'translate_fields' => (string) json_encode($translateFields, SendRequestService::JSON_SAFE_FLAGS),
                        'translate_fields_count' => count($translateFields),
                        'source_lang' => $sourceIsoCode,
                        'target_lang' => $targetIsoCode,
                        'uuid' => $this->uuidService->generateUuid(),
                        'global_instructions' => $globalInstructions,
                    ],
                    '',
                    strtoupper($targetIsoCode),
                    [
                        'translate' => $model,
                    ]
                );

                if ('Error' === $answer->getType()) {
                    $message = (string) ($answer->getResponseData()['message'] ?? '');
                    $this->logger->error('Auto translation request failed', [
                        'sourceUid' => $sourceUid,
                        'targetLanguageUid' => $targetLanguageUid,
                        'errorType' => $answer->getResponseData()['errorType'] ?? '',
                        'message' => $message,
                    ]);
                    $this->addNotice(self::NOTICE_REQUEST_FAILED, $this->failureLabel($targetIsoCode, $message));

                    continue;
                }

                $translationResults = $this->extractTranslationResults($answer->getResponseData());
                if ([] === $translationResults) {
                    $this->logger->warning('Auto translation returned no usable result', [
                        'sourceUid' => $sourceUid,
                        'targetLanguageUid' => $targetLanguageUid,
                    ]);
                    $this->addNotice(self::NOTICE_EMPTY_RESULT, strtoupper($targetIsoCode));

                    continue;
                }

                $skipped = $this->translationService->applyContentElementAutoTranslation($sourceUid, $targetLanguageUid, $translationResults);
                if ([] !== $skipped) {
                    $this->logger->warning('Auto translation could not be written for every record', [
                        'sourceUid' => $sourceUid,
                        'targetLanguageUid' => $targetLanguageUid,
                        'skipped' => $skipped,
                    ]);
                    $this->translationService->addSkippedWarning($skipped);
                }
                if (!$this->everyRecordWasSkipped($translationResults, $skipped)) {
                    $translatedLanguages[] = strtoupper($targetIsoCode);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Auto translation (direct) failed for target language', [
                    'sourceUid' => $sourceUid,
                    'targetLanguageUid' => $targetLanguageUid,
                    'error' => $e->getMessage(),
                ]);
                $this->addNotice(self::NOTICE_REQUEST_FAILED, $this->failureLabel($this->languageLabel($targetLanguageUid, $pageId), $e->getMessage()));
            }
        }

        $this->addDirectTranslationNotification($translatedLanguages);
    }

    protected function addMissingModelPermissionNotification(string $model): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->localizationService->translate('aiSuite.autoTranslation.missingModelPermission.message', [$model]),
            $this->localizationService->translate('aiSuite.autoTranslation.missingModelPermission.title'),
            ContextualFeedbackSeverity::WARNING,
            true
        );
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage($flashMessage);
    }

    /**
     * @param list<string> $translatedLanguages
     */
    protected function addDirectTranslationNotification(array $translatedLanguages): void
    {
        if ([] === $translatedLanguages) {
            return;
        }

        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->localizationService->translate('aiSuite.autoTranslation.direct.message', [implode(', ', $translatedLanguages)]),
            $this->localizationService->translate('aiSuite.autoTranslation.direct.title'),
            ContextualFeedbackSeverity::INFO,
            true
        );
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage($flashMessage);
    }

    /**
     * @param list<int>         $targetLanguageUids
     * @param null|list<string> $changedFields
     */
    protected function enqueueTranslationTasks(int $sourceUid, int $pageId, string $sourceIsoCode, array $targetLanguageUids, string $model, string $mode, ?ServerRequestInterface $request, ?array $changedFields = null): void
    {
        $handledByCli = self::MODE_CLI === $mode
            && $this->cliCommandAvailabilityService->isCliExecutionAvailable('pageTranslate');

        $unappliedTasks = $this->backgroundTaskRepository->findUnappliedContentElementTranslationTasks($sourceUid);
        $parentUuid = $this->uuidService->generateUuid();
        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'translation', $pageId);

        foreach ($targetLanguageUids as $targetLanguageUid) {
            $supersededTasks = array_values(array_filter(
                $unappliedTasks,
                static fn (array $task): bool => $task['sys_language_uid'] === $targetLanguageUid
            ));

            try {
                $taskChangedFields = $this->hasContentElementTranslation($sourceUid, $targetLanguageUid)
                    ? $this->mergeChangedFields($changedFields, $supersededTasks)
                    : null;
                $targetIsoCode = $this->siteService->getIsoCodeByLanguageIdIncludingDisabled($targetLanguageUid, $pageId);
                $translatableContent = $this->translationService->prepareContentElementForTranslation($sourceUid, $targetLanguageUid, $request, $taskChangedFields);
                if ([] === $translatableContent) {
                    $this->reportMissingTranslatableFields($sourceUid, $targetIsoCode);

                    continue;
                }
                $uuid = $this->uuidService->generateUuid();

                $payload = [[
                    'source_content_uid' => $sourceUid,
                    'source_language' => $sourceIsoCode,
                    'target_language' => $targetIsoCode,
                    'translation_scope' => 'content',
                    'translatable_content' => $translatableContent,
                    'uuid' => $uuid,
                    'global_instructions' => $globalInstructions,
                ]];

                $bulkPayload = [new BackgroundTask(
                    self::SCOPE,
                    'translation',
                    $parentUuid,
                    $uuid,
                    'content',
                    'tt_content',
                    'uid',
                    $sourceUid,
                    $targetLanguageUid,
                    '',
                    handledByCli: $handledByCli,
                    model: $model,
                    changedFields: $taskChangedFields,
                )];

                $errorMessage = $this->workflowProcessingService->sendWorkflowRequest(
                    $payload,
                    $bulkPayload,
                    $parentUuid,
                    self::SCOPE,
                    'translation',
                    '',
                    'translate',
                    $model,
                    $this->sendRequestService,
                    $this->backgroundTaskRepository,
                );

                if (null !== $errorMessage) {
                    $this->logger->error('Auto translation enqueue failed', [
                        'sourceUid' => $sourceUid,
                        'targetLanguageUid' => $targetLanguageUid,
                        'message' => $errorMessage,
                    ]);
                    $this->addNotice(self::NOTICE_REQUEST_FAILED, $this->failureLabel($targetIsoCode, $errorMessage));

                    continue;
                }

                if ([] !== $supersededTasks) {
                    $this->backgroundTaskRepository->deleteByUuids(array_column($supersededTasks, 'uuid'));
                }
                $this->addNotice(self::NOTICE_QUEUED, strtoupper($targetIsoCode));
            } catch (\Throwable $e) {
                $this->logger->error('Auto translation (async) failed for target language', [
                    'sourceUid' => $sourceUid,
                    'targetLanguageUid' => $targetLanguageUid,
                    'error' => $e->getMessage(),
                ]);
                $this->addNotice(self::NOTICE_REQUEST_FAILED, $this->failureLabel($this->languageLabel($targetLanguageUid, $pageId), $e->getMessage()));
            }
        }
    }

    /**
     * @param null|list<string>                                                                   $changedFields
     * @param list<array{uuid: string, sys_language_uid: int, changed_fields: null|list<string>}> $supersededTasks
     *
     * @return null|list<string>
     */
    protected function mergeChangedFields(?array $changedFields, array $supersededTasks): ?array
    {
        if (null === $changedFields) {
            return null;
        }

        $merged = $changedFields;
        foreach ($supersededTasks as $task) {
            if (null === $task['changed_fields']) {
                return null;
            }
            $merged = [...$merged, ...$task['changed_fields']];
        }

        return array_values(array_unique($merged));
    }

    protected function hasContentElementTranslation(int $sourceUid, int $targetLanguageUid): bool
    {
        $parentField = $this->tcaCompatibilityService->getTranslationOriginPointerFieldName('tt_content') ?? 'l18n_parent';

        return null !== $this->translationRepository->getRecordTranslation($sourceUid, $targetLanguageUid, 'tt_content', $parentField);
    }

    protected function reportMissingTranslatableFields(int $sourceUid, string $targetIsoCode): void
    {
        $record = BackendUtility::getRecord('tt_content', $sourceUid, 'CType');
        $cType = (string) ($record['CType'] ?? '');

        $this->logger->warning('Auto translation found no translatable fields', [
            'sourceUid' => $sourceUid,
            'cType' => $cType,
            'targetLanguage' => $targetIsoCode,
        ]);
        $this->addNotice(
            self::NOTICE_NO_TRANSLATABLE_FIELDS,
            strtoupper($targetIsoCode).('' === $cType ? '' : ' ('.$cType.')')
        );
    }

    /**
     * By record, not by count: a refusal names the record it belongs to (`table:uid`), and a list
     * that happens to be as long as the answer says nothing about which records it covers.
     *
     * @param array<string, mixed> $translationResults
     * @param list<string>         $skipped
     */
    protected function everyRecordWasSkipped(array $translationResults, array $skipped): bool
    {
        $refused = array_flip($skipped);
        foreach ($translationResults as $table => $records) {
            if (!is_array($records)) {
                continue;
            }
            foreach (array_keys($records) as $uid) {
                if (!isset($refused[$table.':'.$uid])) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function failureLabel(string $languageLabel, string $message): string
    {
        $message = trim($message);

        return strtoupper($languageLabel).('' === $message ? '' : ': '.$message);
    }

    protected function skipLanguage(string $notice, string $logLevel, int $sourceUid, int $pageId, int $languageUid): void
    {
        $this->logger->log($logLevel, 'Auto translation skipped for target language', [
            'reason' => $notice,
            'sourceUid' => $sourceUid,
            'pageId' => $pageId,
            'targetLanguageUid' => $languageUid,
        ]);
        $this->addNotice($notice, $this->languageLabel($languageUid, $pageId));
    }

    protected function languageLabel(int $languageUid, int $pageId): string
    {
        try {
            return strtoupper($this->siteService->getIsoCodeByLanguageIdIncludingDisabled($languageUid, $pageId));
        } catch (\Throwable $e) {
            return (string) $languageUid;
        }
    }

    /**
     * @param array<string, mixed> $responseData
     *
     * @return array<string, mixed>
     */
    protected function extractTranslationResults(array $responseData): array
    {
        return $this->translationService->extractTranslationResults($responseData);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getExtensionConfiguration(): array
    {
        try {
            $extConf = $this->extensionConfiguration->get('ai_suite');

            return is_array($extConf) ? $extConf : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function queueOnce(FlashMessage $message): void
    {
        $queue = $this->flashMessageService->getMessageQueueByIdentifier();

        try {
            foreach ($queue->getAllMessages() as $queued) {
                if ($queued->getTitle() === $message->getTitle() && $queued->getMessage() === $message->getMessage()) {
                    return;
                }
            }
        } catch (\Throwable) {
            // silent fail
        }

        $queue->addMessage($message);
    }
}
