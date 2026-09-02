<?php

use AutoDudes\AiSuite\Controller\AgencyController;
use AutoDudes\AiSuite\Controller\AiSuiteController;
use AutoDudes\AiSuite\Controller\AuditController;
use AutoDudes\AiSuite\Controller\BackgroundTaskController;
use AutoDudes\AiSuite\Controller\CliOverviewController;
use AutoDudes\AiSuite\Controller\FilelistController;
use AutoDudes\AiSuite\Controller\GlobalInstructionController;
use AutoDudes\AiSuite\Controller\PagesController;
use AutoDudes\AiSuite\Controller\PromptTemplateController;
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
        'iconIdentifier' => 'tx-aisuite-extension',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'labels' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => AiSuiteController::class.'::handleRequest',
            ],
            'audit' => [
                'path' => '/audit',
                'target' => AuditController::class.'::handleRequest',
            ],
            'workflow' => [
                'path' => '/workflow',
                'target' => WorkflowManagerController::class.'::handleRequest',
            ],
            'backgroundtask' => [
                'path' => '/background-task',
                'target' => BackgroundTaskController::class.'::handleRequest',
            ],
            'global_instructions' => [
                'path' => '/global-instructions',
                'target' => GlobalInstructionController::class.'::handleRequest',
            ],
            'prompt' => [
                'path' => '/prompt',
                'target' => PromptTemplateController::class.'::handleRequest',
            ],
            'page' => [
                'path' => '/page',
                'target' => PagesController::class.'::handleRequest',
            ],
            'agencies' => [
                'path' => '/agencies',
                'target' => AgencyController::class.'::handleRequest',
            ],
            'settings' => [
                'path' => '/settings',
                'target' => SettingsController::class.'::handleRequest',
            ],
            'statistics' => [
                'path' => '/statistics',
                'target' => StatisticsController::class.'::handleRequest',
            ],
        ],
    ],
    'files_aisuite' => [
        'parent' => 'file',
        'position' => ['after' => 'media_management'],
        'access' => 'user',
        'workspaces' => '*',
        'path' => '/module/file/aisuite',
        'iconIdentifier' => 'tx-aisuite-extension',
        'labels' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => FilelistController::class.'::handleRequest',
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
        'iconIdentifier' => 'tx-aisuite-extension',
        'labels' => [
            'title' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab_cli',
            'shortDescription' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tablabel',
            'description' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tabdescr',
        ],
        'routes' => [
            '_default' => [
                'target' => CliOverviewController::class.'::handleRequest',
            ],
        ],
    ],
];
