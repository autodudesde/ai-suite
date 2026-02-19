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
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackgroundTaskRepository
{
    protected ConnectionPool $connectionPool;
    protected string $table = 'tx_aisuite_domain_model_backgroundtask';
    protected string $sortBy = 'status';

    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'tx_aisuite_domain_model_backgroundtask',
        string $sortBy = 'status'
    ) {
        $this->connectionPool = $connectionPool;
        $this->table = $table;
        $this->sortBy = $sortBy;
    }

    /**
     * @throws Exception
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

    /**
     * @throws Exception
     */
    public function findAllPageBackgroundTasks(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->select(
            'bt.*',
            'p.title',
            'p.slug',
            'p.seo_title',
            'p.description',
            'p.og_title',
            'p.og_description',
            'p.twitter_title',
            'p.twitter_description'
        )
            ->from($this->table, 'bt')
            ->leftJoin(
                'bt',
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('bt.table_uid'))
            )
            ->where(
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('page')),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('p.deleted', 0)
            )
            ->orderBy('p.title', 'ASC')
            ->addOrderBy('bt.' . $this->sortBy, 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function findAllFileReferenceBackgroundTasks(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        return $queryBuilder->select(
            'bt.*',
            'sf.name AS fileName',
            'sf.uid AS fileUid',
            'sf.storage',
            'sfr.title',
            'sfr.alternative',
            'sfr.description',
            'sfr.sys_language_uid',
            'sfr.pid AS pageId'
        )
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
            ->where(
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('fileReference')),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('sys_file_reference')),
                $queryBuilder->expr()->eq('sf.missing', 0),
                $queryBuilder->expr()->eq('sfr.deleted', 0)
            )
            ->orderBy('sfr.title', 'ASC')
            ->addOrderBy('bt.' . $this->sortBy, 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function findAllFileMetadataBackgroundTasks(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        return $queryBuilder->select(
            'bt.*',
            'sf.name AS fileName',
            'sf.uid AS fileUid',
            'sf.storage',
            'sfm.title',
            'sfm.description',
            'sfm.alternative'
        )
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
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('sys_file_metadata')),
                $queryBuilder->expr()->eq('sf.missing', 0),
                $queryBuilder->expr()->gt('sfm.file', 0)
            )
            ->orderBy('sf.name', 'ASC')
            ->addOrderBy('bt.' . $this->sortBy, 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function findByUuid(string $uuid): array|bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->select('bt.*')
            ->from($this->table, 'bt')
            ->where(
                $queryBuilder->expr()->eq('uuid', $queryBuilder->createNamedParameter($uuid))
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    public function findFileUid(int $uidLocal, string $uuid, string $type, string $scope): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->select('bt.*', 'sfm.file AS fileUid')
            ->from($this->table, 'bt')
            ->leftJoin(
                'bt',
                'sys_file_metadata',
                'sfm',
                $queryBuilder->expr()->eq('sfm.uid', $queryBuilder->createNamedParameter($uidLocal))
            )
            ->where(
                $queryBuilder->expr()->eq('bt.uuid', $queryBuilder->createNamedParameter($uuid)),
                $queryBuilder->expr()->eq('bt.type', $queryBuilder->createNamedParameter($type)),
                $queryBuilder->expr()->eq('bt.scope', $queryBuilder->createNamedParameter($scope)),
                $queryBuilder->expr()->eq('bt.table_name', $queryBuilder->createNamedParameter('sys_file_metadata')),
                $queryBuilder->expr()->gt('sfm.file', 0)
            )
            ->executeQuery()
            ->fetchAssociative();
    }

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

    public function updateStatus(array $data): void
    {
        foreach ($data as $uuid => $statusData) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
            $error = isset($statusData['error']) ? $statusData['error'] : '';
            $queryBuilder
                ->update($this->table)
                ->where(
                    $queryBuilder->expr()->eq('uuid', $queryBuilder->createNamedParameter($uuid))
                )
                ->set('status', $statusData['status'])
                ->set('answer', $statusData['answer'])
                ->set('error', $error)
                ->executeStatement();
        }
    }

    /**
     * @throws Exception
     */
    public function fetchAlreadyPendingEntries(array $foundUids, string $tableName, string $column, string $mode = '', string $type = '', int $sysLanguageUid = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $constraints = [
            $queryBuilder->expr()->in('table_uid', $queryBuilder->createNamedParameter($foundUids, Connection::PARAM_INT_ARRAY)),
            $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($tableName)),
            $queryBuilder->expr()->eq('mode', $queryBuilder->createNamedParameter($mode))
        ];
        if (!empty($type)) {
            $constraints[] = $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter($type));
        }
        if($sysLanguageUid > 0) {
            $constraints[] = $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($sysLanguageUid));
        }
        if ($column === 'all') {
            $constraints[] = $queryBuilder->expr()->or(
                $queryBuilder->expr()->eq('column', $queryBuilder->createNamedParameter('title')),
                $queryBuilder->expr()->eq('column', $queryBuilder->createNamedParameter('alternative')),
            );
        } else {
            $constraints[] = $queryBuilder->expr()->eq('column', $queryBuilder->createNamedParameter($column));
        }

        return $queryBuilder->select('table_name', 'table_uid', 'mode', 'status')
            ->from($this->table)
            ->where(
                ...$constraints
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

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

    public function deleteByUuids(array $uuids): int
    {
        if (empty($uuids)) {
            return 0;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->in('uuid', $queryBuilder->createNamedParameter($uuids, Connection::PARAM_STR_ARRAY))
            )
            ->executeStatement();
    }

    public function countBackgroundTasksByStatusAndScope(): array
    {
        $results = [];

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $pageResults = $queryBuilder
            ->addSelectLiteral('COUNT(*) AS ' . $queryBuilder->quoteIdentifier('count'))
            ->addSelect('bt.scope', 'bt.status', 'bt.column', 'bt.table_uid', 'bt.table_name')
            ->from($this->table, 'bt')
            ->leftJoin(
                'bt',
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('bt.table_uid'))
            )
            ->where(
                $queryBuilder->expr()->eq('bt.scope', $queryBuilder->createNamedParameter('page')),
                $queryBuilder->expr()->eq('p.deleted', 0)
            )
            ->groupBy('bt.scope', 'bt.status', 'bt.column')
            ->executeQuery()
            ->fetchAllAssociative();

        $results = array_merge($results, $pageResults);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $fileRefResults = $queryBuilder
            ->addSelectLiteral('COUNT(*) AS ' . $queryBuilder->quoteIdentifier('count'))
            ->addSelect('bt.scope', 'bt.status', 'bt.column', 'bt.table_uid', 'bt.table_name')
            ->from($this->table, 'bt')
            ->leftJoin(
                'bt',
                'sys_file_reference',
                'sfr',
                $queryBuilder->expr()->eq('sfr.uid', $queryBuilder->quoteIdentifier('bt.table_uid'))
            )
            ->where(
                $queryBuilder->expr()->eq('bt.scope', $queryBuilder->createNamedParameter('fileReference')),
                $queryBuilder->expr()->eq('sfr.deleted', 0)
            )
            ->groupBy('bt.scope', 'bt.status', 'bt.column')
            ->executeQuery()
            ->fetchAllAssociative();

        $results = array_merge($results, $fileRefResults);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $fileMetaResults = $queryBuilder
            ->addSelectLiteral('COUNT(*) AS ' . $queryBuilder->quoteIdentifier('count'))
            ->addSelect('bt.scope', 'bt.status', 'bt.column', 'bt.table_uid', 'bt.table_name')
            ->from($this->table, 'bt')
            ->leftJoin(
                'bt',
                'sys_file_metadata',
                'sfm',
                $queryBuilder->expr()->eq('sfm.uid', $queryBuilder->quoteIdentifier('bt.table_uid'))
            )
            ->where(
                $queryBuilder->expr()->eq('bt.scope', $queryBuilder->createNamedParameter('fileMetadata')),
                $queryBuilder->expr()->gt('sfm.file', 0)
            )
            ->groupBy('bt.scope', 'bt.status', 'bt.column')
            ->executeQuery()
            ->fetchAllAssociative();

        $results = array_merge($results, $fileMetaResults);
        return $results;
    }

    /**
     * @throws Exception
     */
    public function fetchAlreadyPendingEntriesForTranslation(array $foundUids, string $tableName, int $targetLanguageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder->select('table_name', 'table_uid', 'status', 'sys_language_uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('table_uid', $queryBuilder->createNamedParameter($foundUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($tableName)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($targetLanguageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('page-translation'))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function findAllPageTranslationBackgroundTasks(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->select('bt.*', 'p.title', 'p.slug')
            ->from($this->table, 'bt')
            ->leftJoin(
                'bt',
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('bt.table_uid'))
            )
            ->where(
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('page-translation')),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('p.deleted', 0)
            )
            ->orderBy('p.title', 'ASC')
            ->addOrderBy('bt.' . $this->sortBy, 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function findTranslationTasksForPage(int $pageUid, string $status): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('page-translation')),
                $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter('translation')),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('table_uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter($status))
            )
            ->orderBy('crdate', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function findAllFileMetadataTranslationBackgroundTasks(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        return $queryBuilder->select(
            'bt.*',
            'sf.name AS fileName',
            'sf.uid AS fileUid',
            'sf.storage',
            'sfm.title',
            'sfm.description',
            'sfm.alternative'
        )
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
                $queryBuilder->expr()->eq('bt.scope', $queryBuilder->createNamedParameter('metadata')),
                $queryBuilder->expr()->eq('bt.type', $queryBuilder->createNamedParameter('translation')),
                $queryBuilder->expr()->eq('bt.table_name', $queryBuilder->createNamedParameter('sys_file_metadata')),
                $queryBuilder->expr()->eq('sf.missing', 0),
                $queryBuilder->expr()->gt('sfm.file', 0)
            )
            ->orderBy('sf.name', 'ASC')
            ->addOrderBy('bt.' . $this->sortBy, 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function findSourceColumnValueByFileUidAndDefaultLanguage(int $fileUid): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable('sys_file_metadata')->createQueryBuilder();
        return $queryBuilder->select('title', 'alternative', 'description')
            ->from('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->eq('file', $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative() ?: [];
    }
}
