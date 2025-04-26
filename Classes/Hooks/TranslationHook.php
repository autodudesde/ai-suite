<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Service\GlossarService;
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


    public function __construct(
        TranslationService $translationService,
        SendRequestService $sendRequestService,
        FlashMessageService $flashMessageService,
        ConnectionPool $connectionPool,
        GlossarService $glossarService,
        LoggerInterface $logger
    ) {
        $this->translationService = $translationService;
        $this->sendRequestService = $sendRequestService;
        $this->flashMessageService = $flashMessageService;
        $this->connectionPool = $connectionPool;
        $this->glossarService = $glossarService;
        $this->logger = $logger;
    }

    /**
     * @throws AspectNotFoundException
     * @throws Exception
     */
    public function processCmdmap_afterFinish(DataHandler $dataHandler): void {
        try {
            if (isset($dataHandler->cmdmap['localization'][0]['aiSuite'])) {
                $srcLangIsoCode = $dataHandler->cmdmap['localization'][0]['aiSuite']['srcLangIsoCode'];
                $destLangIsoCode = $dataHandler->cmdmap['localization'][0]['aiSuite']['destLangIsoCode'];
                $srcLangId = (int)$dataHandler->cmdmap['localization'][0]['aiSuite']['srcLangId'];
                $destLangId = (int)$dataHandler->cmdmap['localization'][0]['aiSuite']['destLangId'];
                $translateAi = $dataHandler->cmdmap['localization'][0]['aiSuite']['translateAi'];
                $rootPageId = (int)$dataHandler->cmdmap['localization'][0]['aiSuite']['rootPageId'];

                $request = $GLOBALS['TYPO3_REQUEST'];
                $translateFields = [];
                $elementsCount = 0;
                foreach ($dataHandler->copyMappingArray_merged as $tableKey => $table) {
                    foreach ($table as $ceSrcLangUid => $ceDestLangUid) {
                        $fields = $this->translationService->fetchTranslationtFields($request, [], $ceSrcLangUid, $tableKey);
                        if (count($fields) > 0) {
                            $translateFields[$tableKey][$ceDestLangUid] = $fields;
                            $elementsCount++;
                        }
                    }
                }
                $translateFields = json_encode($translateFields, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);

                //find all fixed expressions for our target language
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
                        'uuid' => $dataHandler->cmdmap['localization'][0]['aiSuite']['uuid'] ?? '',
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
                    $translationResults = json_decode($answer->getResponseData()['translationResults'], true);
                    $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
                    $localDataHandler->start($translationResults, [], $dataHandler->BE_USER);
                    $localDataHandler->process_datamap();
                    $errorLog = $localDataHandler->errorLog;
                    if (count($errorLog) > 0) {
                        $this->addErrorFlashMessage();
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in TranslationHook: ' . $e->getMessage());
            $this->addErrorFlashMessage();
        }
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
