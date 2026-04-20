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

use AutoDudes\AiSuite\Domain\Model\Pages;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PagesRepository extends AbstractRepository
{
    /** @var list<int> */
    protected array $ignorePageTypes = [3, 4, 6, 7, 199, 254, 255];

    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'pages',
        string $sortBy = 'sorting'
    ) {
        parent::__construct($connectionPool, $table, $sortBy);
    }

    /**
     * @param array<string, mixed> $uidList
     *
     * @return list<array<string, mixed>>
     */
    public function findAvailablePages(array $uidList = []): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;
        $queryBuilder
            ->select('*')
            ->from($this->table)
        ;
        $constraints = [
            $queryBuilder->expr()->eq('l10n_parent', 0),
        ];
        if (count($uidList) > 0) {
            $constraints[] = $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uidList, Connection::PARAM_INT_ARRAY));
        }
        $queryBuilder->where(...$constraints);

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    public function addPage(Pages $page): string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->insert($this->table)->values($page->toDatabase());
        $queryBuilder->executeStatement();

        return $queryBuilder->getConnection()->lastInsertId();
    }

    /**
     * @param list<int>            $foundPageUids
     * @param array<string, mixed> $workflowData
     *
     * @return list<array<string, mixed>>
     */
    public function fetchNecessaryPageData(array $workflowData, array $foundPageUids, string $mode = 'pages'): array
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $context->getPropertyFromAspect('workspace', 'id')))
        ;
        $fields = ['uid', 'title', 'slug'];
        if ('pages' === $mode) {
            $fields[] = $workflowData['column'].' AS columnValue';
        }
        if ('pages' === $mode) {
            $queryBuilder->select(...$fields)
                ->from($this->table)
                ->where(
                    $queryBuilder->expr()->notIn('doktype', $queryBuilder->createNamedParameter($this->ignorePageTypes, Connection::PARAM_INT_ARRAY))
                )
            ;
        } else {
            $queryBuilder->select(...$fields)
                ->from($this->table)
            ;
        }
        $languageParts = explode('__', $workflowData['sysLanguage']);
        if (isset($languageParts[1]) && (int) $languageParts[1] > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('l10n_parent', $queryBuilder->createNamedParameter($foundPageUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter((int) $languageParts[1], Connection::PARAM_INT))
            );
        } else {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($foundPageUids, Connection::PARAM_INT_ARRAY)),
            );
        }
        if ('pages' === $mode && (int) $workflowData['pageType'] > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter($workflowData['pageType'], Connection::PARAM_INT))
            );
        }
        if ('pages' === $mode && $workflowData['showOnlyEmpty']) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq($workflowData['column'], $queryBuilder->createNamedParameter('', Connection::PARAM_STR)),
                    $queryBuilder->expr()->isNull($workflowData['column'])
                )
            );
        }

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @todo: PageRepository should not be responsible for fetching tt_content
     *
     * @param list<int> $pids
     *
     * @return list<array<string, mixed>>
     */
    public function getAvailableNewsDetailPlugins(array $pids, int $languageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');

        return $queryBuilder->select('tt_content.pid', 'p.title')
            ->from('tt_content')
            ->leftJoin(
                'tt_content',
                $this->table,
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('tt_content.pid'))
            )
            ->where(
                $queryBuilder->expr()->in('tt_content.pid', $pids),
                $queryBuilder->expr()->eq('tt_content.sys_language_uid', $languageId),
                $queryBuilder->expr()->eq('tt_content.CType', $queryBuilder->createNamedParameter('news_newsdetail')),
                $queryBuilder->expr()->eq('p.deleted', 0)
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @todo: PageRepository should not be responsible for fetching sys_file_references
     *
     * @param list<int> $pagesUids
     *
     * @return list<array<string, mixed>>
     */
    public function fetchSysFileReferences(array $pagesUids, string $column, int $sysLanguageUid, bool $showOnlyEmpty): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->select('sfr.uid', 'sfr.pid', 'sfr.tablenames', 'sfr.fieldname', 'sfr.uid_local', 'sfr.uid_foreign', 'sfr.'.$column.' AS columnValue', 'sf.name AS fileName', 'sf.mime_type AS fileMimeType', 'sf.size AS size')
            ->from('sys_file_reference', 'sfr')
            ->leftJoin(
                'sfr',
                'sys_file',
                'sf',
                $queryBuilder->expr()->eq('sf.uid', $queryBuilder->quoteIdentifier('sfr.uid_local'))
            )
            ->where(
                $queryBuilder->expr()->eq('sf.type', 2),
                $queryBuilder->expr()->in('sfr.pid', $pagesUids),
                $queryBuilder->expr()->eq('sfr.sys_language_uid', $queryBuilder->createNamedParameter($sysLanguageUid)),
                $queryBuilder->expr()->eq('sf.missing', 0),
            )
        ;
        if (true === $showOnlyEmpty) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('sfr.'.$column),
                    $queryBuilder->expr()->eq('sfr.'.$column, $queryBuilder->createNamedParameter('', Connection::PARAM_STR))
                )
            );
        }

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    public function checkPageTranslationExists(int $pageId, int $languageUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;
        $pageTranslationExists = $queryBuilder
            ->count('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne()
        ;

        return $pageTranslationExists > 0;
    }

    public function getPageIdFromFileReference(int $fileRefUid): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');

        $result = $queryBuilder
            ->select('p.uid')
            ->from('sys_file_reference', 'sfr')
            ->join('sfr', 'pages', 'p', 'sfr.uid_foreign = p.uid')
            ->where(
                $queryBuilder->expr()->eq('sfr.uid', $queryBuilder->createNamedParameter($fileRefUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sfr.tablenames', $queryBuilder->createNamedParameter('pages'))
            )
            ->executeQuery()
            ->fetchAssociative()
        ;

        return $result ? (int) $result['uid'] : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFileReferencesOnPage(int $pageId, int $languageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('sfr.*')
            ->from('sys_file_reference', 'sfr')
            ->join(
                'sfr',
                'pages',
                'p',
                $queryBuilder->expr()->eq('sfr.uid_foreign', 'p.uid')
            )
            ->where(
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('p.sys_language_uid', $queryBuilder->createNamedParameter($languageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sfr.tablenames', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('p.deleted', 0),
                $queryBuilder->expr()->eq('sfr.deleted', 0)
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPageRecord(int $pageId, int $languageId): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;
        $query = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, ParameterType::INTEGER))
            )
        ;

        $result = $query->executeQuery()->fetchAssociative();

        return $result ?: null;
    }

    /**
     * Fetch pages for translation with filter options and additional statistics.
     *
     * @param list<int>            $foundPageUids
     * @param array<string, mixed> $workflowData
     *
     * @return list<array<string, mixed>>
     */
    public function fetchPagesForTranslation(array $foundPageUids, int $sourceLanguageUid, int $targetLanguageUid, array $workflowData): array
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $context->getPropertyFromAspect('workspace', 'id')))
        ;

        $queryBuilder->select('uid', 'title', 'slug', 'doktype')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($foundPageUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($sourceLanguageUid, Connection::PARAM_INT))
            )
        ;

        // Filter by page type if specified
        if (isset($workflowData['pageType']) && (int) $workflowData['pageType'] > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter($workflowData['pageType'], Connection::PARAM_INT))
            );
        }

        $sourcePages = $queryBuilder->executeQuery()->fetchAllAssociative();

        // Filter out pages that already have translations in target language and enhance with statistics
        $pages = [];
        foreach ($sourcePages as $page) {
            $pageUid = (int) $page['uid'];
            $page['isAlreadyTranslated'] = $this->isAlreadyTranslated($pageUid, $targetLanguageUid);
            $page['contentElementsCount'] = $this->countContentElementsOnPage($pageUid, $sourceLanguageUid, $targetLanguageUid);
            $page['pagePropertiesCount'] = $this->countTranslatablePageProperties($pageUid, $sourceLanguageUid);
            $page['fileReferencesCount'] = count($this->getFileReferencesOnPage($pageUid, $sourceLanguageUid));
            if ($page['contentElementsCount'] > 0 || $page['pagePropertiesCount'] > 0 || $page['fileReferencesCount'] > 0) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * Get the UID of an existing page translation.
     *
     * @throws Exception
     */
    public function getTranslatedPageUid(int $sourcePageUid, int $targetLanguageUid): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        $result = $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($sourcePageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($targetLanguageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative()
        ;

        return $result ? (int) $result['uid'] : null;
    }

    /**
     * @throws Exception
     */
    public function countContentElementsOnPage(int $pageUid, int $sourceLanguageUid, int $targetLanguageUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        $sourceElements = $queryBuilder
            ->select('uid', 'l18n_parent')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($sourceLanguageUid, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(-1, Connection::PARAM_INT))
                )
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        if (empty($sourceElements)) {
            return 0;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        $targetElements = $queryBuilder
            ->select('l18n_parent')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($targetLanguageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        $translatedParentUids = array_column($targetElements, 'l18n_parent');

        $untranslatedCount = 0;
        foreach ($sourceElements as $sourceElement) {
            $elementUid = (int) $sourceElement['uid'];
            if (!in_array($elementUid, $translatedParentUids)) {
                ++$untranslatedCount;
            }
        }

        return $untranslatedCount;
    }

    /**
     * Count translatable page properties for a page.
     *
     * @throws Exception
     */
    public function countTranslatablePageProperties(int $pageUid, int $languageUid): int
    {
        $translatableFields = [
            'title',
            'subtitle',
            'seo_title',
            'description',
            'keywords',
            'og_title',
            'og_description',
            'twitter_title',
            'twitter_description',
        ];

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        $pageRecord = $queryBuilder
            ->select(...$translatableFields)
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative()
        ;

        if (!$pageRecord) {
            return 0;
        }

        $count = 0;
        foreach ($translatableFields as $field) {
            if (!empty($pageRecord[$field])) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Get translated page UID - alias for getTranslatedPageUid for consistency.
     *
     * @throws Exception
     */
    public function getPageTranslationUid(int $sourcePageUid, int $targetLanguageUid): ?int
    {
        return $this->getTranslatedPageUid($sourcePageUid, $targetLanguageUid);
    }

    /**
     * @param list<int|string> $pids
     *
     * @return list<array<string, mixed>>
     */
    public function getPageTitlesForPages(array $pids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        return $queryBuilder
            ->select('uid', 'title')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($pids, Connection::PARAM_INT_ARRAY))
            )
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * Get direct child pages of a parent, default language only.
     *
     * @return list<array<string, mixed>>
     */
    public function getChildPageRows(int $parentId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        return $queryBuilder
            ->select('uid', 'title', 'slug', 'doktype', 'hidden')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', 0),
            )
            ->orderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;
    }

    /**
     * Get the localized title of a page.
     */
    public function getLocalizedTitle(int $pageId, int $languageUid): ?string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        $result = $queryBuilder
            ->select('title')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative()
        ;

        return $result ? (string) $result['title'] : null;
    }

    /**
     * Collect all page UIDs within a subtree (including the root itself).
     *
     * @return list<int>
     */
    public function getSubtreePageIds(int $rootPageId, int $maxDepth = 20): array
    {
        $allIds = [$rootPageId];
        $currentLevel = [$rootPageId];

        for ($depth = 0; $depth < $maxDepth; ++$depth) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
            $queryBuilder->getRestrictions()->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ;

            $childIds = $queryBuilder->select('uid')
                ->from($this->table)
                ->where($queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($currentLevel, Connection::PARAM_INT_ARRAY)))
                ->executeQuery()
                ->fetchFirstColumn()
            ;

            if (empty($childIds)) {
                break;
            }

            $childIds = array_map('intval', $childIds);
            $allIds = array_merge($allIds, $childIds);
            $currentLevel = $childIds;
        }

        return $allIds;
    }

    /**
     * Full-text search across page fields.
     *
     * @return list<array<string, mixed>>
     */
    public function searchByText(string $query, int $maxResults = 100): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;
        $searchTerm = '%'.$queryBuilder->escapeLikeWildcards($query).'%';

        return $queryBuilder->select('uid', 'title', 'slug', 'seo_title', 'description')
            ->from($this->table)
            ->where($queryBuilder->expr()->or(
                $queryBuilder->expr()->like('title', $queryBuilder->createNamedParameter($searchTerm)),
                $queryBuilder->expr()->like('seo_title', $queryBuilder->createNamedParameter($searchTerm)),
                $queryBuilder->expr()->like('description', $queryBuilder->createNamedParameter($searchTerm)),
            ))
            ->setMaxResults($maxResults)
            ->executeQuery()->fetchAllAssociative()
        ;
    }

    /**
     * Find stale pages where neither the page nor its content was modified since cutoff.
     *
     * @param null|list<int> $restrictToPageIds
     *
     * @return list<array<string, mixed>>
     */
    public function findStalePages(int $cutoff, ?array $restrictToPageIds, int $limit, int $offset): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        $contentSubquery = 'SELECT MAX(c.tstamp) FROM tt_content c WHERE c.pid = p.uid AND c.deleted = 0';

        $queryBuilder->select('p.uid', 'p.title', 'p.slug', 'p.tstamp AS page_tstamp')
            ->addSelectLiteral(
                sprintf('GREATEST(p.tstamp, COALESCE((%s), 0)) AS last_activity', $contentSubquery),
            )
            ->from($this->table, 'p')
            ->where($queryBuilder->expr()->eq('p.sys_language_uid', 0))
        ;

        if (null !== $restrictToPageIds && !empty($restrictToPageIds)) {
            $queryBuilder->andWhere($queryBuilder->expr()->in('p.uid', $queryBuilder->createNamedParameter($restrictToPageIds, Connection::PARAM_INT_ARRAY)));
        }

        return $queryBuilder->having(sprintf('last_activity < %d', $cutoff))
            ->orderBy('last_activity', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()->fetchAllAssociative()
        ;
    }

    /**
     * Check if a page is already translated in the target language.
     *
     * @throws Exception
     */
    protected function isAlreadyTranslated(int $pageUid, int $targetLanguageUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        $translationCount = $queryBuilder
            ->count('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($targetLanguageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne()
        ;

        return (int) $translationCount > 0;
    }
}
