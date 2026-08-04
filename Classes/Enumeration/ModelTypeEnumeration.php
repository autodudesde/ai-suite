<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Enumeration;

final class ModelTypeEnumeration
{
    // general models
    public const TEXT = 'CHATGPT,Vision';
    public const IMAGE = 'GPTImage,Midjourney,Flux';
    public const TRANSLATE = 'GoogleTranslate';
    public const CHAT = 'OpenAiLuna,IonosQwen35,ClaudeHaiku45';

    // model - api mapping
    public const CHATGPT = 'openAiApiKey';
    public const ANTHROPIC = 'anthropicApiKey';
    public const VISION = 'openAiApiKey';
    public const GPTIMAGE = 'openAiApiKey';
    public const MIDJOURNEY = 'midjourneyApiKey,midjourneyId';
    public const FLUX = 'aiModelHubApiKey';
    public const GOOGLETRANSLATE = 'googleTranslateApiKey';
    public const DEEPL = 'deeplApiKey,deeplApiMode';
    public const DEEPLGLOSSARYMANAGER = 'deeplApiKey,deeplApiMode';
    public const AISUITETEXTULTIMATE = '';

    public const MITTWALDMINISTRAL14B = 'mittwaldAiModelHubApiKey';
    public const MITTWALDMINISTRAL14BVISION = 'mittwaldAiModelHubApiKey';

    public const OPENAILUNA = '';
    public const IONOSQWEN35 = '';
    public const CLAUDEHAIKU45 = '';
}
