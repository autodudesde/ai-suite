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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CustomPromptTemplateRepository extends AbstractPromptTemplateRepository
{
    protected ConnectionPool $connectionPool;
    protected string $table;
    protected string $sortBy;

    public function __construct(
        ConnectionPool $connectionPool,
        WorkspaceContextService $workspaceContextService,
        TcaCompatibilityService $tcaCompatibilityService,
        string $table = 'tx_aisuite_domain_model_custom_prompt_template',
        string $sortBy = 'name'
    ) {
        parent::__construct(
            $connectionPool,
            $workspaceContextService,
            $tcaCompatibilityService,
            $table,
            $sortBy
        );
    }

    /**
     * @param list<int> $allowedMounts
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function findByAllowedMounts(array $allowedMounts, string $search): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class)
        ;
        $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($allowedMounts, Connection::PARAM_INT_ARRAY))
            )
            ->orderBy($this->sortBy, 'ASC')
        ;

        $rows = $this->fetchVisibleRows($queryBuilder, true);
        if ('' === $search) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => false !== mb_stripos((string) ($row['name'] ?? ''), $search)
                || false !== mb_stripos((string) ($row['prompt'] ?? ''), $search),
        ));
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
