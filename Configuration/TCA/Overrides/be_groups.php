<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or exit;

$lll = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:';

$beGroupColumns = [
    'aiSuiteApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.aiSuiteApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'openAiApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.openAiApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'anthropicApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.anthropicApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'googleTranslateApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.googleTranslateApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'deeplApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.deeplApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'deeplApiMode' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.deeplApiMode',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'midjourneyApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.midjourneyApiKey',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'midjourneyId' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.midjourneyId',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
    'mediaStorageFolder' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.mediaStorageFolder',
        'config' => [
            'type' => 'input',
            'eval' => 'trim',
        ],
    ],
];
ExtensionManagementUtility::addTCAcolumns(
    'be_groups',
    $beGroupColumns
);
ExtensionManagementUtility::addToAllTCAtypes(
    'be_groups',
    '--div--;'.$lll.'aiSuite.beGroups.tab.settings, aiSuiteApiKey, openAiApiKey, anthropicApiKey, googleTranslateApiKey, deeplApiKey, deeplApiMode, midjourneyApiKey, midjourneyId, mediaStorageFolder',
    '',
    'after:category_perms'
);
