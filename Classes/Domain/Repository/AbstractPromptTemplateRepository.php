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
    public function findByScopeAndType(string $scope, string $type = '', int $languageId = 0): array
    {
        $connection = $this->connectionPool->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        if($type !== '') {
            $queryBuilder->select('name', 'prompt')
                ->from($this->table)
                ->where(
                    $queryBuilder->expr()->in('sys_language_uid', [$languageId, -1]),
                    $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter($scope)),
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter($type)),
                        $queryBuilder->expr()->like('type', $queryBuilder->createNamedParameter($type .',%')),
                        $queryBuilder->expr()->like('type', $queryBuilder->createNamedParameter('%,' . $type)),
                        $queryBuilder->expr()->like('type', $queryBuilder->createNamedParameter('%,' . $type . ',%'))
                    )
                )
            ->orWhere(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->in('sys_language_uid', [$languageId, -1]),
                    $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('general')),
                )
            )
            ->orWhere(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->in('sys_language_uid', [$languageId, -1]),
                    $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter($scope)),
                    $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter(''))
                )
            );
        } else {
            $queryBuilder->select('name', 'prompt')
                ->from($this->table)
                ->where(
                    $queryBuilder->expr()->in('sys_language_uid', [$languageId, -1]),
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter($scope)),
                        $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('general'))
                    )
                );
        }
        $queryBuilder->orderBy($this->sortBy, 'DESC');
        $data = $queryBuilder->executeQuery()
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
