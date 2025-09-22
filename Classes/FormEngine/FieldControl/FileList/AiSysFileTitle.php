<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\FormEngine\FieldControl\FileList;

use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

class AiSysFileTitle extends AbstractNode
{
    public function render(): array
    {
        if (!$GLOBALS['BE_USER']->check('custom_options', 'tx_aisuite_features:enable_metadata_generation')) {
            return [];
        }
        return [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => $GLOBALS['LANG']->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.generation.titleSuggestions'),
            'linkAttributes' => [
                'id' => 'title_generation',
                'data-sys-file-id' => (int)$this->data['databaseRow']['file'][0],
                'class' => 'ai-suite-suggestions-generation-btn',
                'data-id' => $this->data['databaseRow']['uid'],
                'data-table' => $this->data['tableName'],
                'data-field-name' => 'title',
                'data-field-label' => 'Title',
            ],
            'javaScriptModules' => [
                JavaScriptModuleInstruction::create('@autodudes/ai-suite/metadata/generate-suggestions.js')
            ],
        ];
    }
}
