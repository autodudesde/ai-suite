<?php

/***
 *
 * This file is part of the "ai_suite_server" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

return [
    'ctrl' => [
        'title' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.customPromptTemplatesLabel',
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
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'name, prompt, scope, type,',
        'iconfile' => 'EXT:ai_suite/Resources/Public/Icons/Extension.svg',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '
            name, prompt, scope, type,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access, hidden,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid,',
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
                'foreign_table' => 'tx_aisuite_domain_model_custom_prompt_template',
                'foreign_table_where' => 'AND tx_aisuite_domain_model_custom_prompt_template.pid=###CURRENT_PID### AND tx_aisuite_domain_model_custom_prompt_template.sys_language_uid IN (-1,0)',
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
                    ]
                ],
            ],
        ],
        'starttime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ]
            ]
        ],
        'endtime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
                'range' => [
                    'upper' => mktime(0, 0, 0, 1, 1, 2038),
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ]
            ]
        ],
        'name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.name',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim',
                'required' => true
            ],
        ],
        'prompt' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.promptTemplate',
            'config' => [
                'type' => 'text',
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'scope' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.scope',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.scopeGeneral', 'value' => 'general'],
                    ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.scopePageTree', 'value' => 'pageTree'],
                    ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.scopeImageWizard', 'value' => 'imageWizard'],
                    ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.scopeContentElement', 'value' => 'contentElement'],
                    ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.scopeNewsRecord', 'value' => 'newsRecord'],
                    ['label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.scopeEditContent', 'value' => 'editContent'],
                ],
                'size' => 1,
                'eval' => 'trim',
            ],
        ],
        'type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.managePromptTemplates.cTypeScope',
            'displayCond' => 'FIELD:scope:=:contentElement',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'items' => [],
                'size' => 3,
                'eval' => 'trim',
                'default' => '',
                'autoSizeMax' => 10,
            ],
        ],
    ],
];
