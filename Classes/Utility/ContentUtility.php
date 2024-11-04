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

class ContentUtility
{
    public const IGNORE_FIELDS_BY_RECORD = [
        'tx_news_domain_model_news' => [
            'tx_news_domain_model_link',
            'tt_content',
        ]
    ];
    public static function cleanupRequestField(array $requestFields, $table): array
    {
        if (array_key_exists($table, self::IGNORE_FIELDS_BY_RECORD)) {
            $ignoreFields = self::IGNORE_FIELDS_BY_RECORD[$table];
            foreach ($ignoreFields as $ignoreField) {
                unset($requestFields[$ignoreField]);
            }
        }
        return $requestFields;
    }
}
