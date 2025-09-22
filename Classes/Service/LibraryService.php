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

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;

class LibraryService implements SingletonInterface
{
    protected BackendUserService $backendUserService;

    public function __construct(BackendUserService $backendUserService)
    {
        $this->backendUserService = $backendUserService;
    }

    public function prepareLibraries(array $libraries, string $selectedLibraryKey = ''): array
    {
        $processedLibraries = [];

        foreach ($libraries as $library) {
            if (!$this->backendUserService->getBackendUser()->isAdmin() &&
                !$this->backendUserService->checkPermissions('tx_aisuite_models:' . $library['model_identifier'])
            ) {
                continue;
            }
            if ($library['model_identifier'] === $selectedLibraryKey) {
                $library['checked'] = true;
            } else {
                $library['checked'] = false;
            }
            $processedLibraries[] = $library;
        }
        if (empty($selectedLibraryKey) && count($processedLibraries) > 0) {
            $processedLibraries[0]['checked'] = true;
        }
        return $processedLibraries;
    }

    public function prepareAdditionalImageSettings(string $additionalImageSettings): array
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
