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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SiteService implements SingletonInterface
{
    protected Context $context;
    protected SiteFinder $siteFinder;
    protected BasicAuthService $basicAuthService;

    public function __construct(SiteFinder $siteFinder = null, Context $context = null)
    {
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->context = $context ?? GeneralUtility::makeInstance(Context::class);
        $this->basicAuthService = GeneralUtility::makeInstance(BasicAuthService::class);
    }

    public function getAvailableLanguages(): array
    {
        $availableLanguages = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getLanguages() as $language) {
                $availableLanguages[$language->getLocale()->getLanguageCode()] = $language->getTitle();
            }
        }
        return $availableLanguages;
    }

    public function getAvailableLanguageIds(): array
    {
        $availableLanguages = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getLanguages() as $language) {
                $availableLanguages[$language->getLanguageId()] = $language->getTitle();
            }
        }
        return $availableLanguages;
    }

    public function getAvailableDefaultLanguages(): array
    {
        $availableDefaultLanguages = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getLanguages() as $language) {
                if ($language->getTypo3Language() === 'default') {
                    $availableDefaultLanguages[$language->getLocale()->getLanguageCode()] = $language->getTitle();
                }
            }
        }
        return $availableDefaultLanguages;
    }

    /**
     * @throws SiteNotFoundException
     */
    public function getIsoCodeByLanguageId(int $languageId): string
    {
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getLanguages() as $language) {
                if ($language->getLanguageId() === $languageId) {
                    return $language->getLocale()->getLanguageCode();
                }
            }
        }
        throw new SiteNotFoundException('No site found for language id ' . $languageId);
    }

    public function getAvailableRootPages(): array
    {
        $availableRootPages = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            $availableRootPages[] = $site->getRootPageId();
        }
        return $availableRootPages;
    }

    /**
     * @throws SiteNotFoundException
     */
    public function getLangIsoCode(int $pageId): string {
        $languageId = self::getLanguageId();
        $site = $this->siteFinder->getSiteByPageId($pageId);
        $language = $site->getLanguageById($languageId);
        return $language->getLocale()->getLanguageCode() ?? 'en';
    }

    public function getLanguageId() {
        return $this->context->getPropertyFromAspect('language', 'id');
    }

    public function getSiteByPageId(int $pageId): ?Site {
        return $this->siteFinder->getSiteByPageId($pageId);
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
