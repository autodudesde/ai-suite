<?php

return [
    'backend' => [
        'autodudes/ai-suite/parameter-tracking' => [
            'target' => \AutoDudes\AiSuite\Middleware\ParameterTrackingMiddleware::class,
            'after' => [
                'typo3/cms-backend/backend-module-validator',
            ],
            'before' => [
                'typo3/cms-backend/output-compression',
            ],
        ],
    ],
];
