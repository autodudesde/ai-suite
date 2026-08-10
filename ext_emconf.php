<?php

$EM_CONF['ai_suite'] = [
    'title' => 'AI Suite',
    'description' => 'AI Suite brings powerful, GDPR-compliant AI directly into the TYPO3 backend. Generate and manage image and page metadata, translate content (including "Easy Language"), create and refine copy, and much more, both as single actions and in bulk. Built for agencies, freelancers and editorial teams who want to ship faster.',
    'category' => 'be',
    'author' => 'Manuel Schnabel, André Kraus',
    'author_email' => 'service@autodudes.de',
    'author_company' => 'AutoDudes',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.11-12.4.99'
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
    'version' => '12.22.1',
];
