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

namespace AutoDudes\AiSuite\Utility;

class ControllerUtility
{
    public static function toSearchableDropdown(array $items, string $valueField, string $labelField): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[$item[$valueField]] = $item[$labelField];
        }
        return $result;
    }
}
