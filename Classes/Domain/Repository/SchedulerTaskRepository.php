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

class SchedulerTaskRepository
{
    protected string $table = 'tx_scheduler_task';

    public function __construct(
        protected readonly ConnectionPool $connectionPool,
    ) {}

    public function countActiveSchedulableCommandTasks(string $commandIdentifier): int
    {
        try {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
            $queryBuilder->getRestrictions()->removeAll();

            $constraints = [
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('disable', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            ];

            $legacyMatch = $queryBuilder->expr()->and(
                $queryBuilder->expr()->like(
                    'serialized_task_object',
                    $queryBuilder->createNamedParameter('%ExecuteSchedulableCommandTask%')
                ),
                $queryBuilder->expr()->like(
                    'serialized_task_object',
                    $queryBuilder->createNamedParameter('%'.$commandIdentifier.'%')
                ),
            );

            if ($this->hasTaskTypeColumn()) {
                $constraints[] = $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        'tasktype',
                        $queryBuilder->createNamedParameter($commandIdentifier)
                    ),
                    $legacyMatch,
                );
            } else {
                $constraints[] = $legacyMatch;
            }

            return (int) $queryBuilder
                ->count('uid')
                ->from($this->table)
                ->where(...$constraints)
                ->executeQuery()
                ->fetchOne()
            ;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function hasTaskTypeColumn(): bool
    {
        try {
            $schemaManager = $this->connectionPool
                ->getConnectionForTable($this->table)
                ->createSchemaManager()
            ;

            foreach ($schemaManager->listTableColumns($this->table) as $column) {
                if ('tasktype' === $column->getName()) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // fall through to false
        }

        return false;
    }
}
