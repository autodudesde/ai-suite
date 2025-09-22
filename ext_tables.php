<?php
defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
    'ai_suite',
    'setup',
    '@import "EXT:ai_suite/Configuration/TypoScript/setup.typoscript"'
);

$lll = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:';

$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']['tx_aisuite_features'] = [
    'header' => $lll . 'aiSuite.customPermOptions.headerAiSuiteFeatures',
    'items' => [
        'enable_rte_aiplugin' => [
            $lll . 'aiSuite.customPermOptions.enableRteAiPlugin',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableRteAiPluginDescription',
        ],
        'enable_rte_aieasylanguageplugin' => [
            $lll . 'aiSuite.customPermOptions.enableRteAiEasyLanguagePlugin',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableRteAiEasyLanguagePluginDescription',
        ],
        'enable_translation' => [
            $lll . 'aiSuite.customPermOptions.enableTranslation',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableTranslationDescription',
        ],
        'enable_translation_list_wizard' => [
            $lll . 'aiSuite.customPermOptions.enableTranslationListWizard',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableTranslationListWizardDescription',
        ],
        'enable_translation_whole_page' => [
            $lll . 'aiSuite.customPermOptions.enableTranslationWholePage',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableTranslationWholePageDescription',
        ],
        'enable_translation_deepl_sync' => [
            $lll . 'aiSuite.customPermOptions.enableTranslationDeeplSync',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableTranslationDeeplSyncDescription',
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
        'enable_massaction_generation' => [
            $lll . 'aiSuite.customPermOptions.enableMassActionGeneration',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableMassActionGenerationDescription',
        ],
        'enable_background_task_handling' => [
            $lll . 'aiSuite.customPermOptions.enableBackgroundTaskHandling',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableBackgroundTaskHandlingDescription',
        ],
        'enable_toolbar_stats_item' => [
            $lll . 'aiSuite.customPermOptions.enableToolbarStatsItem',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.enableToolbarStatsItemDescription',
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
        'Flux' => [
            $lll . 'aiSuite.customPermOptions.modelFlux',
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
        'AiSuiteTextUltimate' => [
            $lll . 'aiSuite.customPermOptions.modelAiSuiteTextUltimate',
            'tx-aisuite-permissions',
            $lll . 'aiSuite.customPermOptions.modelDescription',
        ],
    ],
];
