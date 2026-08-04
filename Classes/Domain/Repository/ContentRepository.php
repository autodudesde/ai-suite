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

use AutoDudes\AiSuite\Service\WorkspaceContextService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        WorkspaceContextService $workspaceContextService,
        string $table = 'tt_content',
        string $sortBy = 'sorting',
    ) {
        parent::__construct($connectionPool, $workspaceContextService, $table, $sortBy);
    }

    /**
     * @param list<int> $pids
     *
     * @return list<array<string, mixed>>
     */
    public function getAvailableNewsDetailPlugins(array $pids, int $languageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');

        return $queryBuilder->select('tt_content.pid', 'p.title')
            ->from('tt_content')
            ->leftJoin(
                'tt_content',
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('tt_content.pid'))
            )
            ->where(
                $queryBuilder->expr()->in('tt_content.pid', $pids),
                $queryBuilder->expr()->eq('tt_content.sys_language_uid', $languageId),
                $queryBuilder->expr()->eq('tt_content.CType', $queryBuilder->createNamedParameter('news_newsdetail')),
                $queryBuilder->expr()->eq('p.deleted', 0)
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByPage(int $pageId, int $languageUid, bool $includeHidden = false, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        if (!$includeHidden) {
            $qb->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        }
        $this->addWorkspaceRestriction($qb);

        return $qb
            ->select('uid', 'pid', 'colPos', 'CType', 'header', 'bodytext', 'hidden', 'sorting', 'image', 'assets', 'media', 'sys_language_uid', 't3ver_oid', 't3ver_wsid', 't3ver_state')
            ->from($this->table)
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($pageId, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT)),
            )
            ->orderBy('colPos', 'ASC')
            ->addOrderBy('sorting', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    public function countByPage(int $pageId, int $languageUid, bool $includeHidden = false): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        if (!$includeHidden) {
            $qb->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        }
        $this->addWorkspaceRestriction($qb);

        return (int) $qb
            ->count('uid')
            ->from($this->table)
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($pageId, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne()
        ;
    }

    public function hasFreeModeTranslation(int $sourceUid, int $languageUid): bool
    {
        if ($languageUid <= 0 || $sourceUid <= 0) {
            return false;
        }

        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $count = (int) $qb
            ->count('uid')
            ->from($this->table)
            ->where(
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('l18n_parent', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('l10n_source', $qb->createNamedParameter($sourceUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne()
        ;

        return $count > 0;
    }

    /**
     * @return list<int>
     */
    public function getReferencedFileUids(int $contentUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $uids = $qb
            ->select('uid_local')
            ->from('sys_file_reference')
            ->where(
                $qb->expr()->eq('uid_foreign', $qb->createNamedParameter($contentUid, Connection::PARAM_INT)),
                $qb->expr()->eq('tablenames', $qb->createNamedParameter('tt_content')),
            )
            ->orderBy('sorting_foreign', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn()
        ;

        return array_values(array_unique(array_map('intval', $uids)));
    }

    /**
     * @param null|list<int>    $restrictToPageIds
     * @param null|list<string> $searchFields
     *
     * @return list<array<string, mixed>>
     */
    public function searchByText(string $query, int $maxResults = 100, ?array $restrictToPageIds = null, ?array $searchFields = null): array
    {
        if (null !== $restrictToPageIds && [] === $restrictToPageIds) {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class))
        ;
        $this->addWorkspaceRestriction($qb);
        $searchTerm = '%'.$qb->escapeLikeWildcards($query).'%';

        $fields = array_values($searchFields ?? []);
        if ([] === $fields) {
            throw new \InvalidArgumentException(
                'searchByText() needs at least one search field. Pass the columns from TCA discovery (TcaCompatibilityService::getSearchableTextFields()).',
                1752220800
            );
        }
        $likes = array_map(
            static fn (string $field): string => (string) $qb->expr()->like($field, $qb->createNamedParameter($searchTerm)),
            $fields,
        );

        $select = array_values(array_unique(array_merge(
            ['uid', 'pid', 'header', 'bodytext', 'CType', 't3ver_oid', 't3ver_wsid', 't3ver_state'],
            $fields,
        )));

        $qb->select(...$select)
            ->from($this->table)
            ->where($qb->expr()->or(...$likes))
            ->setMaxResults($maxResults)
        ;

        if (null !== $restrictToPageIds) {
            $qb->andWhere(
                $qb->expr()->in('pid', $qb->createNamedParameter($restrictToPageIds, Connection::PARAM_INT_ARRAY)),
            );
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param list<int> $pageIds
     * @param list<int> $contentUids
     *
     * @return list<int>
     */
    public function findUidsByPagesOrUids(array $pageIds = [], array $contentUids = []): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $qb->select('uid')
            ->from($this->table)
            ->where($qb->expr()->eq('hidden', 0))
            ->orderBy('pid', 'ASC')
            ->addOrderBy('colPos', 'ASC')
            ->addOrderBy('sorting', 'ASC')
        ;

        if (!empty($contentUids)) {
            $qb->andWhere($qb->expr()->in('uid', $qb->createNamedParameter(
                array_map('intval', $contentUids),
                Connection::PARAM_INT_ARRAY,
            )));
        } elseif (!empty($pageIds)) {
            $qb->andWhere($qb->expr()->in('pid', $qb->createNamedParameter(
                array_map('intval', $pageIds),
                Connection::PARAM_INT_ARRAY,
            )));
        }

        return array_map('intval', $qb->executeQuery()->fetchFirstColumn());
    }

    /**
     * @param list<string> $containerCTypes CTypes registered as containers (from B13\Container\Tca\Registry::getRegisteredCTypes())
     *
     * @return list<array<string, mixed>>
     */
    public function findContainersOnPage(int $pageId, int $languageUid, array $containerCTypes): array
    {
        if ([] === $containerCTypes || $pageId <= 0) {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $this->addWorkspaceRestriction($qb);

        return $qb
            ->select('uid', 'header', 'CType', 'colPos', 'tx_container_parent')
            ->from($this->table)
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($pageId, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT)),
                $qb->expr()->in('CType', $qb->createNamedParameter($containerCTypes, Connection::PARAM_STR_ARRAY)),
            )
            ->orderBy('colPos', 'ASC')
            ->addOrderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findContainerChildren(int $containerUid, int $languageUid): array
    {
        if ($containerUid <= 0) {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $this->addWorkspaceRestriction($qb);

        return $qb
            ->select('uid', 'header', 'CType', 'colPos', 'sorting', 'tx_container_parent')
            ->from($this->table)
            ->where(
                $qb->expr()->eq('tx_container_parent', $qb->createNamedParameter($containerUid, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT)),
            )
            ->orderBy('colPos', 'ASC')
            ->addOrderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findContentForExtraction(int $pageId, int $languageUid, int $workspaceId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId))
        ;

        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('hidden', 0),
            )
            ->orderBy('colPos', 'ASC')
            ->addOrderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @param list<int> $parentUids
     *
     * @return list<array<string, mixed>>
     */
    public function findContainerChildrenByParents(array $parentUids, int $languageUid, int $workspaceId): array
    {
        if ([] === $parentUids) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId))
        ;

        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('tx_container_parent', $queryBuilder->createNamedParameter($parentUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('hidden', 0),
            )
            ->orderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @param null|list<int> $restrictToPageIds Restrict by pid (or uid for pages table)
     *
     * @return list<array<string, mixed>>
     */
    public function findStaleRecords(
        string $table,
        string $tstampField,
        string $labelField,
        int $cutoff,
        ?array $restrictToPageIds,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $this->addWorkspaceRestriction($qb);

        $qb->select('uid', $labelField, $tstampField)
            ->from($table)
            ->where($qb->expr()->lt($tstampField, $qb->createNamedParameter($cutoff, Connection::PARAM_INT)))
        ;

        if ('pages' === $table) {
            $qb->andWhere($qb->expr()->eq('sys_language_uid', 0));
        }

        if (null !== $restrictToPageIds && [] !== $restrictToPageIds) {
            $idField = 'pages' === $table ? 'uid' : 'pid';
            $qb->andWhere($qb->expr()->in($idField, $qb->createNamedParameter($restrictToPageIds, Connection::PARAM_INT_ARRAY)));
        }

        return $qb->orderBy($tstampField, 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }
}
