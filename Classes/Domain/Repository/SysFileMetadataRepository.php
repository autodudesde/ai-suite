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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysFileMetadataRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'sys_file_metadata',
        string $sortBy = 'title'
    ) {
        parent::__construct(
            $connectionPool,
            $table,
            $sortBy
        );
    }

    /**
     * @throws Exception
     */
    public function findByLangUidAndFileIdList(array $uids, string $indexColumn = 'uid', int $langUid = 0): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('file', $uids),
                $queryBuilder->expr()->eq('sys_language_uid', $langUid)
            );
        $metadataList = $queryBuilder->executeQuery()->fetchAllAssociative();
        // set the file uid or what is needed as key
        return array_column($metadataList, null, $indexColumn);
    }

    public function findByUidList(array $uids, string $indexColumn = 'uid'): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('uid', $uids)
            );
        $metadataList = $queryBuilder->executeQuery()->fetchAllAssociative();
        // set the file uid or what is needed as key
        return array_column($metadataList, null, $indexColumn);
    }
}
