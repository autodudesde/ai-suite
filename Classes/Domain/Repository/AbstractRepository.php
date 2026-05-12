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

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractRepository
{
    public function __construct(
        protected readonly ConnectionPool $connectionPool,
        protected readonly string $table = '',
        protected readonly string $sortBy = '',
    ) {}

    public function updateQuery(string $whereColumn, string $whereValue, string $updateColumn, string $updateValue): void
    {
        $this->connectionPool->getConnectionForTable($this->table)
            ->update(
                $this->table,
                [$updateColumn => $updateValue],
                [$whereColumn => $whereValue]
            )
        ;
    }

    /**
     * @param int $uid the unique id
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function findByUid(int $uid): array
    {
        return $this->selectQuery('uid', $uid);
    }

    /**
     * Adds a WorkspaceRestriction for the current Context workspace aspect to the
     * QueryBuilder. Repositories shared between MCP-Sub and the host extension call
     * this so workspace overlay is consistent across both call sites.
     *
     * Reads the Context aspect (not BE_USER->workspace) because MCP's writeMode=live
     * sets the aspect to 0 even when the user's default workspace is non-zero — the
     * aspect is the source of truth for read-side workspace selection.
     */
    protected function addWorkspaceRestriction(QueryBuilder $queryBuilder): void
    {
        $workspaceId = 0;

        try {
            $workspaceId = (int) GeneralUtility::makeInstance(Context::class)
                ->getPropertyFromAspect('workspace', 'id', 0)
            ;
        } catch (\Throwable) {
            // No workspace aspect set — fall back to live (0).
        }
        $queryBuilder->getRestrictions()->add(
            GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function selectQuery(string $column, int $value): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    $column,
                    $queryBuilder->createNamedParameter(
                        $value
                    )
                )
            )
            ->orderBy($this->sortBy, 'ASC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }
}
