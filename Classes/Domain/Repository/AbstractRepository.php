<?php

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractRepository extends Repository
{
    protected ConnectionPool $connectionPool;
    protected string $table = '';
    protected string $sortBy = '';

    public function __construct(
        ConnectionPool $connectionPool,
        string $table = '',
        string $sortBy = ''
    ) {
        parent::__construct();
        $this->connectionPool = $connectionPool;
        $this->table = $table;
        $this->sortBy = $sortBy;
    }

    /**
     * @throws Exception
     */
    protected function selectQuery(string $column, string $value): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

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
            ->fetchAllAssociative();
    }

    public function updateQuery(string $whereColumn, string $whereValue, string $updateColumn, string $updateValue): void
    {
        $this->connectionPool->getConnectionForTable($this->table)
            ->update(
                $this->table,
                [$updateColumn => $updateValue],
                [$whereColumn => $whereValue]
            );
    }

    public function persistAll(): void
    {
        $this->persistenceManager->persistAll();
    }
}
