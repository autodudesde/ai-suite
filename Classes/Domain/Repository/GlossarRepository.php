<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Domain\Repository;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;

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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder->count('pid')
                    ->from('tx_aisuite_domain_model_glossar')
                    ->where($queryBuilder->expr()->eq('pid', $pid))
                    ->executeQuery()
                    ->fetchOne();
    }

    public function findAllEntriesForPages(array $foundPages)
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        return $queryBuilder
            ->select('input')
            ->from($this->table)
            ->executeQuery()
            ->fetchAssociative();
    }

    public function findDeeplGlossaryEntry(int $rootPageId, string $sourceLang, string $targetLang)
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_aisuite_domain_model_deepl');
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

    public function findDeeplGlossaryUuidsByRootPageId(int $rootPageId)
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_aisuite_domain_model_deepl');
        return $queryBuilder->select('source_lang', 'target_lang', 'glossar_uuid')
            ->from('tx_aisuite_domain_model_deepl')
            ->where(
                $queryBuilder->expr()->eq('root_page_uid', $queryBuilder->createNamedParameter($rootPageId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function insertOrUpdateDeeplGlossaryEntry(array|bool $existingRecord, string $glossaryId, int $rootPageId, array $nameParts): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_aisuite_domain_model_deepl');
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
