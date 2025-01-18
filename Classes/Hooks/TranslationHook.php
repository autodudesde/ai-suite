<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationHook
{
    protected SiteService $siteService;
    protected TranslationService $translationService;
    protected SendRequestService $sendRequestService;
    protected FlashMessageService $flashMessageService;
    protected ConnectionPool $connectionPool;

    public function __construct(
        SiteService $siteService,
        TranslationService $translationService,
        SendRequestService $sendRequestService,
        FlashMessageService $flashMessageService,
        ConnectionPool $connectionPool
    ) {
        $this->siteService = $siteService;
        $this->translationService = $translationService;
        $this->sendRequestService = $sendRequestService;
        $this->flashMessageService = $flashMessageService;
        $this->connectionPool = $connectionPool;
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void {
        if (isset($dataHandler->cmdmap['localization'][0]['aiSuite'])) {
            $srcLanguageId = (int)$dataHandler->cmdmap['localization'][0]['aiSuite']['srcLanguageId'];
            $srcLangIsoCode = $this->siteService->getIsoCodeByLanguageId($srcLanguageId);
            $destLanguageId = (int)$dataHandler->cmdmap['localization'][0]['aiSuite']['destLanguageId'];
            $destLangIsoCode = $this->siteService->getIsoCodeByLanguageId($destLanguageId);
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
                foreach ($translationResults as $tableKey => $table) {
                    foreach ($table as $ceDestLangUid => $fields) {
                        $queryBuilder = $this->connectionPool->getConnectionForTable($tableKey);
                        $queryBuilder->update(
                            $tableKey,
                            $fields,
                            ['uid' => $ceDestLangUid]
                        );
                    }
                }
            }
        }
    }
}
