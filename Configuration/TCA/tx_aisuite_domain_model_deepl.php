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


return [
    'ctrl' => [
        'title' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_deepl',
        'label' => 'glossar_uuid',
        'label_alt' => 'source_lang,target_lang',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
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
        'searchFields' => 'glossar_uuid, source_lang, target_lang',
        'iconfile' => 'EXT:ai_suite/Resources/Public/Icons/Extension.svg',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ]
    ],
    'types' => [
        '1' => [
            'showitem' => '
        glossar_uuid, root_page_uid, source_lang, target_lang, default_language_id, target_language_id, external,
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
                    ['', 0],
                ],
                'foreign_table' => 'tx_aisuite_domain_model_deepl',
                'foreign_table_where' => 'AND tx_aisuite_domain_model_deepl.pid=###CURRENT_PID### AND tx_aisuite_domain_model_deepl.sys_language_uid IN (-1,0)',
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
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
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
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'default' => 0,
                'range' => [
                    'upper' => mktime(0, 0, 0, 1, 1, 2038),
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ]
            ]
        ],
        'glossar_uuid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_deepl.glossar_uuid',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'root_page_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_deepl.root_page_uid',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'required' => true,
            ],
        ],
        'source_lang' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_deepl.source_lang',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 10,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'target_lang' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_deepl.target_lang',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 10,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'default_language_id' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_deepl.default_language_id',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'required' => true,
            ],
        ],
        'target_language_id' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_deepl.target_language_id',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'required' => true,
            ],
        ],
        'external' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_deepl.external',
            'config' => [
                'type' => 'check',
                'default' => 0,
                'items' => [
                    [
                        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_deepl.external_enable'
                    ],
                ]
            ]
        ],
    ],
];
