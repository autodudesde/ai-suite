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
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'tt_content',
        string $sortBy = 'sorting',
    ) {
        parent::__construct($connectionPool, $table, $sortBy);
    }

    /**
     * Find content elements on a page with language filter.
     *
     * @return list<array<string, mixed>>
     */
    public function findByPage(int $pageId, int $languageUid, bool $includeHidden = false, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        if (!$includeHidden) {
            $qb->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        }

        return $qb
            ->select('uid', 'pid', 'colPos', 'CType', 'header', 'bodytext', 'hidden', 'sorting', 'image', 'assets', 'media', 'sys_language_uid')
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

    /**
     * Count content elements on a page with language filter.
     */
    public function countByPage(int $pageId, int $languageUid, bool $includeHidden = false): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        if (!$includeHidden) {
            $qb->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        }

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

    /**
     * Count file references for a content element.
     */
    public function countFileReferences(int $contentUid): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return (int) $qb
            ->count('uid')
            ->from('sys_file_reference')
            ->where(
                $qb->expr()->eq('uid_foreign', $qb->createNamedParameter($contentUid, Connection::PARAM_INT)),
                $qb->expr()->eq('tablenames', $qb->createNamedParameter('tt_content')),
            )
            ->executeQuery()
            ->fetchOne()
        ;
    }

    /**
     * Full-text search across content element fields.
     *
     * @return list<array<string, mixed>>
     */
    public function searchByText(string $query, int $maxResults = 100): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($this->table);
        $qb->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class))
        ;
        $searchTerm = '%'.$qb->escapeLikeWildcards($query).'%';

        return $qb->select('uid', 'pid', 'header', 'bodytext', 'CType')
            ->from($this->table)
            ->where($qb->expr()->or(
                $qb->expr()->like('header', $qb->createNamedParameter($searchTerm)),
                $qb->expr()->like('bodytext', $qb->createNamedParameter($searchTerm)),
            ))
            ->setMaxResults($maxResults)
            ->executeQuery()->fetchAllAssociative()
        ;
    }

    /**
     * Find visible content element UIDs by page IDs or specific UIDs.
     *
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
     * Find stale records in any TCA table that have not been modified since the cutoff timestamp.
     *
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
