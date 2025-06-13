<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Enumeration;

final class ModelTypeEnumeration
{
    // general models
    public const TEXT = 'CHATGPT,Vision';
    public const IMAGE = 'DALL-E,Midjourney';
    public const TRANSLATE = 'GoogleTranslate';

    // model - api mapping
    public const CHATGPT = 'openAiApiKey';
    public const ANTHROPIC = 'anthropicApiKey';
    public const VISION = 'openAiApiKey';
    public const DALLE = 'openAiApiKey';
    public const MIDJOURNEY = 'midjourneyApiKey,midjourneyId';
    public const GOOGLETRANSLATE = 'googleTranslateApiKey';
    public const DEEPL = 'deeplApiKey,deeplApiMode';
    public const DEEPLGLOSSARYMANAGER = 'deeplApiKey,deeplApiMode';
    public const AISUITETEXTULTIMATE = '';
}
