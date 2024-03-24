<?php

$EM_CONF['ai_suite'] = [
    'title' => 'AI Suite',
    'description' => 'Adds a backend module as well as various helper functionalities and features to your TYPO3 backend, which are intended to make the daily work of editors easier and faster with the help of AI.',
    'category' => 'be',
    'author' => 'Manuel Schnabel, André Kraus',
    'author_email' => 'service@autodudes.de',
    'author_company' => 'AutoDudes',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
            'bootstrap_package' => '14.0.0-14.99.99',
            'content_blocks' => '0.6.0-1.0.0',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'AutoDudes\\AiSuite\\' => 'Classes'
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'clearCacheOnLoad' => 1,
    'version' => '12.0.3',
];
