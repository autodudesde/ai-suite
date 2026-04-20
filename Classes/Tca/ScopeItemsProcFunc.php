<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Tca;

class ScopeItemsProcFunc
{
    /**
     * @param array<string, mixed> $config
     */
    public function getScopeItems(array &$config): void
    {
        $context = $config['row']['context'][0] ?? 'pages';

        if ('files' === $context) {
            $config['items'] = [
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.files.general', 'value' => 'general'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.files.imageWizard', 'value' => 'imageWizard'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.files.metadata', 'value' => 'metadata'],
            ];
        } else {
            $config['items'] = [
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.pages.general', 'value' => 'general'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.pages.pageTree', 'value' => 'pageTree'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.pages.imageWizard', 'value' => 'imageWizard'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.pages.contentElement', 'value' => 'contentElement'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.pages.newsRecord', 'value' => 'newsRecord'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.pages.editContent', 'value' => 'editContent'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.pages.metadata', 'value' => 'metadata'],
                ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.pages.translation', 'value' => 'translation'],
            ];
        }
    }
}
