<?php

return [
    'ai_suite_record_edit' => [
        'path' => 'ai/suite/record/edit',
        'target' => \AutoDudes\AiSuite\Controller\ContentController::class . '::createContentAction',
        'redirect' => [
            'enable' => true,
            'parameters' => [
                'edit' => true,
            ],
        ],
    ],
];
