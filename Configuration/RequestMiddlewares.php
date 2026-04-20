<?php

use AutoDudes\AiSuite\Middleware\ParameterTrackingMiddleware;

return [
    'backend' => [
        'autodudes/ai-suite/parameter-tracking' => [
            'target' => ParameterTrackingMiddleware::class,
            'after' => [
                'typo3/cms-backend/backend-module-validator',
            ],
            'before' => [
                'typo3/cms-backend/output-compression',
            ],
        ],
    ],
];
