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

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractRepository
{
    public function __construct(
        protected readonly ConnectionPool $connectionPool,
        protected readonly string $table = '',
        protected readonly string $sortBy = '',
    ) {}

    public function updateQuery(string $whereColumn, string $whereValue, string $updateColumn, string $updateValue): void
    {
        $this->connectionPool->getConnectionForTable($this->table)
            ->update(
                $this->table,
                [$updateColumn => $updateValue],
                [$whereColumn => $whereValue]
            )
        ;
    }

    /**
     * @param int $uid the unique id
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function findByUid(int $uid): array
    {
        return $this->selectQuery('uid', $uid);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function selectQuery(string $column, int $value): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    $column,
                    $queryBuilder->createNamedParameter(
                        $value
                    )
                )
            )
            ->orderBy($this->sortBy, 'ASC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }
}
