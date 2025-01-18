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

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;

class RequestsRepository
{
    protected ConnectionPool $connectionPool;
    protected string $table = 'tx_aisuite_domain_model_requests';
    protected string $sortBy = 'uid';

    public function __construct(ConnectionPool $connectionPool) {
        $this->connectionPool = $connectionPool;
    }

    /**
     * @throws DriverException
     * @throws DBALException
     */
    public function findFirstEntry(): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $result = $queryBuilder
            ->select('free_requests', 'paid_requests', 'abo_requests', 'model_type')
            ->from($this->table)
            ->executeQuery()
            ->fetchAllAssociative();
        return array_key_exists('0', $result) ? $result[0] : [];
    }

    /**
     * @throws DriverException|DBALException
     */
    public function setRequests(int $freeRequests, int $paidRequests, int $aboRequests, string $modelType): void
    {
        if (count($this->findFirstEntry()) > 0 && $freeRequests > -1 && $paidRequests > -1) {
            $this->updateRequests($freeRequests, $paidRequests, $aboRequests, $modelType);
        } elseif (count($this->findFirstEntry()) > 0 && $freeRequests < 0 && $paidRequests < 0) {
            $this->deleteRequests();
        } else {
            $this->insertRequests($freeRequests, $paidRequests, $aboRequests, $modelType);
        }
    }

    /**
     * @throws Exception|DBALException
     */
    public function updateRequests(int $freeRequests, int $paidRequests, int $aboRequests, string $modelType): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->update($this->table)
            ->set('free_requests', $freeRequests)
            ->set('paid_requests', $paidRequests)
            ->set('abo_requests', $aboRequests)
            ->set('model_type', $modelType)
            ->executeStatement();
    }

    /**
     * @throws Exception|DBALException
     */
    public function insertRequests(int $freeRequests, int $paidRequests, int $aboRequests, string $modelType): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->insert($this->table)
            ->values([
                'free_requests' => $freeRequests,
                'paid_requests' => $paidRequests,
                'abo_requests' => $aboRequests,
                'model_type' => $modelType
            ])
            ->executeStatement();
    }

    /**
     * @throws DBALException
     */
    public function deleteRequests(): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->delete($this->table)
            ->executeStatement();
    }
}
