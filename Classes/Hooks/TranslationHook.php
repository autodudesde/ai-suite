<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\GlossarService;
use AutoDudes\AiSuite\Service\MetadataService;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationHook
{
    protected TranslationService $translationService;
    protected SendRequestService $sendRequestService;
    protected FlashMessageService $flashMessageService;
    protected ConnectionPool $connectionPool;
    protected GlossarService $glossarService;
    protected LoggerInterface $logger;
    protected MetadataService $metadataService;

    protected PagesRepository $pagesRepository;

    public function __construct(
        TranslationService $translationService,
        SendRequestService $sendRequestService,
        FlashMessageService $flashMessageService,
        ConnectionPool $connectionPool,
        GlossarService $glossarService,
        LoggerInterface $logger,
        MetadataService $metadataService,
        PagesRepository $pagesRepository
    ) {
        $this->translationService = $translationService;
        $this->sendRequestService = $sendRequestService;
        $this->flashMessageService = $flashMessageService;
        $this->connectionPool = $connectionPool;
        $this->glossarService = $glossarService;
        $this->logger = $logger;
        $this->metadataService = $metadataService;
        $this->pagesRepository = $pagesRepository;
    }

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
            $this->logger->error('Error in TranslationHook: ' . $e->getMessage());
            $this->addErrorFlashMessage();
        }
    }

    protected function isWholePageTranslation(array $aiSuiteConfig): bool
    {
        return isset($aiSuiteConfig['wholePageMode']) && $aiSuiteConfig['wholePageMode'] === true;
    }

    protected function processWholePageTranslation(DataHandler $dataHandler, array $aiSuiteConfig): void
    {
        $destLangId = (int)$aiSuiteConfig['destLangId'];
        $scope = $aiSuiteConfig['scope'] ?? '';
        $pageId = $this->getPageIdFromCmdmap($dataHandler, $scope);

        if ($pageId === null) {
            return;
        }

        $allTranslateFields = $this->collectAllTranslatableContent($pageId, $destLangId, $scope, $dataHandler);

        if (empty($allTranslateFields)) {
            return;
        }

        $this->sendTranslationRequest($allTranslateFields, $aiSuiteConfig, $dataHandler);
    }

    protected function processSingleRecordTranslation(DataHandler $dataHandler, array $aiSuiteConfig): void
    {
        $srcLangIsoCode = $aiSuiteConfig['srcLangIsoCode'];
        $destLangIsoCode = $aiSuiteConfig['destLangIsoCode'];
        $srcLangId = (int)$aiSuiteConfig['srcLangId'];
        $destLangId = (int)$aiSuiteConfig['destLangId'];
        $translateAi = $aiSuiteConfig['translateAi'];
        $rootPageId = (int)$aiSuiteConfig['rootPageId'];

        $request = $GLOBALS['TYPO3_REQUEST'];
        $translateFields = [];
        $elementsCount = 0;
        foreach ($dataHandler->copyMappingArray_merged as $tableKey => $table) {
            foreach ($table as $ceSrcLangUid => $ceDestLangUid) {
                $fields = $this->translationService->fetchTranslationFields($request, [], $ceSrcLangUid, $tableKey);
                if (count($fields) > 0) {
                    $fields = array_filter($fields, function ($field, $key) {
                        if ($key === 'pi_flexform') {
                            return true;
                        }
                        return !is_array($field);
                    }, ARRAY_FILTER_USE_BOTH);
                    $translateFields[$tableKey][$ceDestLangUid] = $fields;
                    $elementsCount++;
                }
            }
        }
        $translateFields = json_encode($translateFields, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);

        $glossarEntries = $this->glossarService->findGlossarEntries($translateFields, $destLangId, $srcLangId);
        $glossary = $this->glossarService->findDeeplGlossary($rootPageId, $srcLangIsoCode, $destLangIsoCode);
        $answer = $this->sendRequestService->sendDataRequest(
            'translate',
            [
                'translate_fields' => $translateFields,
                'translate_fields_count' => $elementsCount,
                'glossary' => json_encode($glossarEntries, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
                'source_lang' => $srcLangIsoCode,
                'target_lang' => $destLangIsoCode,
                'uuid' => $aiSuiteConfig['uuid'] ?? '',
                'deepl_glossary_id' => $glossary['glossar_uuid'] ?? '',
            ],
            '',
            strtoupper($destLangIsoCode),
            [
                'translate' => $translateAi,
            ]
        );
        if ($answer->getType() === 'Error') {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $answer->getResponseData()['message'],
                '',
                ContextualFeedbackSeverity::ERROR,
                true
            );
            $this->flashMessageService
                ->getMessageQueueByIdentifier()
                ->addMessage($flashMessage);
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

    protected function getPageIdFromCmdmap(DataHandler $dataHandler, string $scope): ?int
    {
        if ($scope === 'page' && isset($dataHandler->cmdmap['pages'])) {
            return (int)array_key_first($dataHandler->cmdmap['pages']);
        }

        if ($scope === 'fileReference' && isset($dataHandler->cmdmap['sys_file_reference'])) {
            $fileRefUid = array_key_first($dataHandler->cmdmap['sys_file_reference']);
            return $this->pagesRepository->getPageIdFromFileReference($fileRefUid);
        }

        return null;
    }

    protected function collectAllTranslatableContent(int $pageId, int $destLangId, string $scope, DataHandler $dataHandler): array
    {
        $allTranslateFields = [];

        switch ($scope) {
            case 'page':
                $pageMetadata = $this->metadataService->collectPageMetadataFields($pageId);
                if (!empty($pageMetadata)) {
                    $allTranslateFields['pages'][$dataHandler->copyMappingArray_merged['pages'][$pageId]] = $pageMetadata;
                }
                break;
            default:
                $request = $GLOBALS['TYPO3_REQUEST'];
                foreach ($dataHandler->copyMappingArray_merged as $tableKey => $table) {
                    foreach ($table as $ceSrcLangUid => $ceDestLangUid) {
                        $fields = $this->translationService->fetchTranslationFields($request, [], $ceSrcLangUid, $tableKey);
                        if (count($fields) > 0) {
                            $fields = array_filter($fields, function ($field) {
                                return !is_array($field);
                            });
                            $allTranslateFields[$tableKey][$ceDestLangUid] = $fields;
                        }
                    }
                }
        }

        return $allTranslateFields;
    }

    protected function sendTranslationRequest(array $allTranslateFields, array $aiSuiteConfig, DataHandler $dataHandler): void
    {
        $translateFields = json_encode($allTranslateFields, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
        $elementsCount = $this->countTranslatableElements($allTranslateFields);

        $srcLangId = (int)$aiSuiteConfig['srcLangId'];
        $destLangId = (int)$aiSuiteConfig['destLangId'];
        $srcLangIsoCode = $aiSuiteConfig['srcLangIsoCode'];
        $destLangIsoCode = $aiSuiteConfig['destLangIsoCode'];
        $translateAi = $aiSuiteConfig['translateAi'];
        $rootPageId = (int)$aiSuiteConfig['rootPageId'];

        $glossarEntries = $this->glossarService->findGlossarEntries($translateFields, $destLangId, $srcLangId);
        $glossary = $this->glossarService->findDeeplGlossary($rootPageId, $srcLangIsoCode, $destLangIsoCode);

        $answer = $this->sendRequestService->sendDataRequest(
            'translate',
            [
                'translate_fields' => $translateFields,
                'translate_fields_count' => $elementsCount,
                'glossary' => json_encode($glossarEntries, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
                'source_lang' => $srcLangIsoCode,
                'target_lang' => $destLangIsoCode,
                'uuid' => $aiSuiteConfig['uuid'] ?? '',
                'deepl_glossary_id' => $glossary['glossar_uuid'] ?? '',
                'whole_page_mode' => true,
                'scope' => $aiSuiteConfig['scope'] ?? '',
            ],
            '',
            strtoupper($destLangIsoCode),
            [
                'translate' => $translateAi,
            ]
        );

        if ($answer->getType() === 'Error') {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $answer->getResponseData()['message'],
                '',
                ContextualFeedbackSeverity::ERROR,
                true
            );
            $this->flashMessageService
                ->getMessageQueueByIdentifier()
                ->addMessage($flashMessage);
        } else {
            $translationResults = is_array($answer->getResponseData()['translationResults']) ? $answer->getResponseData()['translationResults'] : json_decode($answer->getResponseData()['translationResults'], true);
            $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $localDataHandler->start($translationResults, [], $dataHandler->BE_USER);
            $localDataHandler->process_datamap();
            $errorLog = $localDataHandler->errorLog;
            if (count($errorLog) > 0) {
                $this->addErrorFlashMessage();
            } else {
                $pageUid = array_key_first($allTranslateFields['pages'] ?? null);
                if (!empty($pageUid)) {
                    $this->translationService->updatePageSlug($pageUid);
                }
            }
        }
    }

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
            $this->translationService->translate('translation.failed'),
            '',
            ContextualFeedbackSeverity::ERROR,
            true
        );
        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->addMessage($flashMessage);
    }
}
