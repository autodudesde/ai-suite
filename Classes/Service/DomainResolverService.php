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

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

class DomainResolverService implements SingletonInterface
{
    public function __construct(
        protected readonly SiteFinder $siteFinder,
        protected readonly LoggerInterface $logger,
    ) {}

    public function getDomainByPageId(int $pageId): string
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);

            return $site->getBase()->getHost();
        } catch (\Exception $e) {
            $this->logger->warning('Could not resolve domain by page id', [
                'pageId' => $pageId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    public function getDomainBySiteIdentifier(string $siteIdentifier): string
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);

            return $site->getBase()->getHost();
        } catch (\Exception $e) {
            $this->logger->warning('Could not resolve domain by site identifier', [
                'siteIdentifier' => $siteIdentifier,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
