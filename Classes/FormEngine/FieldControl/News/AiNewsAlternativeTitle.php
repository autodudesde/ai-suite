<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\FormEngine\FieldControl\News;

use AutoDudes\AiSuite\Service\JavaScriptModuleService;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AiNewsAlternativeTitle extends AbstractNode
{
    public function render(): array
    {
        $resultArray = [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.generation.newsAlternativeTitleSuggestions'),
            'linkAttributes' => [
                'id' => 'alternative_title_generation',
                'class' => 'ai-suite-news-suggestions-generation-btn',
                'data-news-id' => $this->data['databaseRow']['uid'],
                'data-folder-id' => $this->data['databaseRow']['pid'],
                'data-field-name' => 'alternative_title'
            ]
        ];

        $javaScriptModuleService = GeneralUtility::makeInstance(JavaScriptModuleService::class);

        return array_merge($resultArray, $javaScriptModuleService->addModules());
    }
}
