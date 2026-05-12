<?php

use AutoDudes\AiSuite\Controller\AiSuiteController;
use AutoDudes\AiSuite\Controller\CliOverviewController;
use AutoDudes\AiSuite\Controller\FilelistController;

return [
    'web_aisuite' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'workspaces' => '*',
        'path' => '/module/page/aisuite',
        'iconIdentifier' => 'tx-aisuite-extension-v14',
        'labels' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => AiSuiteController::class.'::handleRequest',
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
            ],
        ],
    ],
];
