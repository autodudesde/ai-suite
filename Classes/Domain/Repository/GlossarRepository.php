<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Domain\Repository;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

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

    public function findDeeplGlossaryEntry(int $rootPageId, int $defaultLanguageId, int $targetLanguageId)
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
            ->fetchAllAssociative();
    }

    public function insertOrUpdateDeeplGlossaryEntry(array|bool $existingRecord, string $glossaryId, int $rootPageId, array $nameParts, int $defaultLanguageId, ?int $targetLanguageId): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_aisuite_domain_model_deepl');
        if (is_array($existingRecord)) {
            $connection->update(
                'tx_aisuite_domain_model_deepl',
                [
                    'glossar_uuid' => $glossaryId,
                    'default_language_id' => $defaultLanguageId,
                    'target_language_id' => $targetLanguageId
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
                'target_language_id' => $targetLanguageId
            ];
            $connection->insert('tx_aisuite_domain_model_deepl', $data);
        }
    }

    public function findDistinctRootPageUidsWithGlossaryEntries(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $pagesWithGlossaries = $queryBuilder->selectLiteral('DISTINCT pid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->gt('pid', 0)
            )
            ->executeQuery()
            ->fetchFirstColumn();

        if (empty($pagesWithGlossaries)) {
            return [];
        }

        $rootPageUids = [];

        foreach ($pagesWithGlossaries as $pid) {
            try {
                $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pid);
                $rootline = $rootlineUtility->get();

                $rootPageUid = 0;
                foreach ($rootline as $page) {
                    if ((int)$page['pid'] === 0 || (int)$page['is_siteroot'] === 1) {
                        $rootPageUid = (int)$page['uid'];
                        break;
                    }
                }

                if ($rootPageUid > 0 && !in_array($rootPageUid, $rootPageUids)) {
                    $rootPageUids[] = $rootPageUid;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $rootPageUids;
    }

}
