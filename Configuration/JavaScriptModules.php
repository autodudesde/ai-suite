<?php

return [
    'dependencies' => ['core', 'backend', 'rte_ckeditor'],
    'tags' => [
        'backend.form',
        'backend.contextmenu',
    ],
    'imports' => [
        '@autodudes/ai-suite/' => 'EXT:ai_suite/Resources/Public/JavaScript/',
    ],
];
