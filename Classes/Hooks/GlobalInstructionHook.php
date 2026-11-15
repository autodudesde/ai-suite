<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use AutoDudes\AiSuite\Service\GlobalInstructionService;
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
                $isNew = str_starts_with($id, 'NEW');
                $stored = $isNew ? [] : $globalInstructionsRepository->findByUid((int) $id);
                $context = $incomingFieldArray['context'] ?? $stored['context'] ?? null;
                $scope = $incomingFieldArray['scope'] ?? $stored['scope'] ?? null;
                if (empty($context) || empty($scope) || 'global' === $context) {
                    return;
                }
                $fieldName = 'pages' === $context ? 'selected_pages' : 'selected_directories';
                $selectedTree = (string) ($incomingFieldArray[$fieldName] ?? $stored[$fieldName] ?? '');
                $incomingFieldArray['selected_directories'] ??= '';
                $selectedTreeArray = GeneralUtility::trimExplode(',', $selectedTree, true);
                if ([] === $selectedTreeArray) {
                    return;
                }
                $globalInstruction = $globalInstructionsRepository->findExistingGlobalInstruction(
                    $context,
                    $scope,
                    $selectedTreeArray,
                    $isNew ? null : (int) $id
                );
                if (!empty($globalInstruction)) {
                    $existingSelectedTree = 'pages' === $context ? $globalInstruction['selected_pages'] : $globalInstruction['selected_directories'];
                    $existingSelectedTreeArray = GeneralUtility::trimExplode(',', $existingSelectedTree, true);
                    $uniqueTreeIds = array_diff($selectedTreeArray, $existingSelectedTreeArray);
                    $duplicateTreeIds = array_intersect($existingSelectedTreeArray, $selectedTreeArray);
                    $incomingFieldArray[$fieldName] = implode(',', $uniqueTreeIds);
                    $this->buildFlashMessage($context, $scope, array_values($duplicateTreeIds));
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
        $contextTranslated = $localizationService->translate(GlobalInstructionService::contextLabel($context));
        $scopeTranslated = $localizationService->translate(GlobalInstructionService::scopeLabel($context, $scope));
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
