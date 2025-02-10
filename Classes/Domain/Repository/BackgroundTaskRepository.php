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

use AutoDudes\AiSuite\Domain\Model\Dto\BackgroundTask;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackgroundTaskRepository
{
    protected ConnectionPool $connectionPool;
    protected string $table = 'tx_aisuite_domain_model_backgroundtask';
    protected string $sortBy = 'timestamp';

    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'tx_aisuite_domain_model_backgroundtask',
        string $sortBy = 'timestamp'
    ) {
        $this->connectionPool = $connectionPool;
        $this->table = $table;
        $this->sortBy = $sortBy;
    }

    /**
     * @throws DBALException|\Doctrine\DBAL\Driver\Exception
     */
    public function findAll(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function findAllPageBackgroundTasks(): array {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder->select('bt.*', 'p.title', 'p.slug', 'p.seo_title', 'p.description', 'p.og_title', 'p.og_description', 'p.twitter_title', 'p.twitter_description')
            ->from($this->table, 'bt')
            ->leftJoin(
                'bt',
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('bt.table_uid'))
            )
            ->where(
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('page')),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('pages'))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function findAllFileReferenceBackgroundTasks(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        return $queryBuilder->select('bt.*', 'sf.name AS fileName', 'sf.uid AS fileUid', 'sf.storage', 'sfr.title', 'sfr.alternative', 'sfr.sys_language_uid', 'tt.pid AS pageId')
            ->from($this->table, 'bt')
            ->leftJoin(
                'bt',
                'sys_file_reference',
                'sfr',
                $queryBuilder->expr()->eq('sfr.uid', $queryBuilder->quoteIdentifier('bt.table_uid'))
            )
            ->leftJoin(
                'sfr',
                'sys_file',
                'sf',
                $queryBuilder->expr()->eq('sf.uid', $queryBuilder->quoteIdentifier('sfr.uid_local'))
            )
            ->leftJoin(
                'sfr',
                'tt_content',
                'tt',
                $queryBuilder->expr()->eq('tt.uid', $queryBuilder->quoteIdentifier('sfr.uid_foreign'))
            )
            ->where(
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('fileReference')),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('sys_file_reference'))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function findAllFileMetadataBackgroundTasks(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        return $queryBuilder->select('bt.*', 'sf.name AS fileName', 'sf.uid AS fileUid', 'sf.storage', 'sfm.title', 'sfm.description', 'sfm.alternative')
            ->from($this->table, 'bt')
            ->leftJoin(
                'bt',
                'sys_file_metadata',
                'sfm',
                $queryBuilder->expr()->eq('sfm.uid', $queryBuilder->quoteIdentifier('bt.table_uid'))
            )
            ->leftJoin(
                'sfm',
                'sys_file',
                'sf',
                $queryBuilder->expr()->eq('sf.uid', $queryBuilder->quoteIdentifier('sfm.file'))
            )
            ->where(
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('fileMetadata')),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('sys_file_metadata'))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function findByUuid(string $uuid): array|bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('uuid', $queryBuilder->createNamedParameter($uuid))
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * @throws DBALException
     */
    public function deleteByUuid(string $uuid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->eq('uuid', $queryBuilder->createNamedParameter($uuid))
            )
            ->executeStatement();
    }

    /**
     * @throws DBALException
     */
    public function updateStatus(array $data): void
    {
       foreach ($data as $uuid => $statusData) {
           $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
           $queryBuilder
               ->update($this->table)
               ->where(
                   $queryBuilder->expr()->eq('uuid', $queryBuilder->createNamedParameter($uuid))
               )
               ->set('status', $statusData['status'])
               ->set('answer', $statusData['answer'])
               ->executeStatement();
       }
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchAlreadyPendingEntries(array $foundUids, string $tableName): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder->select('table_name', 'table_uid', 'status')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('table_uid', $queryBuilder->createNamedParameter($foundUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($tableName))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param BackgroundTask[] $bulkPayloadList
     */
    public function insertBackgroundTasks(array $bulkPayloadList): void
    {
        if (empty($bulkPayloadList)) {
            return;
        }
        $bulkPayload = [];
        foreach ($bulkPayloadList as $bulkPayloadItem) {
            $bulkPayload[] = $bulkPayloadItem->getBulkInsertPayload();
        }
        $this->connectionPool
            ->getConnectionForTable($this->table)
            ->bulkInsert(
                $this->table,
                $bulkPayload,
                BackgroundTask::getDbColumnsForBulkInsert(),
                BackgroundTask::getTypesForBulkInsert()
            );
    }
}
