<?php

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

declare(strict_types=1);

namespace AutoDudes\AiSuite\Tca;

class ScopeItemsProcFunc
{
    public function getScopeItems(array &$config): void
    {
        $context = $config['row']['context'][0] ?? 'pages';

        if ($context === 'files') {
            $config['items'] = [
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.files.general', 'value' => 'general'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.files.imageWizard', 'value' => 'imageWizard'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.files.metadata', 'value' => 'metadata'],
            ];
        } else {
            $config['items'] = [
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.pages.general', 'value' => 'general'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.pages.pageTree', 'value' => 'pageTree'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.pages.imageWizard', 'value' => 'imageWizard'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.pages.contentElement', 'value' => 'contentElement'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.pages.newsRecord', 'value' => 'newsRecord'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.pages.editContent', 'value' => 'editContent'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.pages.metadata', 'value' => 'metadata'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_global_instructions.scope.pages.translation', 'value' => 'translation'],
            ];
        }
    }
}
