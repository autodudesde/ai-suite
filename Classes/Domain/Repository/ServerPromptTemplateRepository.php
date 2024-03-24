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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ServerPromptTemplateRepository extends AbstractPromptTemplateRepository
{
    public function __construct(
        protected ConnectionPool $connectionPool,
        protected string $table = 'tx_aisuite_domain_model_server_prompt_template',
        string $sortBy = 'name'
    ) {
        parent::__construct(
            $connectionPool,
            $table,
            $sortBy
        );
    }


    /**
     * @return int returns the number of affected rows
     */
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
