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
    public function findEntryByApiKey(string $apiKey): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $result = $queryBuilder
            ->select('free_requests', 'paid_requests', 'abo_requests', 'model_type')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('api_key', $queryBuilder->createNamedParameter($apiKey))
            )
            ->executeQuery()
            ->fetchAllAssociative();
        return array_key_exists('0', $result) ? $result[0] : [];
    }

    /**
     * @throws DriverException|DBALException
     */
    public function setRequests(int $freeRequests, int $paidRequests, int $aboRequests, string $modelType, string $apiKey): void
    {
        $apiKeyEntity = $this->findEntryByApiKey($apiKey);
        if (count($apiKeyEntity) > 0 && $freeRequests > -1 && $paidRequests > -1) {
            $this->updateRequests($freeRequests, $paidRequests, $aboRequests, $modelType, $apiKey);
        } elseif (count($apiKeyEntity) > 0 && $freeRequests < 0 && $paidRequests < 0) {
            $this->deleteRequests($apiKey);
        } else {
            $this->insertRequests($freeRequests, $paidRequests, $aboRequests, $modelType, $apiKey);
        }
    }

    /**
     * @throws Exception|DBALException
     */
    public function updateRequests(int $freeRequests, int $paidRequests, int $aboRequests, string $modelType, string $apiKey): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->update($this->table)
            ->set('free_requests', $freeRequests)
            ->set('paid_requests', $paidRequests)
            ->set('abo_requests', $aboRequests)
            ->set('model_type', $modelType)
            ->set('api_key', $apiKey)
            ->where(
                $queryBuilder->expr()->eq('api_key', $queryBuilder->createNamedParameter($apiKey))
            )
            ->executeStatement();
    }

    /**$queryBuilder
     * @throws Exception|DBALException
     */
    public function insertRequests(int $freeRequests, int $paidRequests, int $aboRequests, string $modelType, string $apiKey): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->insert($this->table)
            ->values([
                'free_requests' => $freeRequests,
                'paid_requests' => $paidRequests,
                'abo_requests' => $aboRequests,
                'model_type' => $modelType,
                'api_key' => $apiKey
            ])
            ->executeStatement();
    }

    /**
     * @throws DBALException
     */
    public function deleteRequests(string $apiKey = ''): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder->delete($this->table);
        if(!empty($apiKey)) {
            $queryBuilder->where(
                $queryBuilder->expr()->eq('api_key', $queryBuilder->createNamedParameter($apiKey))
            );
        }
        $queryBuilder->executeStatement();
    }
}
