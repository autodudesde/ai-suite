<?php

declare(strict_types=1);

use AutoDudes\AiSuite\Middleware\ContextualRecordEditAssetsMiddleware;
use AutoDudes\AiSuite\Middleware\ParameterTrackingMiddleware;
use AutoDudes\AiSuite\Middleware\RoutePermissionMiddleware;

return [
    'backend' => [
        'autodudes/ai-suite/route-permission' => [
            'target' => RoutePermissionMiddleware::class,
            'after' => [
                'typo3/cms-backend/backend-module-validator',
            ],
            'before' => [
                'autodudes/ai-suite/parameter-tracking',
                'autodudes/ai-suite/contextual-record-edit-assets',
                'typo3/cms-backend/output-compression',
            ],
        ],
        'autodudes/ai-suite/parameter-tracking' => [
            'target' => ParameterTrackingMiddleware::class,
            'after' => [
                'typo3/cms-backend/backend-module-validator',
            ],
            'before' => [
                'typo3/cms-backend/output-compression',
            ],
        ],
        'autodudes/ai-suite/contextual-record-edit-assets' => [
            'target' => ContextualRecordEditAssetsMiddleware::class,
            'after' => [
                'typo3/cms-backend/backend-module-validator',
            ],
            'before' => [
                'typo3/cms-backend/output-compression',
            ],
        ],
    ],
];
