<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use AutoDudes\AiSuite\Service\LocalizationService;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GlobalInstructionHook
{
    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, string $table, string $id, DataHandler $dataHandler): void
    {
        try {
            if ('tx_aisuite_domain_model_global_instructions' === $table) {
                $globalInstructionsRepository = GeneralUtility::makeInstance(GlobalInstructionsRepository::class);
                $context = $incomingFieldArray['context'] ?? null;
                $scope = $incomingFieldArray['scope'] ?? null;
                if (empty($context) || empty($scope)) {
                    return;
                }
                $incomingFieldArray['selected_directories'] ??= '';
                $selectedTree = 'pages' === $context ? $incomingFieldArray['selected_pages'] : $incomingFieldArray['selected_directories'];
                $selectedTreeArray = GeneralUtility::trimExplode(',', $selectedTree, true);
                $globalInstruction = $globalInstructionsRepository->findExistingGlobalInstruction(
                    $context,
                    $scope,
                    $selectedTreeArray
                );
                if (!empty($globalInstruction)) {
                    $existingSelectedTree = 'pages' === $context ? $globalInstruction['selected_pages'] : $globalInstruction['selected_directories'];
                    $existingSelectedTreeArray = GeneralUtility::trimExplode(',', $existingSelectedTree, true);
                    $fieldName = 'pages' === $context ? 'selected_pages' : 'selected_directories';
                    $uniqueTreeIds = array_diff($selectedTreeArray, $existingSelectedTreeArray);
                    $duplicateTreeIds = array_intersect($existingSelectedTreeArray, $selectedTreeArray);
                    if (str_starts_with($id, 'NEW')) {
                        $incomingFieldArray[$fieldName] = implode(',', $uniqueTreeIds);
                        $this->buildFlashMessage($context, $scope, array_values($duplicateTreeIds));
                    } else {
                        if ((int) $id !== $globalInstruction['uid']) {
                            $incomingFieldArray[$fieldName] = implode(',', $uniqueTreeIds);
                            $this->buildFlashMessage($context, $scope, array_values($duplicateTreeIds));
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // silent fail
        }
    }

    /**
     * @param list<string> $duplicateTreeIds
     *
     * @throws Exception
     */
    private function buildFlashMessage(string $context, string $scope, array $duplicateTreeIds): void
    {
        $localizationService = GeneralUtility::makeInstance(LocalizationService::class);
        $contextTranslated = $localizationService->translate('tx_aisuite_domain_model_global_instructions.context.'.$context);
        $scopeTranslated = $localizationService->translate('module:aiSuite.module.dashboard.card.managePromptTemplates.scope'.ucfirst($scope));
        $flashMessageTitle = $localizationService->translate('aiSuite.globalInstructions.duplicate_detected_title');
        $flashMessageText = $localizationService->translate('aiSuite.globalInstructions.duplicate_detected_message', [
            $contextTranslated,
            $scopeTranslated,
            implode(',', $duplicateTreeIds),
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
