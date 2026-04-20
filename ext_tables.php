<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or exit;

ExtensionManagementUtility::addTypoScript(
    'ai_suite',
    'setup',
    '@import "EXT:ai_suite/Configuration/TypoScript/setup.typoscript"'
);

$lll = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:';

$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']['tx_aisuite_features'] = [
    'header' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.headerAiSuiteFeatures',
    'items' => [
        'enable_rte_aiplugin' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableRteAiPlugin',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableRteAiPluginDescription',
        ],
        'enable_rte_aieasylanguageplugin' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableRteAiEasyLanguagePlugin',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableRteAiEasyLanguagePluginDescription',
        ],
        'enable_translation' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslation',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslationDescription',
        ],
        'enable_translation_list_wizard' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslationListWizard',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslationListWizardDescription',
        ],
        'enable_translation_whole_page' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslationWholePage',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslationWholePageDescription',
        ],
        'enable_translation_deepl_sync' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslationDeeplSync',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslationDeeplSyncDescription',
        ],
        'enable_translation_sys_file_metadata' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslationSysFileMetadata',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableTranslationSysFileMetadataDescription',
        ],
        'enable_image_generation' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableImageGeneration',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableImageGenerationDescription',
        ],
        'enable_content_element_generation' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableContentElementGeneration',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableContentElementGenerationDescription',
        ],
        'enable_news_generation' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableNewsGeneration',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableNewsGenerationDescription',
        ],
        'enable_pages_generation' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enablePagesGeneration',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enablePagesGenerationDescription',
        ],
        'enable_metadata_generation' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableMetadataGeneration',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableMetadataGenerationDescription',
        ],
        'enable_massaction_generation' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableMassActionGeneration',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableMassActionGenerationDescription',
        ],
        'enable_background_task_handling' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableBackgroundTaskHandling',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableBackgroundTaskHandlingDescription',
        ],
        'enable_toolbar_stats_item' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableToolbarStatsItem',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableToolbarStatsItemDescription',
        ],
        'enable_prompt_template_button' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enablePromptTemplateButton',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enablePromptTemplateButtonDescription',
        ],
        'enable_global_instructions_button' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableGlobalInstructionsButton',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableGlobalInstructionsButtonDescription',
        ],
        'enable_global_settings' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableGlobalSettings',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.enableGlobalSettingsDescription',
        ],
    ],
];
$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']['tx_aisuite_models'] = [
    'header' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.headerAiSuiteModels',
    'items' => [
        'ChatGPT' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelOpenai',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'Anthropic' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelAnthropic',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'GPTImage' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelGptImage',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'Midjourney' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelMidjourney',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'Flux' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelFlux',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'Vision' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelVision',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'GoogleTranslate' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelGoogleTranslate',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'Deepl' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDeepl',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'AiSuiteTextUltimate' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelAiSuiteTextUltimate',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'MittwaldMinistral14B' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelMittwaldMinistral',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
        'MittwaldMinistral14BVision' => [
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelMittwaldMinistralVision',
            'tx-aisuite-permissions',
            'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.permissions.modelDescription',
        ],
    ],
];
