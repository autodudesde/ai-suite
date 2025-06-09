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

use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SiteService implements SingletonInterface
{
    protected SiteFinder $siteFinder;
    protected BasicAuthService $basicAuthService;
    protected TranslationConfigurationProvider $translationConfigurationProvider;

    public function __construct(
        ?SiteFinder $siteFinder = null,
        ?BasicAuthService $basicAuthService = null,
        ?TranslationConfigurationProvider $translationConfigurationProvider = null
    ) {
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->basicAuthService = $basicAuthService ?? GeneralUtility::makeInstance(BasicAuthService::class);
        $this->translationConfigurationProvider = $translationConfigurationProvider ?? GeneralUtility::makeInstance(TranslationConfigurationProvider::class);
    }

    public function getAvailableLanguages(bool $includeLanguageIds = false): array
    {
        $availableLanguages = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getLanguages() as $language) {
                if($includeLanguageIds) {
                    $availableLanguages[$language->getLocale()->getLanguageCode() . '__' . $language->getLanguageId()] = $language->getTitle();
                } else {
                    $availableLanguages[$language->getLocale()->getLanguageCode()] = $language->getTitle();
                }
            }
        }
        return $availableLanguages;
    }

    public function getSiteRootPageId(int $pageId): int
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
            return $site->getRootPageId();
        } catch (SiteNotFoundException $e) {
            return 0;
        }
    }

    /**
     * Get ISO code by language ID using TYPO3's built-in functionality
     *
     * @throws SiteNotFoundException
     */
    public function getIsoCodeByLanguageId(int $languageId, int $pageUid): string
    {
        try {
            $allSystemLanguages = $this->translationConfigurationProvider->getSystemLanguages($pageUid);
            $sites = $this->siteFinder->getAllSites();
            $updatedSystemLanguages = [];
            foreach ($sites as $site) {
                $updatedSystemLanguages = $this->addSiteLanguagesToConsolidatedList(
                    $allSystemLanguages,
                    $site->getAvailableLanguages($GLOBALS['BE_USER'], true)
                );
            }
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            if($languageId === -1) {
                $languageId = $site->getDefaultLanguage()->getLanguageId();
            }
            foreach ($updatedSystemLanguages as $language) {
                if ($language['uid'] === $languageId) {
                    return $language['isoCode'] ?? '';
                }
            }
            throw new SiteNotFoundException('No language found for language id ' . $languageId . ' and page uid ' . $pageUid, 1521716621);
        } catch (\Exception $e) {
            throw new SiteNotFoundException('No site found for language id ' . $languageId . ' and page uid ' . $pageUid, 1521716622);
        }
    }

    protected function addSiteLanguagesToConsolidatedList(array $allSystemLanguages, array $languagesOfSpecificSite): array
    {
        foreach ($languagesOfSpecificSite as $language) {
            $languageId = $language->getLanguageId();
            if (isset($allSystemLanguages[$languageId])) {
                $allSystemLanguages[$languageId]['isoCode'] = $language->getLocale()->getLanguageCode();
            } else {
                $allSystemLanguages[$languageId] = [
                    'uid' => $languageId,
                    'isoCode' => $language->getLocale()->getLanguageCode(),
                ];
            }
        }
        return $allSystemLanguages;
    }

    public function buildAbsoluteUri(UriInterface $uri): string {
        $port = $uri->getPort() ? ':' . $uri->getPort() : '';
        $basicAuth = $this->basicAuthService->getBasicAuth();
        $absoluteUri = $uri->getScheme() . '://' . $basicAuth . $uri->getHost() . $port . $uri->getPath();
        if ($uri->getScheme() === '' || $uri->getHost() === '') {
            $request = $GLOBALS['TYPO3_REQUEST'];
            $uri = $uri->withScheme($request->getUri()->getScheme());
            $uri = $uri->withHost($request->getUri()->getHost());
            $uri = $uri->withPort($request->getUri()->getPort());
            $port = $uri->getPort() ? ':' . $uri->getPort() : '';
            $absoluteUri = $uri->getScheme() . '://' . $basicAuth . $uri->getHost() . $port . $uri->getPath();
        }
        if ($uri->getQuery() !== '') {
            return $absoluteUri . '?' . $uri->getQuery();
        }
        return $absoluteUri;
    }
}
