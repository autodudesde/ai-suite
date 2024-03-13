<?php

return [
    'description_generation' => [
        'path' => '/generate/meta-description',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateMetaDescriptionAction'
    ],
    'keywords_generation' => [
        'path' => '/generate/keywords',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateKeywordsAction'
    ],
    'seo_title_generation' => [
        'path' => '/generate/page-title',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generatePageTitleAction'
    ],
    'og_title_generation' => [
        'path' => '/generate/og-title',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateOgTitleAction'
    ],
    'twitter_title_generation' => [
        'path' => '/generate/twitter-title',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateTwitterTitleAction'
    ],
    'og_description_generation' => [
        'path' => '/generate/og-description',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateOgDescriptionAction'
    ],
    'twitter_description_generation' => [
        'path' => '/generate/twitter-description',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateTwitterDescriptionAction'
    ],
    'news_description_generation' => [
        'path' => '/generate/news-meta-description',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateNewsMetaDescriptionAction'
    ],
    'news_alternative_title_generation' => [
        'path' => '/generate/news-alternative-title',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateNewsAlternativeTitleAction'
    ],
    'news_keywords_generation' => [
        'path' => '/generate/news-keywords',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateNewsKeywordsAction'
    ],
    'aisuite_image_generation_slide_one' => [
        'path' => '/generate/ai-image-slide-one',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::getImageWizardSlideOneAction'
    ],
    'aisuite_image_generation_slide_two' => [
        'path' => '/generate/ai-image-slide-two',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::getImageWizardSlideTwoAction'
    ],
    'aisuite_image_generation_save' => [
        'path' => '/generate/ai-image-save',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::saveGeneratedImageAction'
    ],
    'aisuite_regenerate_images' => [
        'path' => '/generate/ai-image-regenerate',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::regenerateImageAction'
    ],
];
