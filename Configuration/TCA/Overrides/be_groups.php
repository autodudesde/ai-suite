<?php

defined('TYPO3') or die();

$lll = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:';

$beGroupColumns =  [
    'aiSuiteApiKey' => [
        'label' => $lll . 'aiSuite.beGroups.field.aiSuiteApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'openAiApiKey' => [
        'label' => $lll . 'aiSuite.beGroups.field.openAiApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'anthropicApiKey' => [
        'label' => $lll . 'aiSuite.beGroups.field.anthropicApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'googleTranslateApiKey' => [
        'label' => $lll . 'aiSuite.beGroups.field.googleTranslateApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'deeplApiKey' => [
        'label' => $lll . 'aiSuite.beGroups.field.deeplApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'deeplApiMode' => [
        'label' => $lll . 'aiSuite.beGroups.field.deeplApiMode',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'midjourneyApiKey' => [
        'label' => $lll . 'aiSuite.beGroups.field.midjourneyApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'midjourneyId' => [
        'label' => $lll . 'aiSuite.beGroups.field.midjourneyId',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'mediaStorageFolder' => [
        'label' => $lll . 'aiSuite.beGroups.field.mediaStorageFolder',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'openTranslatedRecordInEditMode' => [
        'label' => $lll . 'aiSuite.beGroups.field.openTranslatedRecordInEditMode',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 1,
        ],
    ]
];
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
    'be_groups',
    $beGroupColumns
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'be_groups',
    '--div--;' . $lll . 'aiSuite.beGroups.tab.settings, aiSuiteApiKey, openAiApiKey, anthropicApiKey, googleTranslateApiKey, deeplApiKey, deeplApiMode, midjourneyApiKey, midjourneyId, mediaStorageFolder, openTranslatedRecordInEditMode',
    '',
    'after:category_perms'
);
