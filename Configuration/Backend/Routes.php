<?php

use AutoDudes\AiSuite\Controller\AgencyController;
use AutoDudes\AiSuite\Controller\AiSuiteController;
use AutoDudes\AiSuite\Controller\BackgroundTaskController;
use AutoDudes\AiSuite\Controller\ContentController;
use AutoDudes\AiSuite\Controller\FilelistController;
use AutoDudes\AiSuite\Controller\GlobalInstructionController;
use AutoDudes\AiSuite\Controller\PagesController;
use AutoDudes\AiSuite\Controller\PromptTemplateController;
use AutoDudes\AiSuite\Controller\SettingsController;
use AutoDudes\AiSuite\Controller\Workflow\WorkflowManagerController;

return [
    'ai_suite_record_edit' => [
        'path' => 'ai/suite/record/edit',
        'target' => ContentController::class.'::handleRequest',
        'redirect' => [
            'enable' => true,
            'parameters' => [
                'edit' => true,
            ],
        ],
    ],
    'ai_suite_dashboard' => [
        'path' => '/aisuite/dashboard',
        'target' => AiSuiteController::class.'::handleRequest',
    ],
    // Page routes
    'ai_suite_page' => [
        'path' => '/aisuite/page',
        'target' => PagesController::class.'::handleRequest',
    ],
    'ai_suite_page_create_pagetree' => [
        'path' => '/aisuite/page/create/pagetree',
        'target' => PagesController::class.'::handleRequest',
    ],
    'ai_suite_page_validate_pagetree' => [
        'path' => '/aisuite/page/validate/pagetree',
        'methods' => ['POST'],
        'target' => PagesController::class.'::handleRequest',
    ],
    'ai_suite_page_validate_pagetree_create' => [
        'path' => '/aisuite/page/validate/pagetree/create',
        'methods' => ['POST'],
        'target' => PagesController::class.'::handleRequest',
    ],
    // Agencies routes
    'ai_suite_agencies' => [
        'path' => '/aisuite/agencies',
        'target' => AgencyController::class.'::handleRequest',
    ],
    'ai_suite_agencies_translate_xlf' => [
        'path' => '/aisuite/agencies/translate/xlf',
        'target' => AgencyController::class.'::handleRequest',
    ],
    'ai_suite_agencies_validate_xlf' => [
        'path' => '/aisuite/agencies/validate/xlf',
        'target' => AgencyController::class.'::handleRequest',
    ],
    'ai_suite_agencies_write_xlf' => [
        'path' => '/aisuite/agencies/write/xlf',
        'target' => AgencyController::class.'::handleRequest',
    ],
    // Prompt routes
    'ai_suite_prompt' => [
        'path' => '/aisuite/prompt',
        'target' => PromptTemplateController::class.'::handleRequest',
    ],
    'ai_suite_prompt_update_serverprompttemplates' => [
        'path' => '/aisuite/prompt/update/server-prompt-templates',
        'target' => PromptTemplateController::class.'::handleRequest',
    ],
    'ai_suite_prompt_manage_customprompttemplates' => [
        'path' => '/aisuite/prompt/manage/custom-prompt-templates',
        'target' => PromptTemplateController::class.'::handleRequest',
    ],
    'ai_suite_prompt_update_customprompttemplates' => [
        'path' => '/aisuite/prompt/update/custom-prompt-templates',
        'target' => PromptTemplateController::class.'::handleRequest',
    ],
    'ai_suite_prompt_activate_customprompttemplates' => [
        'path' => '/aisuite/prompt/activate/custom-prompt-templates',
        'target' => PromptTemplateController::class.'::handleRequest',
    ],
    'ai_suite_prompt_deactivate_customprompttemplates' => [
        'path' => '/aisuite/prompt/deactivate/custom-prompt-templates',
        'target' => PromptTemplateController::class.'::handleRequest',
    ],
    'ai_suite_prompt_delete_customprompttemplates' => [
        'path' => '/aisuite/prompt/delete/custom-prompt-templates',
        'target' => PromptTemplateController::class.'::handleRequest',
    ],
    // Global Instructions routes
    'ai_suite_global_instructions' => [
        'path' => '/aisuite/global-instructions',
        'target' => GlobalInstructionController::class.'::handleRequest',
    ],
    'ai_suite_global_instructions_activate' => [
        'path' => '/aisuite/global-instructions/activate',
        'target' => GlobalInstructionController::class.'::handleRequest',
    ],
    'ai_suite_global_instructions_deactivate' => [
        'path' => '/aisuite/global-instructions/deactivate',
        'target' => GlobalInstructionController::class.'::handleRequest',
    ],
    'ai_suite_global_instructions_delete' => [
        'path' => '/aisuite/global-instructions/delete',
        'target' => GlobalInstructionController::class.'::handleRequest',
    ],
    // Content routes
    'ai_suite_content_create' => [
        'path' => '/aisuite/content/create',
        'target' => ContentController::class.'::handleRequest',
    ],
    'ai_suite_content_request' => [
        'path' => '/aisuite/content/request',
        'target' => ContentController::class.'::handleRequest',
    ],
    'ai_suite_content_save' => [
        'path' => '/aisuite/content/save',
        'target' => ContentController::class.'::handleRequest',
    ],
    // Workflow routes
    'ai_suite_workflow' => [
        'path' => '/aisuite/workflow',
        'target' => WorkflowManagerController::class.'::handleRequest',
    ],
    'ai_suite_workflow_pages_prepare' => [
        'path' => '/aisuite/workflow/pages-prepare',
        'target' => WorkflowManagerController::class.'::handleRequest',
    ],
    'ai_suite_workflow_filereferences_prepare' => [
        'path' => '/aisuite/workflow/file-references-prepare',
        'target' => WorkflowManagerController::class.'::handleRequest',
    ],
    'ai_suite_workflow_filelist_files_prepare' => [
        'path' => '/aisuite/workflow/files-prepare',
        'target' => FilelistController::class.'::handleRequest',
    ],
    'ai_suite_workflow_pages_translation_prepare' => [
        'path' => '/aisuite/workflow/pages-translation-prepare',
        'target' => WorkflowManagerController::class.'::handleRequest',
    ],
    // Background task routes
    'ai_suite_backgroundtask' => [
        'path' => '/aisuite/backgroundtask',
        'target' => BackgroundTaskController::class.'::handleRequest',
    ],
    'ai_suite_workflow_filelist_files_translate_prepare' => [
        'path' => '/aisuite/workflow/files-translate-prepare',
        'target' => FilelistController::class.'::handleRequest',
    ],
    // Settings routes
    'ai_suite_settings' => [
        'path' => '/aisuite/settings',
        'target' => SettingsController::class.'::handleRequest',
    ],
    'ai_suite_settings_save' => [
        'path' => '/aisuite/settings/save',
        'methods' => ['POST'],
        'target' => SettingsController::class.'::handleRequest',
    ],
    // Filelist routes
    'ai_suite_filelist' => [
        'path' => '/aisuite/filelist',
        'target' => FilelistController::class.'::handleRequest',
    ],
];
