<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use AutoDudes\AiSuite\Service\TranslationService;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GlobalInstructionsHook
{
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, string $table, string $id, DataHandler $dataHandler)
    {
        try {
            if ($table === 'tx_aisuite_domain_model_global_instructions') {
                $globalInstructionsRepository = GeneralUtility::makeInstance(GlobalInstructionsRepository::class);
                $selectedTree = $incomingFieldArray['context'] === 'pages' ? $incomingFieldArray['selected_pages'] : $incomingFieldArray['selected_directories'];
                $selectedTreeArray = GeneralUtility::trimExplode(',', $selectedTree, true);
                $globalInstruction = $globalInstructionsRepository->findExistingGlobalInstruction(
                    $incomingFieldArray['context'],
                    $incomingFieldArray['scope'],
                    $selectedTreeArray
                );
                if (!empty($globalInstruction)) {
                    $existingSelectedTree = $incomingFieldArray['context'] === 'pages' ? $globalInstruction['selected_pages'] : $globalInstruction['selected_directories'];
                    $existingSelectedTreeArray = GeneralUtility::trimExplode(',', $existingSelectedTree, true);
                    $fieldName = $incomingFieldArray['context'] === 'pages' ? 'selected_pages' : 'selected_directories';
                    $uniqueTreeIds = array_diff($selectedTreeArray, $existingSelectedTreeArray);
                    $duplicateTreeIds = array_intersect($existingSelectedTreeArray, $selectedTreeArray);
                    if (str_starts_with($id, 'NEW')) {
                        $incomingFieldArray[$fieldName] = implode(',', $uniqueTreeIds);
                        $this->buildFlashMessage($incomingFieldArray['context'], $incomingFieldArray['scope'], $duplicateTreeIds);
                    } else {
                        if ((int)$id !== $globalInstruction['uid']) {
                            $incomingFieldArray[$fieldName] = implode(',', $uniqueTreeIds);
                            $this->buildFlashMessage($incomingFieldArray['context'], $incomingFieldArray['scope'], $duplicateTreeIds);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // silent fail
        }
    }

    /**
     * @throws Exception
     */
    private function buildFlashMessage(string $context, string $scope, array $duplicateTreeIds): void
    {
        $translationService = GeneralUtility::makeInstance(TranslationService::class);
        $contextTranslated = $translationService->translate('tx_aisuite_domain_model_global_instructions.context.' . $context);
        $scopeTranslated = $translationService->translate('tx_aisuite.module.dashboard.card.manageGlobalInstructions.scope' . ucfirst($scope));
        $flashMessageTitle = $translationService->translate('global_instructions.duplicate_detected_title');
        $flashMessageText = $translationService->translate('global_instructions.duplicate_detected_message', [
            $contextTranslated,
            $scopeTranslated,
            implode(',', $duplicateTreeIds)
        ]);
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $flashMessageText,
            $flashMessageTitle,
            ContextualFeedbackSeverity::WARNING,
            true
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }
}
