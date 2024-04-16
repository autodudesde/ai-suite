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
        string $table = 'tx_aisuite_domain_model_custom_prompt_template',
        string $sortBy = 'name'
    ) {
        parent::__construct(
            $connectionPool,
            $table,
            $sortBy
        );
    }

    /**
     * @throws Exception
     */
    public function findByAllowedMounts(array $allowedMounts, string $search): array
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable($this->table)->createQueryBuilder();
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);
        $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('pid', $allowedMounts)
            );
        if($search !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->like('name', $queryBuilder->createNamedParameter('%' . $search . '%')),
                    $queryBuilder->expr()->like('prompt', $queryBuilder->createNamedParameter('%' . $search . '%'))
                )
            );
        }
        $queryBuilder->orderBy($this->sortBy, 'ASC');
        $result = $queryBuilder->executeQuery();
        return $result->fetchAllAssociative();
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

    protected function handleWithDataHandler($data, $cmd): void {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, $cmd);
        if(count($data) > 0) {
            $dataHandler->process_datamap();
        }
        if(count($cmd) > 0) {
            $dataHandler->process_cmdmap();
        }
    }
}
