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

use AutoDudes\AiSuite\Domain\Model\Pages;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

class PagesRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'pages',
        string $sortBy = 'sorting'
    ) {
        parent::__construct(
            $connectionPool,
            $table,
            $sortBy
        );
    }

    public function initializeObject(): void
    {
        $defaultQuerySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $defaultQuerySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($defaultQuerySettings);
    }

    /**
     * @throws Exception
     */
    public function findAiStructurePages($sortBy = 'sorting'): array
    {
        $this->sortBy = $sortBy;
        return $this->selectQuery('doktype', 1);
    }

    public function addPage(Pages $page): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->insert($this->table)->values($page->toDatabase());
        $queryBuilder->executeStatement();
        return $queryBuilder->getConnection()->lastInsertId();
    }
}
