<?php

$EM_CONF['ai_suite'] = [
    'title' => 'AI Suite',
    'description' => 'The AI Suite optimizes the workflow of project managers, agencies and freelancers by using the latest AI technologies. It seamlessly integrates a wide variety of AI interfaces and the AI Suite open source models into the TYPO3 backend. Enables, among other things, more efficient creation and management of image and page metadata (individually and as batch processing), content translations (including "Easy Language"), content creation and modification, image generation and page structure generation.',
    'category' => 'be',
    'author' => 'Manuel Schnabel, André Kraus',
    'author_email' => 'service@autodudes.de',
    'author_company' => 'AutoDudes',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99'
        ],
        'conflicts' => [
            'ai_seo_helper' => '0.1.0-1.9.99'
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
    'version' => '13.7.0',
];
