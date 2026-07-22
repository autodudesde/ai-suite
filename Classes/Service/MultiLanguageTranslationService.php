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
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\ContentRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
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
    ) {}

    /**
     * @param null|list<string> $changedFields
     */
    public function translateContentElementToAllLanguages(int $sourceUid, int $pageId, ?ServerRequestInterface $request = null, ?array $changedFields = null): void
    {
        if (!$this->backendUserService->checkPermissions('tx_aisuite_features:enable_auto_translation')) {
            return;
        }

        $extConf = $this->getExtensionConfiguration();
        $model = (string) ($extConf['autoTranslateModel'] ?? 'Deepl');
        if ('' === $model || !$this->backendUserService->checkPermissions('tx_aisuite_models:'.$model)) {
            $this->logger->warning('Auto translation skipped: missing permission for configured model', [
                'model' => $model,
                'sourceUid' => $sourceUid,
            ]);
            $this->addMissingModelPermissionNotification($model);

            return;
        }

        $targetLanguageUids = array_values(array_filter(
            $this->siteService->getNonDefaultLanguageUids($pageId),
            fn (int $languageUid): bool => $this->pagesRepository->checkPageTranslationExists($pageId, $languageUid)
                && !$this->contentRepository->hasFreeModeTranslation($sourceUid, $languageUid)
        ));
        if ([] === $targetLanguageUids) {
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

        if (self::MODE_DIRECT === $mode) {
            $this->translateDirectly($sourceUid, $pageId, $sourceIsoCode, $targetLanguageUids, $model, $request, $changedFields);

            return;
        }

        $this->enqueueTranslationTasks($sourceUid, $pageId, $sourceIsoCode, $targetLanguageUids, $model, $mode, $request, $changedFields);
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
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage($flashMessage);
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
                    $this->logger->error('Auto translation request failed', [
                        'sourceUid' => $sourceUid,
                        'targetLanguageUid' => $targetLanguageUid,
                        'message' => $answer->getResponseData()['message'] ?? '',
                    ]);

                    continue;
                }

                $translationResults = $this->extractTranslationResults($answer->getResponseData());
                if ([] !== $translationResults) {
                    $this->translationService->applyContentElementAutoTranslation($sourceUid, $targetLanguageUid, $translationResults);
                    $translatedLanguages[] = strtoupper($targetIsoCode);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Auto translation (direct) failed for target language', [
                    'sourceUid' => $sourceUid,
                    'targetLanguageUid' => $targetLanguageUid,
                    'error' => $e->getMessage(),
                ]);
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

        $unappliedLanguageUids = $this->backgroundTaskRepository->findUnappliedContentElementTranslationLanguageUids($sourceUid);
        $parentUuid = $this->uuidService->generateUuid();
        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'translation', $pageId);

        foreach ($targetLanguageUids as $targetLanguageUid) {
            if (in_array($targetLanguageUid, $unappliedLanguageUids, true)) {
                continue;
            }

            try {
                $targetIsoCode = $this->siteService->getIsoCodeByLanguageIdIncludingDisabled($targetLanguageUid, $pageId);
                $translatableContent = $this->translationService->prepareContentElementForTranslation($sourceUid, $targetLanguageUid, $request, $changedFields);
                if ([] === $translatableContent) {
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
                }
            } catch (\Throwable $e) {
                $this->logger->error('Auto translation (async) failed for target language', [
                    'sourceUid' => $sourceUid,
                    'targetLanguageUid' => $targetLanguageUid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $responseData
     *
     * @return array<string, mixed>
     */
    protected function extractTranslationResults(array $responseData): array
    {
        $translationResults = $responseData['translationResults'] ?? [];
        if (is_string($translationResults)) {
            $translationResults = json_decode($translationResults, true);
        }

        return is_array($translationResults) ? $translationResults : [];
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
}
