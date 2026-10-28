<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or exit;

$lll = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:';

$beGroupColumns = [
    'aiSuiteApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.aiSuiteApiKey',
        'config' => [
            'type' => 'password',
            'hashed' => false,
        ],
    ],
    'openAiApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.openAiApiKey',
        'config' => [
            'type' => 'password',
            'hashed' => false,
        ],
    ],
    'anthropicApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.anthropicApiKey',
        'config' => [
            'type' => 'password',
            'hashed' => false,
        ],
    ],
    'aiModelHubApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.aiModelHubApiKey',
        'config' => [
            'type' => 'password',
            'hashed' => false,
        ],
    ],
    'mittwaldAiModelHubApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.mittwaldAiModelHubApiKey',
        'config' => [
            'type' => 'password',
            'hashed' => false,
        ],
    ],
    'googleTranslateApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.googleTranslateApiKey',
        'config' => [
            'type' => 'password',
            'hashed' => false,
        ],
    ],
    'deeplApiKey' => [
        'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.beGroups.field.deeplApiKey',
        'config' => [
            'type' => 'password',
            'hashed' => false,
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
            'type' => 'password',
            'hashed' => false,
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
    '--div--;'.$lll.'aiSuite.beGroups.tab.settings, aiSuiteApiKey, openAiApiKey, anthropicApiKey, aiModelHubApiKey, mittwaldAiModelHubApiKey, googleTranslateApiKey, deeplApiKey, deeplApiMode, midjourneyApiKey, midjourneyId, mediaStorageFolder',
    '',
    'after:category_perms'
);
