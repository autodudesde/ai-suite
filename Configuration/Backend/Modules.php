<?php

return [
    'web_aisuite' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/page/aisuite',
        'icon'   => 'EXT:ai_suite/Resources/Public/Icons/Extension.svg',
        'labels' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'AiSuite',
        'controllerActions' => [
            \AutoDudes\AiSuite\Controller\AiSuiteController::class => 'dashboard',
            \AutoDudes\AiSuite\Controller\AgenciesController::class => 'overview, translateXlf, validateXlfResult, writeXlf',
            \AutoDudes\AiSuite\Controller\ContentController::class => 'overview, createContentElement, createPageContent, pageContentValidation, createText, requestContentElement, requestText, createImage, requestImage, createTextpic, requestTextpic, updateImage, createAndAddImage, requestAndAddImage, generateImageForContent, refactorTextContent, addImageContent, requestNewImageContent, createImageContent, createTextContent, requestTextContent',
            \AutoDudes\AiSuite\Controller\ContentElementController::class => 'createContentElement, requestContentElement, createPageContent,',
            \AutoDudes\AiSuite\Controller\FilesController::class => 'overview',
            \AutoDudes\AiSuite\Controller\PagesController::class => 'overview, editMetadata, pageStructure, validatePageStructureResult, createValidatedPageStructure',
            \AutoDudes\AiSuite\Controller\PromptTemplateController::class => 'overview, updateServerPromptTemplates, manageCustomPromptTemplates',
        ],
    ]
];
