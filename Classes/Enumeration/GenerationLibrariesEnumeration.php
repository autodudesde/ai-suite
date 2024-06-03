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

final class GenerationLibrariesEnumeration
{
    public const ERROR = '';
    public const IMAGE = 'image';
    public const METADATA = 'text';
    public const PAGETREE = 'text';
    public const GOOGLE_TRANSLATE = 'translate';
    public const CONTENT = 'text,image';
    public const CREATE_FACEBOOK_POST = 'text,image';
}
