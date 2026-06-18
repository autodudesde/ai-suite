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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

class DomainResolverService implements SingletonInterface
{
    public function __construct(
        protected readonly SiteFinder $siteFinder,
        protected readonly ConnectionPool $connectionPool,
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

    public function getDomainByTask(string $tableName, int $tableUid): string
    {
        if ('pages' === $tableName) {
            return $this->getDomainByPageId($tableUid);
        }

        if ('sys_file_reference' === $tableName) {
            $pageId = $this->getPageIdFromTable('sys_file_reference', $tableUid);
            if ($pageId > 0) {
                return $this->getDomainByPageId($pageId);
            }
        }

        if ('sys_file_metadata' === $tableName) {
            $pageId = $this->getPageIdFromTable('sys_file_metadata', $tableUid);
            if ($pageId > 0) {
                return $this->getDomainByPageId($pageId);
            }
        }

        return '';
    }

    private function getPageIdFromTable(string $tableName, int $uid): int
    {
        try {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
            $row = $queryBuilder
                ->select('pid')
                ->from($tableName)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative()
            ;

            return (int) ($row['pid'] ?? 0);
        } catch (\Exception $e) {
            $this->logger->warning('Could not resolve page id from table', [
                'tableName' => $tableName,
                'uid' => $uid,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
