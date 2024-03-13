<?php

return [
    'ai_suite_record_edit' => [
        'path' => 'ai/suite/record/edit',
        'target' => \AutoDudes\AiSuite\Controller\ContentElementController::class . '::createContentElementAction',
        'redirect' => [
            'enable' => true,
            'parameters' => [
                'edit' => true,
            ],
        ],
    ],
];
