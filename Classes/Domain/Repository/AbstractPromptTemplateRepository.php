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
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractPromptTemplateRepository extends Repository
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

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function findByScopeAndType(string $scope, string $type = ''): array
    {
        $connection = $this->connectionPool->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $data = $queryBuilder
            ->select('name', 'prompt')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter($scope))
            );

        if ($type !== '') {
            $data->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter($type)),
                    $queryBuilder->expr()->like('type', $queryBuilder->createNamedParameter($type .',%')),
                    $queryBuilder->expr()->like('type', $queryBuilder->createNamedParameter('%,' . $type)),
                    $queryBuilder->expr()->like('type', $queryBuilder->createNamedParameter('%,' . $type . ',%'))
                )
            );
        }

        $data = $data->executeQuery()
            ->fetchAllAssociative();
        return $data ?: [];
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function findAll(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $result = $queryBuilder
            ->select('*')
            ->from($this->table)
            ->executeQuery()
            ->fetchAllAssociative();
        return $result ?: [];
    }
}
