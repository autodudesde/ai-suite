<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\FormEngine\FieldControl\FileList;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\WorkflowViewService;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AiSysFileTitle extends AbstractNode
{
    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        if (!GeneralUtility::makeInstance(BackendUserService::class)->checkPermissions('tx_aisuite_features:enable_metadata_generation')) {
            return [];
        }

        $fileUid = (int) $this->data['databaseRow']['file'][0];
        $workflowViewService = GeneralUtility::makeInstance(WorkflowViewService::class);
        $folderCombinedIdentifier = $workflowViewService->getFolderCombinedIdentifier($fileUid);

        return [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => GeneralUtility::makeInstance(LocalizationService::class)->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generation.titleSuggestions'),
            'linkAttributes' => [
                'id' => 'title_generation',
                'data-sys-file-id' => $fileUid,
                'class' => 'ai-suite-suggestions-generation-btn',
                'data-id' => $this->data['databaseRow']['uid'],
                'data-table' => $this->data['tableName'],
                'data-field-name' => 'title',
                'data-field-label' => 'Title',
                'data-target-folder' => $folderCombinedIdentifier,
            ],
            'javaScriptModules' => [
                JavaScriptModuleInstruction::create('@autodudes/ai-suite/metadata/generate-suggestions.js'),
            ],
        ];
    }
}
