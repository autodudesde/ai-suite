<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Domain\Repository;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class GlossarRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'tx_aisuite_domain_model_glossar',
        string $sortBy = 'input'
    ) {
        parent::__construct($connectionPool, $table, $sortBy);
    }

    /**
     * @throws Exception
     */
    public function findBySysLanguageUid(int $languageId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->select("l18n_parent", 'input')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $languageId)
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function findEntryByUid(int $l18n_parent)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->select('input')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('uid', $l18n_parent)
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    public function findEntryByL18nParentAndUid(int $l18n_parent, int $srcLangUid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->select('input')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('l18n_parent', $l18n_parent),
                $queryBuilder->expr()->eq('sys_language_uid', $srcLangUid)
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    public function findGlossarEntriesByPid(int $pid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        return $queryBuilder->count('pid')
                    ->from('tx_aisuite_domain_model_glossar')
                    ->where($queryBuilder->expr()->eq('pid', $pid))
                    ->executeQuery()
                    ->fetchOne();
    }

    public function findAllEntriesForPages(array $foundPages)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        return $queryBuilder->select('*')
            ->from('tx_aisuite_domain_model_glossar')
            ->where(
                $queryBuilder->expr()->in('pid', $foundPages)
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function findAll()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->select('input')
            ->from($this->table)
            ->executeQuery()
            ->fetchAssociative();
    }

    public function findDeeplGlossaryEntry(int $rootPageId, string $sourceLang, string $targetLang)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_aisuite_domain_model_deepl');
        return $queryBuilder->select('*')
            ->from('tx_aisuite_domain_model_deepl')
            ->where(
                $queryBuilder->expr()->eq('root_page_uid', $queryBuilder->createNamedParameter($rootPageId)),
                $queryBuilder->expr()->eq('source_lang', $queryBuilder->createNamedParameter($sourceLang)),
                $queryBuilder->expr()->eq('target_lang', $queryBuilder->createNamedParameter($targetLang))
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    public function findDeeplGlossaryUuidsByRootPageId(int $rootPageId) {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_aisuite_domain_model_deepl');
        return $queryBuilder->select('source_lang', 'target_lang', 'glossar_uuid')
            ->from('tx_aisuite_domain_model_deepl')
            ->where(
                $queryBuilder->expr()->eq('root_page_uid', $queryBuilder->createNamedParameter($rootPageId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function insertOrUpdateDeeplGlossaryEntry(array|bool $existingRecord, string $glossaryId, string $rootPageId, array $nameParts): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_aisuite_domain_model_deepl');
        if (is_array($existingRecord)) {
            $connection->update(
                'tx_aisuite_domain_model_deepl',
                ['glossar_uuid' => $glossaryId],
                [
                    'root_page_uid' => $existingRecord['root_page_uid'],
                    'source_lang' => $existingRecord['source_lang'],
                    'target_lang' => $existingRecord['target_lang'],
                ]
            );
        } else {
            $data = [
                'glossar_uuid' => $glossaryId,
                'source_lang' => $nameParts[1],
                'target_lang' => $nameParts[2],
                'root_page_uid' => $rootPageId
            ];
            $connection->insert('tx_aisuite_domain_model_deepl', $data);
        }
    }
}
