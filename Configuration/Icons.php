<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'tx-aisuite-localization-ChatGPT' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/openai.svg',
    ],
    'tx-aisuite-localization-Anthropic' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/anthropic.svg',
    ],
    'tx-aisuite-localization-GoogleTranslate' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/google-translate.svg',
    ],
    'tx-aisuite-localization-Deepl' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/deepl.svg',
    ],
    'tx-aisuite-localization' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Extension.svg',
    ],
];
