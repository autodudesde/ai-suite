<?php

return [
    'web_aisuite' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/page/aisuite',
        'iconIdentifier' => 'tx-aisuite-extension',
        'labels' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => \AutoDudes\AiSuite\Controller\AiSuiteController::class . '::handleRequest',
            ],
        ],
    ],
    'files_aisuite' => [
        'parent' => 'file',
        'position' => ['after' => 'media_management'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/file/aisuite',
        'iconIdentifier' => 'tx-aisuite-extension',
        'labels' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => \AutoDudes\AiSuite\Controller\FilelistController::class . '::handleRequest',
            ],
        ],
        'moduleData' => [
            'displayThumbs' => true,
            'clipBoard' => true,
            'sort' => 'file',
            'reverse' => false,
            'viewMode' => null,
        ],
    ]
];
