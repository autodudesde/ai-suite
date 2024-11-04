<?php
defined('TYPO3') or die();

$lll = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:';

$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']['tx_aisuite_features'] = [
    'header' => $lll . 'aiSuite.customPermOptions.headerAiSuiteFeatures',
    'items' => [
        'enable_rte_aiplugin' => [
            $lll . 'aiSuite.customPermOptions.enableRteAiPlugin',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableRteAiPluginDescription',
        ],
        'enable_translation' => [
            $lll . 'aiSuite.customPermOptions.enableTranslation',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableTranslationDescription',
        ],
        'enable_image_generation' => [
            $lll . 'aiSuite.customPermOptions.enableImageGeneration',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableImageGenerationDescription',
        ],
        'enable_content_element_generation' => [
            $lll . 'aiSuite.customPermOptions.enableContentElementGeneration',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableContentElementGenerationDescription',
        ],
        'enable_news_generation' => [
            $lll . 'aiSuite.customPermOptions.enableNewsGeneration',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableNewsGenerationDescription',
        ],
        'enable_pages_generation' => [
            $lll . 'aiSuite.customPermOptions.enablePagesGeneration',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enablePagesGenerationDescription',
        ],
        'enable_metadata_generation' => [
            $lll . 'aiSuite.customPermOptions.enableMetadataGeneration',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableMetadataGenerationDescription',
        ],
    ],
];
$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']['tx_aisuite_models'] = [
    'header' => $lll . 'aiSuite.customPermOptions.headerAiSuiteModels',
    'items' => [
        'ChatGPT' => [
            $lll . 'aiSuite.customPermOptions.modelOpenai',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.modelDescription',
        ],
        'Anthropic' => [
            $lll . 'aiSuite.customPermOptions.modelAnthropic',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.modelDescription',
        ],
        'DALL-E' => [
            $lll . 'aiSuite.customPermOptions.modelDalle',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.modelDescription',
        ],
        'Midjourney' => [
            $lll . 'aiSuite.customPermOptions.modelMidjourney',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.modelDescription',
        ],
        'Vision' => [
            $lll . 'aiSuite.customPermOptions.modelVision',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.modelDescription',
        ],
        'GoogleTranslate' => [
            $lll . 'aiSuite.customPermOptions.modelGoogleTranslate',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.modelDescription',
        ],
        'Deepl' => [
            $lll . 'aiSuite.customPermOptions.modelDeepl',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.modelDescription',
        ],
    ],
];
