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

class LibraryUtility
{
    public static function prepareLibraries(array $libraries, string $selectedLibraryKey = ''): array
    {
        foreach ($libraries as $key => $library) {
            if(!BackendUserUtility::isAdmin() &&
                !BackendUserUtility::checkPermissions('tx_aisuite_models:' . $library['model_identifier'])
            ) {
                unset($libraries[$key]);
                continue;
            }
            if ($library['model_identifier'] === $selectedLibraryKey) {
                $libraries[$key]['checked'] = true;
            } else {
                $libraries[$key]['checked'] = false;
            }
        }
        if (empty($selectedLibraryKey) && count($libraries) > 0) {
            $firstKey = array_key_first($libraries);
            $libraries[$firstKey]['checked'] = true;
        }
        return $libraries;
    }

    public static function prepareAdditionalImageSettings(string $additionalImageSettings): array
    {
        $additionalImageSettingsArray = explode(' ', $additionalImageSettings);
        $additionalImageSettingsArray = array_filter($additionalImageSettingsArray);
        $returnArray = [];
        $activeKey = '';
        foreach ($additionalImageSettingsArray as $value) {
            if (str_contains($value, '--')) {
                $returnArray[substr($value, 2)] = '';
                $activeKey = substr($value, 2);
            }
            if ($activeKey !== '' && !str_contains($value, '--')) {
                $returnArray[$activeKey] .= $value;
            }
        }
        $returnArray['v'] = $returnArray['v'] ?? '';
        $returnArray['ar'] = $returnArray['ar'] ?? '';
        $returnArray['no'] = $returnArray['no'] ?? 'text';
        $returnArray['sref'] = $returnArray['sref'] ?? '';

        return $returnArray;
    }
}
