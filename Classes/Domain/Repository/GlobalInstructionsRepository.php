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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GlobalInstructionsRepository
{
    protected ConnectionPool $connectionPool;
    protected string $table;
    protected string $sortBy;

    public function __construct(
        ?ConnectionPool $connectionPool = null,
        string $table = 'tx_aisuite_domain_model_global_instructions',
        string $sortBy = 'name'
    ) {
        $this->connectionPool = $connectionPool ?: GeneralUtility::makeInstance(ConnectionPool::class);
        $this->table = $table;
        $this->sortBy = $sortBy;
    }

    /**
     * @param list<int> $allowedMounts
     *
     * @return list<array<string, mixed>>
     */
    public function findByAllowedMounts(array $allowedMounts, string $search = '', string $scope = '', string $context = 'pages'): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class)
        ;
        $queryBuilder
            ->select('gi.*', 'p.title AS page_title')
            ->from($this->table, 'gi')
            ->leftJoin(
                'gi',
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('gi.pid'))
            )
            ->where(
                $queryBuilder->expr()->in('gi.pid', $allowedMounts),
                $queryBuilder->expr()->eq('gi.context', $queryBuilder->createNamedParameter($context))
            )
        ;
        if ('' !== $search) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->like('gi.name', $queryBuilder->createNamedParameter('%'.$search.'%')),
                    $queryBuilder->expr()->like('gi.instructions', $queryBuilder->createNamedParameter('%'.$search.'%'))
                )
            );
        }
        if ('' !== $scope) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('gi.scope', $queryBuilder->createNamedParameter($scope)),
                    $queryBuilder->expr()->eq('gi.scope', $queryBuilder->createNamedParameter('general'))
                )
            );
        }
        $queryBuilder->orderBy($this->sortBy, 'ASC');
        $result = $queryBuilder->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByScope(string $scope, string $context = ''): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->table)
        ;
        if ('' !== $scope) {
            $queryBuilder->where(
                $queryBuilder->expr()->eq('context', $queryBuilder->createNamedParameter($context)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter($scope)),
                    $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter('general'))
                )
            );
        } else {
            $queryBuilder
                ->where(
                    $queryBuilder->expr()->eq('context', $queryBuilder->createNamedParameter($context)),
                    $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter($scope))
                )
            ;
        }
        $result = $queryBuilder->executeQuery();

        return $result->fetchAllAssociative() ?: [];
    }

    /**
     * @param list<int|string> $selectedTree
     *
     * @return array<string, mixed>
     */
    public function findExistingGlobalInstruction(string $context, string $scope, array $selectedTree): array
    {
        $selectionIdentifier = 'pages' === $context ? 'selected_pages' : 'selected_directories';
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('context', $queryBuilder->createNamedParameter($context)),
                $queryBuilder->expr()->eq('scope', $queryBuilder->createNamedParameter($scope)),
            )
        ;
        if ('pages' === $context) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in($selectionIdentifier, $selectedTree)
            );
        } else {
            $orExpressions = [];
            foreach ($selectedTree as $value) {
                $orExpressions[] = $queryBuilder->expr()->inSet($selectionIdentifier, $queryBuilder->createNamedParameter($value));
            }
            if (count($orExpressions) > 0) {
                $queryBuilder->andWhere($queryBuilder->expr()->or(...$orExpressions));
            }
        }
        $result = $queryBuilder
            ->setMaxResults(1)
            ->executeQuery()
        ;

        return $result->fetchAssociative() ?: [];
    }

    public function deactivateElement(int $id): void
    {
        $data = [
            $this->table => [
                $id => [
                    'hidden' => 1,
                ],
            ],
        ];
        $this->handleWithDataHandler($data, []);
    }

    public function activateElement(int $id): void
    {
        $data = [
            $this->table => [
                $id => [
                    'hidden' => 0,
                ],
            ],
        ];
        $this->handleWithDataHandler($data, []);
    }

    public function deleteElement(int $id): void
    {
        $cmd = [
            $this->table => [
                $id => [
                    'delete' => 1,
                ],
            ],
        ];
        $this->handleWithDataHandler([], $cmd);
    }

    /**
     * @param array<string, mixed> $cmd
     * @param array<string, mixed> $data
     */
    protected function handleWithDataHandler(array $data, array $cmd): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, $cmd);
        if (count($data) > 0) {
            $dataHandler->process_datamap();
        }
        if (count($cmd) > 0) {
            $dataHandler->process_cmdmap();
        }
    }
}
