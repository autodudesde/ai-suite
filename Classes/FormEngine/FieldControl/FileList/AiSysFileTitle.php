<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\FormEngine\FieldControl\FileList;

use AutoDudes\AiSuite\Service\JavaScriptModuleService;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AiSysFileTitle extends AbstractNode
{
    public function render(): array
    {
        $resultArray = [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.generation.titleSuggestions'),
            'linkAttributes' => [
                'id' => 'title_generation',
                'class' => 'ai-suite-suggestions-generation-btn',
                'data-sys-file-metadata-id' => $this->data['databaseRow']['uid'],
                'data-sys-file-id' => (int)$this->data['databaseRow']['file'][0],
                'data-field-name' => 'title'
            ]
        ];

        $javaScriptModuleService = GeneralUtility::makeInstance(JavaScriptModuleService::class);

        return array_merge($resultArray, $javaScriptModuleService->addModules());
    }
}
