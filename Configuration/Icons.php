<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'tx-aisuite-extension' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Extension.svg',
    ],
    'tx-aisuite-permissions' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Extension.svg',
    ],
    'tx-aisuite-model-check-icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/check.svg',
    ],
    'tx-aisuite-model-ChatGPT' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Models/ChatGPT.svg',
    ],
    'tx-aisuite-model-Anthropic' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Models/Anthropic.svg',
    ],
    'tx-aisuite-model-GoogleTranslate' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Models/GoogleTranslate.svg',
    ],
    'tx-aisuite-model-Deepl' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Models/DeepL.svg',
    ],
    'tx-aisuite-model-AiSuiteTextUltimate' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Models/AiSuiteTextUltimate.svg',
    ],
    'tx-aisuite-model-DALL-E' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Models/DALL-E.svg',
    ],
    'tx-aisuite-model-Midjourney' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Models/Midjourney.svg',
    ],
    'tx-aisuite-model-Vision' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Models/Vision.svg',
    ],
    'tx-aisuite-model-Flux' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/Models/Flux.svg',
    ],
    'tx-aisuite-translate-action-finished' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/actions-translate-finished.svg',
    ],
    'tx-aisuite-translate-action-pending' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/actions-translate-pending.svg',
    ],
    'tx-aisuite-translate-action-error' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_suite/Resources/Public/Icons/actions-translate-error.svg',
    ],
];
