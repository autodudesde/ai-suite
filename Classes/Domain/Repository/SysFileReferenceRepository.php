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
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysFileReferenceRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        WorkspaceContextService $workspaceContextService,
        string $table = 'sys_file_reference',
        string $sortBy = 'title'
    ) {
        parent::__construct(
            $connectionPool,
            $workspaceContextService,
            $table,
            $sortBy
        );
    }

    /**
     * @param list<int> $pagesUids
     *
     * @return list<array<string, mixed>>
     */
    public function fetchSysFileReferences(array $pagesUids, string $column, int $sysLanguageUid, bool $showOnlyEmpty): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->select('sfr.uid', 'sfr.pid', 'sfr.tablenames', 'sfr.fieldname', 'sfr.uid_local', 'sfr.uid_foreign', 'sfr.'.$column.' AS columnValue', 'sf.name AS fileName', 'sf.mime_type AS fileMimeType', 'sf.size AS size')
            ->from('sys_file_reference', 'sfr')
            ->leftJoin(
                'sfr',
                'sys_file',
                'sf',
                $queryBuilder->expr()->eq('sf.uid', $queryBuilder->quoteIdentifier('sfr.uid_local'))
            )
            ->where(
                $queryBuilder->expr()->eq('sf.type', 2),
                $queryBuilder->expr()->in('sfr.pid', $pagesUids),
                $queryBuilder->expr()->eq('sfr.sys_language_uid', $queryBuilder->createNamedParameter($sysLanguageUid)),
                $queryBuilder->expr()->eq('sf.missing', 0),
            )
        ;
        if (true === $showOnlyEmpty) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('sfr.'.$column),
                    $queryBuilder->expr()->eq('sfr.'.$column, $queryBuilder->createNamedParameter('', Connection::PARAM_STR))
                )
            );
        }

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative()
        ;
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
}
