<?php
defined('TYPO3') || die();

use AutoDudes\AiSuite\Controller\AgenciesController;
use AutoDudes\AiSuite\Controller\AiSuiteController;
use AutoDudes\AiSuite\Controller\ContentController;
use AutoDudes\AiSuite\Controller\FilesController;
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
        ContentController::class => 'overview, createContent, requestContent, createPageContent',
        FilesController::class => 'overview',
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



