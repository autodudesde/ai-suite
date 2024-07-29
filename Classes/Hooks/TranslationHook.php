<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest\ServerRequest;
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Utility\ModelUtility;
use AutoDudes\AiSuite\Utility\SiteUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationHook
{

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void {
        if(array_key_exists('localization', $dataHandler->cmdmap) && array_key_exists('aiSuite', $dataHandler->cmdmap['localization'])) {
            $srcLanguageId = (int)$dataHandler->cmdmap['localization']['aiSuite']['srcLanguageId'];
            $srcLangIsoCode = SiteUtility::getIsoCodeByLanguageId($srcLanguageId);
            $destLanguageId = (int)$dataHandler->cmdmap['localization']['aiSuite']['destLanguageId'];
            $destLangIsoCode = SiteUtility::getIsoCodeByLanguageId($destLanguageId);
            $translateAi = $dataHandler->cmdmap['localization']['aiSuite']['translateAi'];

            $translationService = GeneralUtility::makeInstance(TranslationService::class);
            $request = $GLOBALS['TYPO3_REQUEST'];
            $translateFields = [];
            $elementsCount = 0;
            foreach ($dataHandler->copyMappingArray_merged as $tableKey => $table) {
                foreach ($table as $ceSrcLangUid => $ceDestLangUid) {
                    $fields = $translationService->fetchTranslationtFields($request, [], $ceSrcLangUid, $tableKey);
                    if(count($fields) > 0) {
                        $translateFields[$tableKey][$ceDestLangUid] = $fields;
                        $elementsCount++;
                    }
                }
            }
            $sendRequestService = GeneralUtility::makeInstance(SendRequestService::class);
            $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ai_suite');
            $answer = $sendRequestService->sendRequest(
                new ServerRequest(
                    $extConf,
                    'translate',
                    [
                        'translate_fields' => json_encode($translateFields, JSON_HEX_QUOT | JSON_HEX_TAG),
                        'translate_fields_count' => $elementsCount,
                        'source_lang' => $srcLangIsoCode,
                        'target_lang' => $destLangIsoCode,
                        'uuid' => $dataHandler->cmdmap['localization']['aiSuite']['uuid'] ?? '',
                        'keys' => ModelUtility::fetchKeysByModel($extConf, [$translateAi]),
                    ],
                    '',
                    strtoupper($destLangIsoCode),
                    [
                        'translate' => $translateAi,
                    ]
                )
            );
            if ($answer->getType() === 'Error') {
                $flashMessage = GeneralUtility::makeInstance(FlashMessage::class,
                    $answer->getResponseData()['message'], '', ContextualFeedbackSeverity::ERROR, true
                );
                GeneralUtility::makeInstance(FlashMessageService::class)
                    ->getMessageQueueByIdentifier()
                    ->addMessage($flashMessage);
            } else {
                if(array_key_exists('free_requests', $answer->getResponseData()) && array_key_exists('free_requests', $answer->getResponseData())) {
                    $requestsRepository = GeneralUtility::makeInstance(RequestsRepository::class);
                    $requestsRepository->setRequests($answer->getResponseData()['free_requests'], $answer->getResponseData()['paid_requests']);
                    BackendUtility::setUpdateSignal('updateTopbar');
                }
                $translationResults = json_decode($answer->getResponseData()['translationResults'], true);
                foreach ($translationResults as $tableKey => $table) {
                    foreach ($table as $ceDestLangUid => $fields) {
                        foreach ($fields as $fieldName => $fieldValue) {
                            $fields[$fieldName] = $translationService->cleanedContent($fieldValue);
                        }
                        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableKey);
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
