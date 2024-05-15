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

use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SiteUtility
{
    public static function getAvailableSites()
    {
        return GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
    }

    public static function getAvailableLanguages(): array
    {
        $availableSites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
        $availableLanguages = [];
        foreach ($availableSites as $site) {
            foreach ($site->getLanguages() as $language) {
                $availableLanguages[$language->getLocale()->getLanguageCode()] = $language->getTitle();
            }
        }
        return $availableLanguages;
    }

    public static function getAvailableRootPages(): array
    {
        $availableSites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
        $availableRootPages = [];
        foreach ($availableSites as $site) {
            $availableRootPages[] = $site->getRootPageId();
        }
        return $availableRootPages;
    }
    public static function getSiteByRootPage(int $rootPageId, int $languageId): ?UriInterface
    {
        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($rootPageId);
            return $site->getRouter()->generateUri($site->getLanguageById($languageId));
        } catch (SiteNotFoundException $e) {
            return null;
        }
    }
}
