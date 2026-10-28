<?php

declare(strict_types=1);

use AutoDudes\AiSuite\Controller\AboutController;
use AutoDudes\AiSuite\Controller\AgencyController;
use AutoDudes\AiSuite\Controller\AiSuiteController;
use AutoDudes\AiSuite\Controller\AuditController;
use AutoDudes\AiSuite\Controller\BackgroundTaskController;
use AutoDudes\AiSuite\Controller\CliOverviewController;
use AutoDudes\AiSuite\Controller\FilelistController;
use AutoDudes\AiSuite\Controller\GlobalInstructionController;
use AutoDudes\AiSuite\Controller\PagesController;
use AutoDudes\AiSuite\Controller\PromptTemplateController;
use AutoDudes\AiSuite\Controller\ProvenanceOverviewController;
use AutoDudes\AiSuite\Controller\SettingsController;
use AutoDudes\AiSuite\Controller\StatisticsController;
use AutoDudes\AiSuite\Controller\Workflow\WorkflowManagerController;

return [
    'web_aisuite' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'workspaces' => '*',
        'path' => '/module/page/aisuite',
        'iconIdentifier' => 'tx-aisuite-extension-v14',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'labels' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => AiSuiteController::class.'::handleRequest',
                'aiSuitePermission' => [],
            ],
            'audit' => [
                'path' => '/audit',
                'target' => AuditController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_audit'],
            ],
            'workflow' => [
                'path' => '/workflow',
                'target' => WorkflowManagerController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_massaction_generation'],
            ],
            'backgroundtask' => [
                'path' => '/background-task',
                'target' => BackgroundTaskController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_background_task_handling'],
            ],
            'global_instructions' => [
                'path' => '/global-instructions',
                'target' => GlobalInstructionController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_global_instructions_button'],
            ],
            'prompt' => [
                'path' => '/prompt',
                'target' => PromptTemplateController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_prompt_template_button'],
            ],
            'page' => [
                'path' => '/page',
                'target' => PagesController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_pages_generation'],
            ],
            'agencies' => [
                'path' => '/agencies',
                'target' => AgencyController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_agency'],
            ],
            'settings' => [
                'path' => '/settings',
                'target' => SettingsController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_global_settings'],
            ],
            'statistics' => [
                'path' => '/statistics',
                'target' => StatisticsController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_statistics'],
            ],
            'provenance' => [
                'path' => '/provenance',
                'target' => ProvenanceOverviewController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_provenance_overview'],
            ],
            'about' => [
                'path' => '/about',
                'target' => AboutController::class.'::handleRequest',
                'aiSuitePermission' => [],
            ],
        ],
    ],
    'files_aisuite' => [
        'parent' => 'file',
        'position' => ['after' => 'media_management'],
        'access' => 'user',
        'workspaces' => '*',
        'path' => '/module/file/aisuite',
        'iconIdentifier' => 'tx-aisuite-extension-v14',
        'labels' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => FilelistController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_translation_sys_file_metadata', 'enable_massaction_generation'],
            ],
            'files_prepare' => [
                'path' => '/files-prepare',
                'target' => FilelistController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_massaction_generation'],
            ],
            'files_translate_prepare' => [
                'path' => '/files-translate-prepare',
                'target' => FilelistController::class.'::handleRequest',
                'aiSuitePermission' => ['enable_translation_sys_file_metadata', 'enable_massaction_generation'],
            ],
        ],
        'moduleData' => [
            'displayThumbs' => true,
            'clipBoard' => true,
            'sort' => 'file',
            'reverse' => false,
            'viewMode' => null,
        ],
    ],
    'tools_aisuite_clioverview' => [
        'parent' => 'tools',
        'position' => ['after' => 'scheduler'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/tools/aisuite-clioverview',
        'iconIdentifier' => 'tx-aisuite-extension-v14',
        'labels' => [
            'title' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab_cli',
            'shortDescription' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tablabel',
            'description' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tabdescr',
        ],
        'routes' => [
            '_default' => [
                'target' => CliOverviewController::class.'::handleRequest',
                'aiSuitePermission' => [],
            ],
        ],
    ],
];
