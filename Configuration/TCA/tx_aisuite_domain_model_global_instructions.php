<?php

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

return [
    'ctrl' => [
        'title' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'name, instructions, scope, context',
        'iconfile' => 'EXT:ai_suite/Resources/Public/Icons/Extension.svg',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '
        name, instructions, context, scope, use_for_subtree, extend_previous_instructions, override_predefined_prompt, selected_pages, selected_directories,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access, hidden,',
        ],
    ],
    'columns' => [
        'crdate' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_aisuite_domain_model_global_instructions',
                'foreign_table_where' => 'AND tx_aisuite_domain_model_global_instructions.pid=###CURRENT_PID### AND tx_aisuite_domain_model_global_instructions.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        't3ver_label' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.versionLabel',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                [
                    [
                        'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                        '',
                    ],
                ],
            ],
        ],
        'name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.name',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'instructions' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions',
            'config' => [
                'type' => 'text',
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'context' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.context',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.context.pages', 'value' => 'pages'],
                    ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.context.files', 'value' => 'files'],
                ],
                'default' => 'pages',
                'size' => 1,
                'eval' => 'trim',
            ],
        ],
        'scope' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'itemsProcFunc' => 'AutoDudes\AiSuite\Tca\ScopeItemsProcFunc->getScopeItems',
                'default' => 'general',
                'size' => 1,
                'eval' => 'trim',
            ],
        ],
        'selected_pages' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.selected_pages',
            'displayCond' => 'FIELD:context:=:pages',
            'config' => [
                'required' => true,
                'type' => 'select',
                'renderType' => 'selectTree',
                'foreign_table' => 'pages',
                'foreign_table_where' => 'AND pages.doktype IN (1,4,254) ORDER BY pages.sorting',
                'size' => 30,
                'treeConfig' => [
                    'parentField' => 'pid',
                    'appearance' => [
                        'expandAll' => true,
                        'showHeader' => true,
                        'maxLevels' => 99,
                    ],
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'selected_directories' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.selected_directories',
            'displayCond' => 'FIELD:context:=:files',
            'config' => [
                'required' => true,
                'type' => 'folder',
                'size' => 5,
                'autoSizeMax' => 10,
                'elementBrowserEntryPoints' => [
                    '_default' => '1:/',
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'use_for_subtree' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.use_for_subtree',
            'config' => [
                'type' => 'check',
                'default' => 1,
                'items' => [
                    [
                        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.enable',
                    ],
                ],
            ],
        ],
        'extend_previous_instructions' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.extend_previous_instructions',
            'config' => [
                'type' => 'check',
                'default' => 1,
                'items' => [
                    [
                        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.enable',
                    ],
                ],
            ],
        ],
        'override_predefined_prompt' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.override_predefined_prompt',
            'displayCond' => 'FIELD:scope:=:metadata',
            'config' => [
                'type' => 'check',
                'default' => 0,
                'items' => [
                    [
                        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.enable',
                    ],
                ],
            ],
        ],
    ],
];
