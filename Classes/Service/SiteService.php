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

namespace AutoDudes\AiSuite\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SiteService implements SingletonInterface
{
    protected SiteFinder $siteFinder;
    protected TranslationConfigurationProvider $translationConfigurationProvider;
    protected BackendUserService $backendUserService;

    public function __construct(
        ?SiteFinder $siteFinder = null,
        ?TranslationConfigurationProvider $translationConfigurationProvider = null,
        ?BackendUserService $backendUserService = null
    ) {
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->translationConfigurationProvider = $translationConfigurationProvider ?? GeneralUtility::makeInstance(TranslationConfigurationProvider::class);
        $this->backendUserService = $backendUserService ?? GeneralUtility::makeInstance(BackendUserService::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAvailableLanguages(bool $includeLanguageIds = false, int $pageId = 0, bool $onlyDefault = false): array
    {
        $backendUser = $this->backendUserService->getBackendUser();
        if (null === $backendUser) {
            return [];
        }
        $availableLanguages = [];
        $sites = $this->siteFinder->getAllSites();
        if ($pageId > 0) {
            $sites = [$this->siteFinder->getSiteByPageId($pageId)];
        }
        foreach ($sites as $site) {
            $siteLanguages = $site->getAvailableLanguages($backendUser, true);
            foreach ($siteLanguages as $language) {
                $languageId = $language->getLanguageId();
                if (-1 === $languageId) {
                    continue;
                }
                if ($onlyDefault && 0 !== $languageId) {
                    continue;
                }
                if ($includeLanguageIds) {
                    $title = $language->getTitle();
                    $languageBase = $language->getBase();
                    if (!empty($languageBase->getHost())) {
                        $title .= ' ['.$languageBase->getHost().']';
                    } else {
                        $title .= ' [Site: '.$site->getIdentifier().']';
                    }
                    $availableLanguages[$language->getLocale()->getLanguageCode().'__'.$languageId.'__'.$site->getIdentifier()] = $title;
                } else {
                    $availableLanguages[$language->getLocale()->getLanguageCode()] = $language->getTitle();
                }
            }
        }

        return $availableLanguages;
    }

    /**
     * @return array<int, string>
     */
    public function getLanguageFlagsByLanguageId(int $languageId): array
    {
        $backendUser = $this->backendUserService->getBackendUser();
        if (null === $backendUser) {
            return [];
        }
        $languageFlags = [];
        $sites = $this->siteFinder->getAllSites();

        foreach ($sites as $site) {
            $siteLanguages = $site->getAvailableLanguages($backendUser, true);
            foreach ($siteLanguages as $language) {
                if ($language->getLanguageId() === $languageId) {
                    $languageFlags[] = $language->getFlagIdentifier();
                }
            }
        }

        return array_unique($languageFlags);
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
     * @throws SiteNotFoundException
     */
    public function getIsoCodeByLanguageId(int $languageId, int $pageUid): string
    {
        try {
            $backendUser = $this->backendUserService->getBackendUser();
            if (null === $backendUser) {
                throw new SiteNotFoundException('No backend user available');
            }
            $allSystemLanguages = $this->translationConfigurationProvider->getSystemLanguages($pageUid);
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            $updatedSystemLanguages = $this->addSiteLanguagesToConsolidatedList(
                $allSystemLanguages,
                $site->getAvailableLanguages($backendUser, true)
            );
            if (-1 === $languageId) {
                $languageId = $site->getDefaultLanguage()->getLanguageId();
            }
            foreach ($updatedSystemLanguages as $language) {
                if ($language['uid'] === $languageId) {
                    $isoCode = $language['isoCode'] ?? '';

                    return $this->applyLanguageMapping($site, $isoCode);
                }
            }

            throw new SiteNotFoundException(GeneralUtility::makeInstance(LocalizationService::class)->translate('aiSuite.error.site.notFound', [$languageId, $pageUid]), 1521716622);
        } catch (\Exception $e) {
            throw new SiteNotFoundException(GeneralUtility::makeInstance(LocalizationService::class)->translate('aiSuite.error.site.notFound', [$languageId, $pageUid]), 1521716622);
        }
    }

    public function getDomainByRootPageId(int $rootPageId): string
    {
        try {
            $site = $this->siteFinder->getSiteByRootPageId($rootPageId);

            return $site->getBase()->getHost();
        } catch (\Exception $e) {
            return 'Unknown Domain (ID: '.$rootPageId.')';
        }
    }

    /**
     * @param array<string, mixed> $availableLanguages
     */
    public function updateSelectedSysLanguage(array &$availableLanguages, string &$sysLanguageToUse, string &$notification, string $currentSysLanguage, string $fieldName = 'sysLanguage'): void
    {
        $currentLanguageData = explode('__', $currentSysLanguage);
        $sysLanguageToUse = '';
        $notification = '';

        if (isset($availableLanguages[$currentSysLanguage])) {
            $sysLanguageToUse = $currentSysLanguage;
        } else {
            foreach ($availableLanguages as $key => $language) {
                $languageData = explode('__', $key);

                if ($languageData[0] === $currentLanguageData[0]) {
                    $sysLanguageToUse = $key;
                    $notification = GeneralUtility::makeInstance(LocalizationService::class)->translate('aiSuite.notification.'.$fieldName.'.selectAvailableLanguageOfPageTree');

                    break;
                }

                if (0 === (int) $languageData[1]) {
                    $sysLanguageToUse = $key;
                    $notification = GeneralUtility::makeInstance(LocalizationService::class)->translate('aiSuite.notification.'.$fieldName.'.selectDefaultLanguageOfPageTree');
                }
            }
        }

        if ($sysLanguageToUse && isset($availableLanguages[$sysLanguageToUse])) {
            $selectedLanguage = [$sysLanguageToUse => $availableLanguages[$sysLanguageToUse]];
            unset($availableLanguages[$sysLanguageToUse]);
            $availableLanguages = $selectedLanguage + $availableLanguages;
        }
    }

    public function buildAbsoluteUri(UriInterface $uri, ?ServerRequestInterface $request = null): string
    {
        $port = $uri->getPort() ? ':'.$uri->getPort() : '';
        $absoluteUri = $uri->getScheme().'://'.$uri->getHost().$port.$uri->getPath();
        if ('' === $uri->getScheme() || '' === $uri->getHost()) {
            $request ??= $GLOBALS['TYPO3_REQUEST'];
            $uri = $uri->withScheme($request->getUri()->getScheme());
            $uri = $uri->withHost($request->getUri()->getHost());
            $uri = $uri->withPort($request->getUri()->getPort());
            $port = $uri->getPort() ? ':'.$uri->getPort() : '';
            $absoluteUri = $uri->getScheme().'://'.$uri->getHost().$port.$uri->getPath();
        }
        if ('' !== $uri->getQuery()) {
            return $absoluteUri.'?'.$uri->getQuery();
        }

        return $absoluteUri;
    }

    protected function applyLanguageMapping(Site $site, string $isoCode): string
    {
        $siteConfiguration = $site->getConfiguration();
        $languageMapping = $siteConfiguration['aiSuite']['locales'] ?? [];
        if (is_array($languageMapping) && isset($languageMapping[$isoCode])) {
            return (string) $languageMapping[$isoCode];
        }

        return $isoCode;
    }

    /**
     * @param array<int, array<string, mixed>> $allSystemLanguages
     * @param array<int, SiteLanguage>         $languagesOfSpecificSite
     *
     * @return array<int, array<string, mixed>>
     */
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
}
