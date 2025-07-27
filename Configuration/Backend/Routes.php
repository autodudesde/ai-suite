<?php

return [
    'ai_suite_record_edit' => [
        'path' => 'ai/suite/record/edit',
        'target' => \AutoDudes\AiSuite\Controller\ContentController::class . '::handleRequest',
        'redirect' => [
            'enable' => true,
            'parameters' => [
                'edit' => true,
            ],
        ],
    ],
    'ai_suite_dashboard' => [
        'path' => '/aisuite/dashboard',
        'target' => \AutoDudes\AiSuite\Controller\AiSuiteController::class . '::handleRequest',
    ],
    /**
     * Page routes
     */
    'ai_suite_page' => [
        'path' => '/aisuite/page',
        'target' => \AutoDudes\AiSuite\Controller\PagesController::class . '::handleRequest',
    ],
    'ai_suite_page_create_pagetree' => [
        'path' => '/aisuite/page/create/pagetree',
        'target' => \AutoDudes\AiSuite\Controller\PagesController::class . '::handleRequest',
    ],
    'ai_suite_page_validate_pagetree' => [
        'path' => '/aisuite/page/validate/pagetree',
        'methods' => ['POST'],
        'target' => \AutoDudes\AiSuite\Controller\PagesController::class . '::handleRequest'
    ],
    'ai_suite_page_validate_pagetree_create' => [
        'path' => '/aisuite/page/validate/pagetree/create',
        'methods' => ['POST'],
        'target' => \AutoDudes\AiSuite\Controller\PagesController::class . '::handleRequest',
    ],
    /**
     * Agencies routes
     */
    'ai_suite_agencies' => [
        'path' => '/aisuite/agencies',
        'target' => \AutoDudes\AiSuite\Controller\AgenciesController::class . '::handleRequest',
    ],
    'ai_suite_agencies_translate_xlf' => [
        'path' => '/aisuite/agencies/translate/xlf',
        'target' => \AutoDudes\AiSuite\Controller\AgenciesController::class . '::handleRequest',
    ],
    'ai_suite_agencies_validate_xlf' => [
        'path' => '/aisuite/agencies/validate/xlf',
        'target' => \AutoDudes\AiSuite\Controller\AgenciesController::class . '::handleRequest',
    ],
    'ai_suite_agencies_write_xlf' => [
        'path' => '/aisuite/agencies/write/xlf',
        'target' => \AutoDudes\AiSuite\Controller\AgenciesController::class . '::handleRequest',
    ],
    /**
     * Prompt routes
     */
    'ai_suite_prompt' => [
        'path' => '/aisuite/prompt',
        'target' => \AutoDudes\AiSuite\Controller\PromptTemplateController::class . '::handleRequest',
    ],
    'ai_suite_prompt_update_serverprompttemplates' => [
        'path' => '/aisuite/prompt/update/server-prompt-templates',
        'target' => \AutoDudes\AiSuite\Controller\PromptTemplateController::class . '::handleRequest',
    ],
    'ai_suite_prompt_manage_customprompttemplates' => [
        'path' => '/aisuite/prompt/manage/custom-prompt-templates',
        'target' => \AutoDudes\AiSuite\Controller\PromptTemplateController::class . '::handleRequest',
    ],
    'ai_suite_prompt_update_customprompttemplates' => [
        'path' => '/aisuite/prompt/update/custom-prompt-templates',
        'target' => \AutoDudes\AiSuite\Controller\PromptTemplateController::class . '::handleRequest',
    ],
    'ai_suite_prompt_activate_customprompttemplates' => [
        'path' => '/aisuite/prompt/activate/custom-prompt-templates',
        'target' => \AutoDudes\AiSuite\Controller\PromptTemplateController::class . '::handleRequest',
    ],
    'ai_suite_prompt_deactivate_customprompttemplates' => [
        'path' => '/aisuite/prompt/deactivate/custom-prompt-templates',
        'target' => \AutoDudes\AiSuite\Controller\PromptTemplateController::class . '::handleRequest',
    ],
    'ai_suite_prompt_delete_customprompttemplates' => [
        'path' => '/aisuite/prompt/delete/custom-prompt-templates',
        'target' => \AutoDudes\AiSuite\Controller\PromptTemplateController::class . '::handleRequest',
    ],
    /**
     * Content routes
     */
    'ai_suite_content_create' => [
        'path' => '/aisuite/content/create',
        'target' => \AutoDudes\AiSuite\Controller\ContentController::class . '::handleRequest',
    ],
    'ai_suite_content_request' => [
        'path' => '/aisuite/content/request',
        'target' => \AutoDudes\AiSuite\Controller\ContentController::class . '::handleRequest',
    ],
    'ai_suite_content_save' => [
        'path' => '/aisuite/content/save',
        'target' => \AutoDudes\AiSuite\Controller\ContentController::class . '::handleRequest',
    ],
    /**
     * Massaction routes
     */
    'ai_suite_massaction' => [
        'path' => '/aisuite/massaction',
        'target' => \AutoDudes\AiSuite\Controller\MassActionController::class . '::handleRequest',
    ],
    'ai_suite_massaction_pages_prepare' => [
        'path' => '/aisuite/massaction/pages-prepare',
        'target' => \AutoDudes\AiSuite\Controller\MassActionController::class . '::handleRequest',
    ],
    'ai_suite_massaction_filereferences_prepare' => [
        'path' => '/aisuite/massaction/file-references-prepare',
        'target' => \AutoDudes\AiSuite\Controller\MassActionController::class . '::handleRequest',
    ],
    'ai_suite_massaction_filelist_files_prepare' => [
        'path' => '/aisuite/massaction/files-prepare',
        'target' => \AutoDudes\AiSuite\Controller\FilelistController::class . '::handleRequest',
    ],
    'ai_suite_massaction_pages_translation_prepare' => [
        'path' => '/aisuite/massaction/pages-translation-prepare',
        'target' => \AutoDudes\AiSuite\Controller\MassActionController::class . '::handleRequest',
    ],
    /**
     * Background task routes
     */
    'ai_suite_backgroundtask' => [
        'path' => '/aisuite/backgroundtask',
        'target' => \AutoDudes\AiSuite\Controller\BackgroundTaskController::class . '::handleRequest',
    ],
];
