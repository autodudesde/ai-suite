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

class RequestsRepository
{
    protected ConnectionPool $connectionPool;
    protected string $table = 'tx_aisuite_domain_model_requests';

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * @return array<string, mixed>
     */
    public function findEntryByApiKey(string $apiKey): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $result = $queryBuilder
            ->select('free_requests', 'paid_requests', 'abo_requests', 'model_type')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('api_key', $queryBuilder->createNamedParameter($this->keyOf($apiKey)))
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        return array_key_exists('0', $result) ? $result[0] : [];
    }

    public function setRequests(int $freeRequests, int $paidRequests, int $aboRequests, string $modelType, string $apiKey): void
    {
        $this->deleteUnhashedKeys();
        $apiKeyEntity = $this->findEntryByApiKey($apiKey);
        if (count($apiKeyEntity) > 0 && $freeRequests > -1 && $paidRequests > -1) {
            $this->updateRequests($freeRequests, $paidRequests, $aboRequests, $modelType, $apiKey);
        } elseif (count($apiKeyEntity) > 0 && $freeRequests < 0 && $paidRequests < 0) {
            $this->deleteRequests($apiKey);
        } else {
            $this->insertRequests($freeRequests, $paidRequests, $aboRequests, $modelType, $apiKey);
        }
    }

    public function updateRequests(int $freeRequests, int $paidRequests, int $aboRequests, string $modelType, string $apiKey): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder
            ->update($this->table)
            ->set('free_requests', $freeRequests)
            ->set('paid_requests', $paidRequests)
            ->set('abo_requests', $aboRequests)
            ->set('model_type', $modelType)
            ->where(
                $queryBuilder->expr()->eq('api_key', $queryBuilder->createNamedParameter($this->keyOf($apiKey)))
            )
            ->executeStatement()
        ;
    }

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
                'api_key' => $this->keyOf($apiKey),
            ])
            ->executeStatement()
        ;
    }

    public function deleteRequests(string $apiKey = ''): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder->delete($this->table);
        if (!empty($apiKey)) {
            $queryBuilder->where(
                $queryBuilder->expr()->eq('api_key', $queryBuilder->createNamedParameter($this->keyOf($apiKey)))
            );
        }
        $queryBuilder->executeStatement();
    }

    private function keyOf(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }

    private function deleteUnhashedKeys(): void
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $storedKeys = $queryBuilder->select('api_key')->from($this->table)->executeQuery()->fetchFirstColumn();
        foreach (array_unique(array_map('strval', $storedKeys)) as $storedKey) {
            if (1 === preg_match('/^[a-f0-9]{64}$/', $storedKey)) {
                continue;
            }
            $delete = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
            $delete->delete($this->table)
                ->where($delete->expr()->eq('api_key', $delete->createNamedParameter($storedKey)))
                ->executeStatement()
            ;
        }
    }
}
