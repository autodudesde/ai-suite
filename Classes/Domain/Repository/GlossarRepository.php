<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Domain\Repository;

use AutoDudes\AiSuite\Service\WorkspaceContextService;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class GlossarRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        WorkspaceContextService $workspaceContextService,
        string $table = 'tx_aisuite_domain_model_glossar',
        string $sortBy = 'input'
    ) {
        parent::__construct($connectionPool, $workspaceContextService, $table, $sortBy);
    }

    /**
     * @return list<int>
     */
    public function findDistinctPids(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);

        return array_map(
            'intval',
            $queryBuilder->selectLiteral('DISTINCT pid')
                ->from($this->table)
                ->where(
                    $queryBuilder->expr()->gt('pid', 0)
                )
                ->executeQuery()
                ->fetchFirstColumn()
        );
    }

    /**
     * @param list<int> $pids
     *
     * @return list<array{uid: int, l18n_parent: int, input: string}>
     *
     * @throws Exception
     */
    public function findBySysLanguageUidAndPages(int $languageId, array $pids): array
    {
        if ([] === $pids) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $rows = $queryBuilder
            ->select('uid', 'l18n_parent', 'input')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($pids, Connection::PARAM_INT_ARRAY))
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        return array_map(
            static fn (array $row): array => [
                'uid' => (int) $row['uid'],
                'l18n_parent' => (int) $row['l18n_parent'],
                'input' => (string) $row['input'],
            ],
            $rows
        );
    }

    /**
     * @param list<int> $parentUids
     * @param list<int> $pids
     *
     * @return array<int, string>
     *
     * @throws Exception
     */
    public function findSourceInputsByParents(array $parentUids, int $sourceLanguageUid, array $pids): array
    {
        if ([] === $parentUids || [] === $pids) {
            return [];
        }
        $keyField = 0 === $sourceLanguageUid ? 'uid' : 'l18n_parent';
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $rows = $queryBuilder
            ->select($keyField, 'input')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in($keyField, $queryBuilder->createNamedParameter($parentUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($sourceLanguageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($pids, Connection::PARAM_INT_ARRAY))
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        $inputs = [];
        foreach ($rows as $row) {
            $inputs[(int) $row[$keyField]] = (string) $row['input'];
        }

        return $inputs;
    }

    public function findGlossarEntriesByPid(int $pid): mixed
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);

        return $queryBuilder->count('pid')
            ->from('tx_aisuite_domain_model_glossar')
            ->where($queryBuilder->expr()->eq('pid', $pid))
            ->executeQuery()
            ->fetchOne()
        ;
    }

    /**
     * @param list<int> $foundPages
     *
     * @return list<array<string, mixed>>
     */
    public function findAllEntriesForPages(array $foundPages): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);

        return $queryBuilder->select('*')
            ->from('tx_aisuite_domain_model_glossar')
            ->where(
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($foundPages, Connection::PARAM_INT_ARRAY))
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @return array<string, mixed>
     */
    public function findDeeplGlossaryEntry(int $rootPageId, int $defaultLanguageId, int $targetLanguageId): array|false
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_aisuite_domain_model_deepl');

        return $queryBuilder->select('*')
            ->from('tx_aisuite_domain_model_deepl')
            ->where(
                $queryBuilder->expr()->eq('root_page_uid', $queryBuilder->createNamedParameter($rootPageId)),
                $queryBuilder->expr()->eq('default_language_id', $queryBuilder->createNamedParameter($defaultLanguageId)),
                $queryBuilder->expr()->eq('target_language_id', $queryBuilder->createNamedParameter($targetLanguageId))
            )
            ->executeQuery()
            ->fetchAssociative()
        ;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findDeeplGlossaryUuidsByRootPageId(int $rootPageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_aisuite_domain_model_deepl');

        return $queryBuilder->select('source_lang', 'target_lang', 'glossar_uuid')
            ->from('tx_aisuite_domain_model_deepl')
            ->where(
                $queryBuilder->expr()->eq('root_page_uid', $queryBuilder->createNamedParameter($rootPageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('external', 0)
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findDeeplGlossaryUuidsBySourceAndTargetLanguage(string $sourceLang, string $targetLang): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_aisuite_domain_model_deepl');

        return $queryBuilder->select('*')
            ->from('tx_aisuite_domain_model_deepl')
            ->where(
                $queryBuilder->expr()->eq('source_lang', $queryBuilder->createNamedParameter($sourceLang)),
                $queryBuilder->expr()->eq('target_lang', $queryBuilder->createNamedParameter($targetLang))
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @param array<string, mixed> $existingRecord
     * @param list<string>         $nameParts
     */
    public function insertOrUpdateDeeplGlossaryEntry(array|bool $existingRecord, string $glossaryId, int $rootPageId, array $nameParts, int $defaultLanguageId, ?int $targetLanguageId): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_aisuite_domain_model_deepl');
        if (is_array($existingRecord)) {
            $connection->update(
                'tx_aisuite_domain_model_deepl',
                [
                    'glossar_uuid' => $glossaryId,
                    'default_language_id' => $defaultLanguageId,
                    'target_language_id' => $targetLanguageId,
                ],
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
                'root_page_uid' => $rootPageId,
                'default_language_id' => $defaultLanguageId,
                'target_language_id' => $targetLanguageId,
            ];
            $connection->insert('tx_aisuite_domain_model_deepl', $data);
        }
    }
}
