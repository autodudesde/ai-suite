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
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class ProvenanceRepository
{
    public const TABLE = 'tx_aisuite_domain_model_provenance';

    public function __construct(
        protected readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public function store(array $row): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);

        $connection->transactional(static function () use ($connection, $row): void {
            $connection->delete(self::TABLE, [
                'tablename' => $row['tablename'],
                'record_uid' => $row['record_uid'],
            ]);
            $connection->insert(self::TABLE, $row);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findForRecord(string $table, int $uid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('record_uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('record_uid', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        return array_values($rows);
    }

    /**
     * @param list<int> $uids
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function findForRecords(string $table, array $uids): array
    {
        if ([] === $uids) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->in(
                    'record_uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY),
                ),
            )
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('record_uid', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['record_uid']][] = $row;
        }

        return $grouped;
    }

    /**
     * @return list<int>
     */
    public function fetchUidsOnPage(string $table, int $pageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $uids = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchFirstColumn()
        ;

        return array_map(intval(...), $uids);
    }

    /**
     * @param list<int>    $uids
     * @param list<string> $columns
     *
     * @return list<array<string, mixed>>
     */
    public function fetchColumns(string $table, array $uids, array $columns): array
    {
        if ([] === $uids || [] === $columns) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $rows = $queryBuilder
            ->select(...$columns)
            ->from($table)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY),
                ),
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        return array_values($rows);
    }

    /**
     * @param list<int> $uids
     *
     * @return array<int, array{table: string, uid: int}>
     */
    public function fetchFileUsages(array $uids): array
    {
        if ([] === $uids) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();

        $rows = $queryBuilder
            ->select('uid_local', 'tablenames', 'uid_foreign')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->in(
                    'uid_local',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY),
                ),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        $usages = [];
        foreach ($rows as $row) {
            $fileUid = (int) $row['uid_local'];
            $table = (string) $row['tablenames'];
            $uid = (int) $row['uid_foreign'];
            if (isset($usages[$fileUid]) || '' === $table || $uid <= 0) {
                continue;
            }

            $usages[$fileUid] = ['table' => $table, 'uid' => $uid];
        }

        return $usages;
    }

    public function fetchFileFolder(int $uid): ?string
    {
        $rows = $this->fetchColumns('sys_file', [$uid], ['storage', 'identifier']);
        if ([] === $rows) {
            return null;
        }

        return $rows[0]['storage'].':'.rtrim((string) dirname((string) $rows[0]['identifier']), '/').'/';
    }

    /**
     * @return list<int>
     */
    public function findVersionUids(string $table, int $liveUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $uids = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('t3ver_oid', $queryBuilder->createNamedParameter($liveUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($liveUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchFirstColumn()
        ;

        return array_map(intval(...), $uids);
    }

    public function isDeleted(string $table, int $uid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $deleted = $queryBuilder
            ->select('deleted')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne()
        ;

        return false === $deleted || (bool) $deleted;
    }

    public function moveToRecord(string $table, int $fromUid, int $toUid): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->delete(self::TABLE, ['tablename' => $table, 'record_uid' => $toUid]);
        $connection->update(
            self::TABLE,
            ['record_uid' => $toUid, 'workspace' => 0],
            ['tablename' => $table, 'record_uid' => $fromUid],
        );
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, mixed>
     */
    public function fetchFieldValues(string $table, int $uid, array $fields): array
    {
        if ([] === $fields) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $row = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative()
        ;

        return false === $row ? [] : $row;
    }

    public function markEdited(string $table, int $uid, int $beUserUid, int $editedAt): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            ['edited_by' => $beUserUid, 'edited_at' => $editedAt],
            ['tablename' => $table, 'record_uid' => $uid],
        );
    }

    public function updateReview(string $table, int $uid, int $beUserUid, int $reviewedAt): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            ['reviewed_by' => $beUserUid, 'reviewed_at' => $reviewedAt],
            ['tablename' => $table, 'record_uid' => $uid],
        );
    }

    /**
     * @param array<string, string> $filters
     *
     * @return list<array<string, mixed>>
     */
    public function findForOverview(array $filters, int $limit, int $offset): array
    {
        $queryBuilder = $this->overviewQuery($filters);

        return $queryBuilder
            ->select('*')
            ->orderBy('crdate', 'DESC')
            // record_uid is the tiebreaker for same-second rows; this table has no key of its own.
            ->addOrderBy('record_uid', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, int>
     */
    public function countByTable(array $filters): array
    {
        $rows = $this->overviewQuery($filters)
            ->select('tablename')
            ->addSelectLiteral('COUNT(record_uid) AS amount')
            ->groupBy('tablename')
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['tablename']] = (int) $row['amount'];
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countForOverview(array $filters): int
    {
        return (int) $this->overviewQuery($filters)
            ->count('record_uid')
            ->executeQuery()
            ->fetchOne()
        ;
    }

    public function deleteForRecord(string $table, int $uid): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)
            ->delete(self::TABLE, ['tablename' => $table, 'record_uid' => $uid])
        ;
    }

    public function fetchWorkspaceId(string $table, int $uid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $value = $queryBuilder
            ->select('t3ver_wsid')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne()
        ;

        return false === $value ? 0 : (int) $value;
    }

    /**
     * @return list<int>
     */
    public function fetchRecordUids(string $table): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $uids = $queryBuilder
            ->select('record_uid')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)))
            ->groupBy('record_uid')
            ->executeQuery()
            ->fetchFirstColumn()
        ;

        return array_values(array_map(intval(...), $uids));
    }

    /**
     * @return list<int>
     */
    public function fetchStructuralRecordUids(string $table): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $uids = $queryBuilder
            ->select('record_uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('source_fields', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('source_fields'),
                ),
            )
            ->groupBy('record_uid')
            ->executeQuery()
            ->fetchFirstColumn()
        ;

        return array_values(array_map(intval(...), $uids));
    }

    /**
     * @return list<string>
     */
    public function fetchDistinctTables(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $tables = $queryBuilder
            ->select('tablename')
            ->from(self::TABLE)
            ->groupBy('tablename')
            ->executeQuery()
            ->fetchFirstColumn()
        ;

        return array_values(array_map(strval(...), $tables));
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function overviewQuery(array $filters): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->from(self::TABLE);

        $excluded = array_values(array_filter(array_map(strval(...), (array) ($filters['excludeTables'] ?? []))));
        if ([] !== $excluded) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->notIn(
                    'tablename',
                    $queryBuilder->createNamedParameter($excluded, Connection::PARAM_STR_ARRAY),
                ),
            );
        }

        foreach (['mode', 'feature', 'tablename'] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));
            if ('' !== $value) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq($column, $queryBuilder->createNamedParameter($value)),
                );
            }
        }

        $queryBuilder->andWhere(
            $queryBuilder->expr()->eq('workspace', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
        );

        if (array_key_exists('includeTables', $filters)) {
            $included = array_values(array_filter(array_map(strval(...), (array) $filters['includeTables'])));
            // Empty list must select nothing: expr()->in() throws on an empty list.
            $queryBuilder->andWhere(
                [] === $included
                    ? $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter(''))
                    : $queryBuilder->expr()->in(
                        'tablename',
                        $queryBuilder->createNamedParameter($included, Connection::PARAM_STR_ARRAY),
                    ),
            );
        }

        $reviewed = trim((string) ($filters['reviewed'] ?? ''));
        if ('yes' === $reviewed) {
            $queryBuilder->andWhere($queryBuilder->expr()->gt('reviewed_at', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)));
        } elseif ('no' === $reviewed) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('reviewed_at', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)));
        }

        return $queryBuilder;
    }
}
