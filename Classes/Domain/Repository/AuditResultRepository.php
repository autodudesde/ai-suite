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

namespace AutoDudes\AiSuite\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class AuditResultRepository
{
    private const TABLE = 'tx_aisuite_audit_result';

    public function __construct(
        protected readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return null|array{pageUid: int, auditType: string, keyword: string, runTs: int, result: array<string, mixed>}
     */
    public function findLatest(int $pageUid, string $auditType, int $languageUid = 0): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('page_uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('audit_type', $queryBuilder->createNamedParameter($auditType)),
                $queryBuilder->expr()->eq('language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
            )
            ->orderBy('run_ts', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative()
        ;
        if (false === $row) {
            return null;
        }
        $result = json_decode((string) $row['result'], true);

        return [
            'pageUid' => (int) $row['page_uid'],
            'auditType' => (string) $row['audit_type'],
            'keyword' => (string) $row['keyword'],
            'runTs' => (int) $row['run_ts'],
            'result' => is_array($result) ? $result : [],
        ];
    }

    /**
     * @return list<array{pageUid: int, summary: array<string, mixed>}>
     */
    public function findAllSummaries(string $auditType, int $languageUid = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('page_uid', 'result')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('audit_type', $queryBuilder->createNamedParameter($auditType)),
                $queryBuilder->expr()->eq('language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;
        $summaries = [];
        foreach ($rows as $row) {
            $result = json_decode((string) $row['result'], true);
            $summary = $result['audit']['summary'] ?? null;
            if (\is_array($summary)) {
                $summaries[] = ['pageUid' => (int) $row['page_uid'], 'summary' => $summary];
            }
        }

        return $summaries;
    }

    public function deleteOlderThan(int $maxAgeSeconds): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->delete(self::TABLE)
            ->where(
                $queryBuilder->expr()->lt('run_ts', $queryBuilder->createNamedParameter(time() - $maxAgeSeconds, Connection::PARAM_INT)),
            )
            ->executeStatement()
        ;
    }

    /**
     * Upsert: one cached result per page, audit type and language.
     *
     * @param array<string, mixed> $result
     */
    public function store(int $pageUid, string $auditType, string $keyword, array $result, ?int $runTs = null, int $languageUid = 0): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->delete(self::TABLE, ['page_uid' => $pageUid, 'audit_type' => $auditType, 'language_uid' => $languageUid]);
        $connection->insert(self::TABLE, [
            'page_uid' => $pageUid,
            'language_uid' => $languageUid,
            'audit_type' => $auditType,
            'keyword' => $keyword,
            'run_ts' => $runTs ?? time(),
            'result' => json_encode($result),
        ]);
    }
}
