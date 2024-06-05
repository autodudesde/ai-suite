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
            'typo3' => '11.5.0-11.5.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
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
    'version' => '11.0.1',
];
