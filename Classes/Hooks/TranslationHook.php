<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

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

    public function __construct(
        TranslationService $translationService,
        SendRequestService $sendRequestService,
        FlashMessageService $flashMessageService,
        ConnectionPool $connectionPool
    ) {
        $this->translationService = $translationService;
        $this->sendRequestService = $sendRequestService;
        $this->flashMessageService = $flashMessageService;
        $this->connectionPool = $connectionPool;
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void {
        if (isset($dataHandler->cmdmap['localization'][0]['aiSuite'])) {
            $srcLangIsoCode = $dataHandler->cmdmap['localization'][0]['aiSuite']['srcLangIsoCode'];
            $destLangIsoCode = $dataHandler->cmdmap['localization'][0]['aiSuite']['destLangIsoCode'];
            $translateAi = $dataHandler->cmdmap['localization'][0]['aiSuite']['translateAi'];

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
            $answer = $this->sendRequestService->sendDataRequest(
                'translate',
                [
                    'translate_fields' => json_encode($translateFields, JSON_HEX_QUOT | JSON_HEX_TAG),
                    'translate_fields_count' => $elementsCount,
                    'source_lang' => $srcLangIsoCode,
                    'target_lang' => $destLangIsoCode,
                    'uuid' => $dataHandler->cmdmap['localization'][0]['aiSuite']['uuid'] ?? '',
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
        }
    }
}
