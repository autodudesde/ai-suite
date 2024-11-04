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

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;

class RequestsRepository extends AbstractPromptTemplateRepository
{
    protected ConnectionPool $connectionPool;
    protected string $table = 'tx_aisuite_domain_model_requests';
    protected string $sortBy = 'uid';

    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'tx_aisuite_domain_model_requests',
        string $sortBy = 'uid'
    ) {
        parent::__construct(
            $connectionPool,
            $table,
            $sortBy
        );
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception|Exception
     */
    public function findFirstEntry(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $result = $queryBuilder
            ->select('free_requests', 'paid_requests')
            ->from($this->table)
            ->executeQuery()
            ->fetchAllAssociative();
        return array_key_exists('0', $result) ? $result[0] : [];
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function setRequests(int $freeRequests, int $paidRequests): void
    {
        if (count($this->findFirstEntry()) > 0 && $freeRequests > -1 && $paidRequests > -1) {
            $this->updateRequests($freeRequests, $paidRequests);
        } elseif (count($this->findFirstEntry()) > 0 && $freeRequests < 0 && $paidRequests < 0) {
            $this->deleteRequests();
        } else {
            $this->insertRequests($freeRequests, $paidRequests);
        }
    }

    /**
     * @throws Exception
     */
    public function updateRequests(int $freeRequests, int $paidRequests): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->update($this->table)
            ->set('free_requests', $freeRequests)
            ->set('paid_requests', $paidRequests)
            ->executeStatement();
    }

    /**
     * @throws Exception
     */
    public function insertRequests(int $freeRequests, int $paidRequests): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->insert($this->table)
            ->values([
                'free_requests' => $freeRequests,
                'paid_requests' => $paidRequests
            ])
            ->executeStatement();
    }
    public function deleteRequests(): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->delete($this->table)
            ->executeStatement();
    }
}
