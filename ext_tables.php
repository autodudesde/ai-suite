<?php
defined('TYPO3') || die();

use AutoDudes\AiSuite\Controller\AgenciesController;
use AutoDudes\AiSuite\Controller\AiSuiteController;
use AutoDudes\AiSuite\Controller\ContentController;
use AutoDudes\AiSuite\Controller\PagesController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use AutoDudes\AiSuite\Controller\PromptTemplateController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;


ExtensionUtility::registerModule(
    'AiSuite',
    'web',
    'AiSuite',
    '',
    [
        AiSuiteController::class => 'dashboard',
        ContentController::class => 'createContent, requestContent, createPageContent',
        PagesController::class => 'overview, editMetadata, pageStructure, validatePageStructureResult, createValidatedPageStructure',
        AgenciesController::class => 'overview, translateXlf, validateXlfResult, writeXlf',
        PromptTemplateController::class => 'overview, updateServerPromptTemplates, manageCustomPromptTemplates, activate, deactivate, delete'
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:ai_suite/Resources/Public/Icons/Extension.svg',
        'labels' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf',
    ],
);

$pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
if (empty($pageRenderer->getCharSet())) {
    $pageRenderer->setCharSet('utf-8');
}

ExtensionManagementUtility::allowTableOnStandardPages('tx_aisuite_domain_model_custom_prompt_template');

$lll = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:';

$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']['tx_aisuite_features'] = [
    'header' => $lll . 'aiSuite.customPermOptions.headerAiSuiteFeatures',
    'items' => [
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
