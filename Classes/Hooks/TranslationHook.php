<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\GlobalInstructionService;
use AutoDudes\AiSuite\Service\GlossarService;
use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationHook
{
    public function __construct(
        protected readonly TranslationService $translationService,
        protected readonly LocalizationService $localizationService,
        protected readonly SendRequestService $sendRequestService,
        protected readonly FlashMessageService $flashMessageService,
        protected readonly ConnectionPool $connectionPool,
        protected readonly GlossarService $glossarService,
        protected readonly LoggerInterface $logger,
        protected readonly MetadataService $metadataService,
        protected readonly PagesRepository $pagesRepository,
        protected readonly GlobalInstructionService $globalInstructionService,
    ) {}

    /**
     * @throws AspectNotFoundException
     * @throws Exception
     */
    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        try {
            if (isset($dataHandler->cmdmap['localization'][0]['aiSuite'])) {
                $aiSuiteConfig = $dataHandler->cmdmap['localization'][0]['aiSuite'];

                if ($this->isWholePageTranslation($aiSuiteConfig)) {
                    $this->processWholePageTranslation($dataHandler, $aiSuiteConfig);
                } else {
                    $this->processSingleRecordTranslation($dataHandler, $aiSuiteConfig);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in TranslationHook: '.$e->getMessage());
            $this->addErrorFlashMessage();
        }
    }

    /**
     * @param array<string, mixed> $aiSuiteConfig
     */
    protected function isWholePageTranslation(array $aiSuiteConfig): bool
    {
        return isset($aiSuiteConfig['wholePageMode']) && true === $aiSuiteConfig['wholePageMode'];
    }

    /**
     * @param array<string, mixed> $aiSuiteConfig
     */
    protected function processWholePageTranslation(DataHandler $dataHandler, array $aiSuiteConfig): void
    {
        $pageId = (int) $aiSuiteConfig['pageId'];
        $destLangId = (int) $aiSuiteConfig['destLangId'];

        if ($pageId <= 0) {
            return;
        }

        $allTranslateFields = $this->collectAllTranslatableContent($pageId, $destLangId, $dataHandler);

        if (empty($allTranslateFields)) {
            return;
        }

        $this->sendTranslationRequest($allTranslateFields, $aiSuiteConfig, $dataHandler, $pageId);
    }

    /**
     * @param array<string, mixed> $aiSuiteConfig
     */
    protected function processSingleRecordTranslation(DataHandler $dataHandler, array $aiSuiteConfig): void
    {
        $srcLangIsoCode = $aiSuiteConfig['srcLangIsoCode'];
        $destLangIsoCode = $aiSuiteConfig['destLangIsoCode'];
        $srcLangId = (int) $aiSuiteConfig['srcLangId'];
        $destLangId = (int) $aiSuiteConfig['destLangId'];
        $translateAi = $aiSuiteConfig['translateAi'];
        $rootPageId = (int) $aiSuiteConfig['rootPageId'];
        $pageId = (int) $aiSuiteConfig['pageId'];

        $request = $GLOBALS['TYPO3_REQUEST'];
        $translateFields = [];
        $elementsCount = 0;
        foreach ($dataHandler->copyMappingArray_merged as $tableKey => $table) {
            foreach ($table as $ceSrcLangUid => $ceDestLangUid) {
                $fields = $this->translationService->fetchTranslationFields($request, [], $ceSrcLangUid, $tableKey);
                $fields = array_filter($fields, function ($field) {
                    return !is_array($field) || isset($field['data']);
                });
                if (count($fields) > 0) {
                    $translateFields[$tableKey][$ceDestLangUid] = $fields;
                    ++$elementsCount;
                }
            }
        }
        if (empty($translateFields)) {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->localizationService->translate('aiSuite.translation.noTranslatableFields'),
                '',
                ContextualFeedbackSeverity::WARNING,
                true
            );
            $this->flashMessageService
                ->getMessageQueueByIdentifier()
                ->addMessage($flashMessage)
            ;

            return;
        }

        $translateFieldsJson = (string) json_encode($translateFields, SendRequestService::JSON_SAFE_FLAGS);

        $glossarEntries = $this->glossarService->findGlossarEntries($translateFieldsJson, $destLangId, $srcLangId);
        $glossary = $this->glossarService->findDeeplGlossary($rootPageId, $srcLangId, $destLangId);

        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'translation', $pageId);

        $answer = $this->sendRequestService->sendDataRequest(
            'translate',
            [
                'translate_fields' => $translateFieldsJson,
                'translate_fields_count' => $elementsCount,
                'glossary' => json_encode($glossarEntries, SendRequestService::JSON_SAFE_FLAGS),
                'source_lang' => $srcLangIsoCode,
                'target_lang' => $destLangIsoCode,
                'uuid' => $aiSuiteConfig['uuid'] ?? '',
                'deepl_glossary_id' => $glossary['glossar_uuid'] ?? '',
                'global_instructions' => $globalInstructions,
            ],
            '',
            strtoupper($destLangIsoCode),
            [
                'translate' => $translateAi,
            ]
        );
        if ('Error' === $answer->getType()) {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $answer->getResponseData()['message'],
                '',
                ContextualFeedbackSeverity::ERROR,
                true
            );
            $this->flashMessageService
                ->getMessageQueueByIdentifier()
                ->addMessage($flashMessage)
            ;
        } else {
            $translationResults = is_array($answer->getResponseData()['translationResults']) ? $answer->getResponseData()['translationResults'] : json_decode($answer->getResponseData()['translationResults'], true);
            $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $localDataHandler->start($translationResults, [], $dataHandler->BE_USER);
            $localDataHandler->process_datamap();
            $errorLog = $localDataHandler->errorLog;
            if (count($errorLog) > 0) {
                $this->addErrorFlashMessage();
            }
        }
    }

    /**
     * Collects page properties and all localized content element fields of a
     * whole-page translation, so they can be sent to the AI in one request.
     *
     * @return array<string, mixed>
     */
    protected function collectAllTranslatableContent(int $pageId, int $destLangId, DataHandler $dataHandler): array
    {
        $allTranslateFields = [];

        $pageMetadata = $this->metadataService->collectPageMetadataFields($pageId);
        if (!empty($pageMetadata)) {
            $targetPageUid = $this->resolveTargetPageUid($dataHandler, $pageId, $destLangId);
            if ($targetPageUid > 0) {
                $allTranslateFields['pages'][$targetPageUid] = $pageMetadata;
            }
        }

        $request = $GLOBALS['TYPO3_REQUEST'];
        foreach ($dataHandler->copyMappingArray_merged as $tableKey => $table) {
            if ('pages' === $tableKey) {
                continue;
            }
            foreach ($table as $ceSrcLangUid => $ceDestLangUid) {
                $fields = $this->translationService->fetchTranslationFields($request, [], $ceSrcLangUid, $tableKey);
                $fields = array_filter($fields, function ($field) {
                    return !is_array($field) || isset($field['data']);
                });
                if (count($fields) > 0) {
                    $allTranslateFields[$tableKey][$ceDestLangUid] = $fields;
                }
            }
        }

        return $allTranslateFields;
    }

    /**
     * Resolves the uid of the translated page record. When the page was localized
     * in the current DataHandler run it is read from the copy mapping, otherwise
     * an already existing translation is looked up.
     */
    protected function resolveTargetPageUid(DataHandler $dataHandler, int $sourcePageId, int $destLangId): int
    {
        $mappedUid = (int) ($dataHandler->copyMappingArray_merged['pages'][$sourcePageId] ?? 0);
        if ($mappedUid > 0) {
            return $mappedUid;
        }

        return (int) ($this->pagesRepository->getPageTranslationUid($sourcePageId, $destLangId) ?? 0);
    }

    /**
     * @param array<string, mixed> $aiSuiteConfig
     * @param array<string, mixed> $allTranslateFields
     */
    protected function sendTranslationRequest(array $allTranslateFields, array $aiSuiteConfig, DataHandler $dataHandler, int $pageId): void
    {
        $translateFields = (string) json_encode($allTranslateFields, SendRequestService::JSON_SAFE_FLAGS);
        $elementsCount = $this->countTranslatableElements($allTranslateFields);

        $srcLangId = (int) $aiSuiteConfig['srcLangId'];
        $destLangId = (int) $aiSuiteConfig['destLangId'];
        $srcLangIsoCode = $aiSuiteConfig['srcLangIsoCode'];
        $destLangIsoCode = $aiSuiteConfig['destLangIsoCode'];
        $translateAi = $aiSuiteConfig['translateAi'];
        $rootPageId = (int) $aiSuiteConfig['rootPageId'];

        $glossarEntries = $this->glossarService->findGlossarEntries($translateFields, $destLangId, $srcLangId);
        $glossary = $this->glossarService->findDeeplGlossary($rootPageId, $srcLangId, $destLangId);

        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'translation', $pageId);

        $answer = $this->sendRequestService->sendDataRequest(
            'translate',
            [
                'translate_fields' => $translateFields,
                'translate_fields_count' => $elementsCount,
                'glossary' => json_encode($glossarEntries, SendRequestService::JSON_SAFE_FLAGS),
                'source_lang' => $srcLangIsoCode,
                'target_lang' => $destLangIsoCode,
                'uuid' => $aiSuiteConfig['uuid'] ?? '',
                'deepl_glossary_id' => $glossary['glossar_uuid'] ?? '',
                'whole_page_mode' => true,
                'scope' => $aiSuiteConfig['scope'] ?? '',
                'global_instructions' => $globalInstructions,
            ],
            '',
            strtoupper($destLangIsoCode),
            [
                'translate' => $translateAi,
            ]
        );

        if ('Error' === $answer->getType()) {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $answer->getResponseData()['message'],
                '',
                ContextualFeedbackSeverity::ERROR,
                true
            );
            $this->flashMessageService
                ->getMessageQueueByIdentifier()
                ->addMessage($flashMessage)
            ;
        } else {
            $translationResults = is_array($answer->getResponseData()['translationResults']) ? $answer->getResponseData()['translationResults'] : json_decode($answer->getResponseData()['translationResults'], true);
            $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $localDataHandler->start($translationResults, [], $dataHandler->BE_USER);
            $localDataHandler->process_datamap();
            $errorLog = $localDataHandler->errorLog;
            if (count($errorLog) > 0) {
                $this->addErrorFlashMessage();
            } else {
                $pageUid = (int) array_key_first($allTranslateFields['pages'] ?? []);
                if ($pageUid > 0) {
                    $this->translationService->updatePageSlug($pageUid);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $allTranslateFields
     */
    protected function countTranslatableElements(array $allTranslateFields): int
    {
        $count = 0;
        foreach ($allTranslateFields as $records) {
            $count += count($records);
        }

        return $count;
    }

    private function addErrorFlashMessage(): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->localizationService->translate('aiSuite.translation.failed'),
            '',
            ContextualFeedbackSeverity::ERROR,
            true
        );
        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->addMessage($flashMessage)
        ;
    }
}
