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
                $availableLanguages[$language->getTwoLetterIsoCode()] = $language->getTitle();
            }
        }
        return $availableLanguages;
    }

    public static function getAvailableDefaultLanguages(): array
    {
        $availableSites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
        $availableDefaultLanguages = [];
        foreach ($availableSites as $site) {
            foreach ($site->getLanguages() as $language) {
                if($language->getTypo3Language() === 'default') {
                    $availableDefaultLanguages[$language->getTwoLetterIsoCode()] = $language->getTitle();
                }
            }
        }
        return $availableDefaultLanguages;
    }

    /**
     * @throws SiteNotFoundException
     */
    public static function getIsoCodeByLanguageId(int $languageId): string
    {
        $availableSites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
        foreach ($availableSites as $site) {
            foreach ($site->getLanguages() as $language) {
                if($language->getLanguageId() === $languageId) {
                    return $language->getTwoLetterIsoCode();
                }
            }
        }
        throw new SiteNotFoundException('No site found for language id ' . $languageId);
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
}
