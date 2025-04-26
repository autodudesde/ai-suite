<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_glossar',
        'label' => 'input',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'input',
        'iconfile' => 'EXT:ai_suite/Resources/Public/Icons/Extension.svg',
        'searchFields' => 'input',
        'enablecolumns' => [
            'fe_group' => 'fe_group',
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'transOrigPointerField' => 'l18n_parent',
        'transOrigDiffSourceField' => 'l18n_diffsource',
        'languageField' => 'sys_language_uid',
        'translationSource' => 'l10n_source',
    ],
    'columns' => [
        'input' => [
            'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite_domain_model_glossar.input',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
    ],
    'types' => [
        1 => [
            'showitem' => '
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,input,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access, hidden,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid,',
        ],
    ],
];
