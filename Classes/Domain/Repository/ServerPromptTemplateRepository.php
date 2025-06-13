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

use TYPO3\CMS\Core\Database\ConnectionPool;

class ServerPromptTemplateRepository extends AbstractPromptTemplateRepository
{
    protected ConnectionPool $connectionPool;
    protected string $table;
    protected string $sortBy;

    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'tx_aisuite_domain_model_server_prompt_template',
        string $sortBy = 'name'
    ) {
        parent::__construct(
            $connectionPool,
            $table,
            $sortBy
        );
    }

    public function truncateTable(): int
    {
        return $this->connectionPool->getConnectionForTable($this->table)->truncate($this->table);
    }

    public function insertData(array $data): void
    {
        $this->connectionPool->getConnectionForTable($this->table)->insert($this->table, $data);
    }

    public function insertList(array $data): void
    {
        if (count($data) > 0) {
            foreach ($data as $row) {
                if (is_array($row) && count($row) > 0) {
                    $this->insertData($row);
                }
            }
        }
    }
}
