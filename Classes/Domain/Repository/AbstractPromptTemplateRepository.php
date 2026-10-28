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

use AutoDudes\AiSuite\Service\TcaCompatibilityService;
use AutoDudes\AiSuite\Service\WorkspaceContextService;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractPromptTemplateRepository
{
    protected ConnectionPool $connectionPool;
    protected WorkspaceContextService $workspaceContextService;
    protected TcaCompatibilityService $tcaCompatibilityService;
    protected string $table = '';
    protected string $sortBy = '';

    public function __construct(
        ConnectionPool $connectionPool,
        WorkspaceContextService $workspaceContextService,
        TcaCompatibilityService $tcaCompatibilityService,
        string $table = '',
        string $sortBy = ''
    ) {
        $this->connectionPool = $connectionPool;
        $this->workspaceContextService = $workspaceContextService;
        $this->tcaCompatibilityService = $tcaCompatibilityService;
        $this->table = $table;
        $this->sortBy = $sortBy;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function findByScopeAndType(string $scope, string $type = '', int $languageId = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->select('*')
            ->from($this->table)
            ->orderBy($this->sortBy, 'DESC')
        ;

        $data = [];
        foreach ($this->fetchVisibleRows($queryBuilder) as $row) {
            if ($this->matchesScopeAndType($row, $scope, $type, $languageId)) {
                $data[] = ['name' => $row['name'] ?? '', 'prompt' => $row['prompt'] ?? ''];
            }
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function findAll(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->select('*')->from($this->table);

        return $this->fetchVisibleRows($queryBuilder);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllEnabled(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->select('*')
            ->from($this->table)
            ->orderBy($this->sortBy, 'ASC')
        ;

        return array_map(
            static fn (array $row): array => [
                'uid' => $row['uid'],
                'name' => $row['name'] ?? '',
                'scope' => $row['scope'] ?? '',
                'type' => $row['type'] ?? '',
            ],
            $this->fetchVisibleRows($queryBuilder),
        );
    }

    /**
     * @return null|array<string, mixed>
     */
    public function findByUid(int $uid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->select('*')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
        ;

        return $this->fetchVisibleRows($queryBuilder)[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchVisibleRows(QueryBuilder $queryBuilder, bool $includeHidden = false): array
    {
        $workspaceId = $this->workspaceContextService->getWorkspaceId();
        $queryBuilder->getRestrictions()->add(
            GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId),
        );

        $rows = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            BackendUtility::workspaceOL($this->table, $row, $workspaceId);
            if (!is_array($row)
                || $this->tcaCompatibilityService->isDeletePlaceholderState($row['t3ver_state'] ?? 0)
                || (!$includeHidden && 1 === (int) ($row['hidden'] ?? 0))
            ) {
                continue;
            }
            $rows[] = BackendUtility::purgeComputedPropertiesFromRecord($row);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function matchesScopeAndType(array $row, string $scope, string $type, int $languageId): bool
    {
        if (!in_array((int) ($row['sys_language_uid'] ?? 0), [$languageId, -1], true)) {
            return false;
        }

        $rowScope = (string) ($row['scope'] ?? '');
        if ('general' === $rowScope) {
            return true;
        }
        if ($scope !== $rowScope) {
            return false;
        }

        $rowType = (string) ($row['type'] ?? '');

        return '' === $type || '' === $rowType || in_array($type, explode(',', $rowType), true);
    }
}
