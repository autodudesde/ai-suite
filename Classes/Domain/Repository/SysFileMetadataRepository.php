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

class SysFileMetadataRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'sys_file_metadata',
        string $sortBy = 'title'
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
    public function findByLangUidAndFileIdList(
        array $uids,
        string $column,
        string $indexColumn = 'uid',
        int $languageUid = 0,
        bool $showOnlyEmpty = false,
        bool $showOnlyUsed = false
    ): array {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $constraints = [
            $queryBuilder->expr()->in('file', $uids),
            $queryBuilder->expr()->eq('sys_language_uid', $languageUid)
        ];
        if ($showOnlyEmpty) {
            if ($column === 'title') {
                $constraints[] = $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('title')
                );
            } elseif ($column === 'alternative') {
                $constraints[] = $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('alternative', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('alternative')
                );
            } else {
                $constraints[] = $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('title')
                );
                $constraints[] = $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('alternative', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('alternative')
                );
            }
        }
        $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(...$constraints);
        $metadataList = $queryBuilder->executeQuery()->fetchAllAssociative();
        if ($showOnlyUsed) {
            foreach ($metadataList as $key => $metadata) {
                if (!$this->isFileUsed($metadata['file'])) {
                    unset($metadataList[$key]);
                }
            }
        }
        return array_column($metadataList, null, $indexColumn);
    }

    public function findByUidList(array $uids, string $indexColumn = 'uid'): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('uid', $uids)
            );
        $metadataList = $queryBuilder->executeQuery()->fetchAllAssociative();
        return array_column($metadataList, null, $indexColumn);
    }

    public function findDefaultLanguageMetadataUidsByFileUids(array $fileUids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder
            ->select('uid', 'file')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('file', $fileUids),
                $queryBuilder->expr()->eq('sys_language_uid', 0)
            );
        $result = $queryBuilder->executeQuery()->fetchAllAssociative();
        return array_column($result, 'uid', 'file');
    }

    public function findTranslatedMetadataUid(int $l10nParent, int $fileUid, int $languageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $l10nParent),
                $queryBuilder->expr()->eq('file', $fileUid),
                $queryBuilder->expr()->eq('sys_language_uid', $languageId)
            );
        return $queryBuilder->executeQuery()->fetchFirstColumn();
    }

    public function isFileUsed(int $fileUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable("sys_refindex");
        $queryBuilder
            ->count('recuid')
            ->from('sys_refindex')
            ->where(
                $queryBuilder->expr()->eq('ref_table', $queryBuilder->createNamedParameter('sys_file')),
                $queryBuilder->expr()->neq('tablename', $queryBuilder->createNamedParameter('sys_file_metadata')),
                $queryBuilder->expr()->eq('ref_uid', $queryBuilder->createNamedParameter($fileUid))
            );
        return $queryBuilder->executeQuery()->fetchOne() > 0;
    }
}
