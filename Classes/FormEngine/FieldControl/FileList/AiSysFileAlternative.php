<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\FormEngine\FieldControl\FileList;

use AutoDudes\AiSuite\Service\MassActionService;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AiSysFileAlternative extends AbstractNode
{
    public function render(): array
    {
        if (!$GLOBALS['BE_USER']->check('custom_options', 'tx_aisuite_features:enable_metadata_generation')) {
            return [];
        }

        $fileUid = (int)$this->data['databaseRow']['file'][0];
        $massActionService = GeneralUtility::makeInstance(MassActionService::class);
        $folderCombinedIdentifier = $massActionService->getFolderCombinedIdentifier($fileUid);

        return [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => $GLOBALS['LANG']->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.generation.alternativeSuggestions'),
            'linkAttributes' => [
                'id' => 'alternative_generation',
                'data-sys-file-id' => (int)$this->data['databaseRow']['file'][0],
                'class' => 'ai-suite-suggestions-generation-btn',
                'data-id' => $this->data['databaseRow']['uid'],
                'data-table' => $this->data['tableName'],
                'data-field-name' => 'alternative',
                'data-field-label' => 'Alternative',
                'data-target-folder' => $folderCombinedIdentifier,
            ],
            'javaScriptModules' => [
                JavaScriptModuleInstruction::create('@autodudes/ai-suite/metadata/generate-suggestions.js')
            ],
        ];
    }
}
