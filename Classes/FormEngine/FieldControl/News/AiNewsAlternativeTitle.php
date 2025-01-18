<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\FormEngine\FieldControl\News;

use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

class AiNewsAlternativeTitle extends AbstractNode
{
    public function render(): array
    {
        if(!$GLOBALS['BE_USER']->check('custom_options', 'tx_aisuite_features:enable_metadata_generation')) {
            return [];
        }
        return [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => $GLOBALS['LANG']->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.generation.newsAlternativeTitleSuggestions'),
            'linkAttributes' => [
                'id' => 'alternative_title_generation',
                'class' => 'ai-suite-suggestions-generation-btn',
                'data-id' => $this->data['databaseRow']['uid'],
                'data-page-id' => $this->data['databaseRow']['pid'],
                'data-language-id' => $this->data['databaseRow']['sys_language_uid'],
                'data-table' => $this->data['tableName'],
                'data-field-name' => 'alternative_title',
                'data-field-label' => 'NewsAlternativeTitle',
            ],
            'javaScriptModules' => [
                JavaScriptModuleInstruction::create('@autodudes/ai-suite/metadata/generate-suggestions.js')
            ],
        ];
    }
}
