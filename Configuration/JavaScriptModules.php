<?php

return [
    'dependencies' => ['core', 'backend'],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@autodudes/ai-suite/' => 'EXT:ai_suite/Resources/Public/JavaScript/',
//        '@typo3/backend/localization.js' => 'EXT:ai_suite/Resources/Public/JavaScript/extended-localization.js',
    ],
];
