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
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysFileReferenceRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'sys_file_reference',
        string $sortBy = 'title'
    ) {
        parent::__construct(
            $connectionPool,
            $table,
            $sortBy
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByFileOrPage(?int $fileUid, ?int $pageId, int $limit = 200): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        $queryBuilder->select(
            'r.uid',
            'r.uid_local',
            'r.uid_foreign',
            'r.pid',
            'r.tablenames',
            'r.fieldname',
            'r.title',
            'r.alternative',
            'r.description',
            'f.name AS file_name',
            'f.extension',
            'f.identifier'
        )
            ->from($this->table, 'r')
            ->join('r', 'sys_file', 'f', $queryBuilder->expr()->eq('f.uid', $queryBuilder->quoteIdentifier('r.uid_local')))
        ;

        if (null !== $fileUid) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('r.uid_local', $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)));
        }
        if (null !== $pageId) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('r.pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)));
        }

        return $queryBuilder->orderBy('r.tablenames')->addOrderBy('r.sorting_foreign')
            ->setMaxResults($limit)
            ->executeQuery()->fetchAllAssociative()
        ;
    }

    /**
     * @param list<int> $pageIds
     *
     * @return list<array<string, mixed>>
     */
    public function findImagesWithMetadata(array $pageIds, int $workspaceId): array
    {
        if ([] === $pageIds) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId))
        ;

        $metadataJoinCondition = (string) $queryBuilder->expr()->and(
            $queryBuilder->expr()->eq('m.file', $queryBuilder->quoteIdentifier('r.uid_local')),
            $queryBuilder->expr()->eq('m.sys_language_uid', 0),
        );

        return $queryBuilder
            ->select('r.uid_local', 'r.pid', 'm.title', 'm.alternative', 'm.description', 'f.name')
            ->from($this->table, 'r')
            ->join('r', 'sys_file', 'f', $queryBuilder->expr()->eq('f.uid', $queryBuilder->quoteIdentifier('r.uid_local')))
            ->leftJoin('r', 'sys_file_metadata', 'm', $metadataJoinCondition)
            ->where(
                $queryBuilder->expr()->in('r.pid', $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->like('f.mime_type', $queryBuilder->createNamedParameter('image/%')),
            )
            ->executeQuery()->fetchAllAssociative()
        ;
    }
}
