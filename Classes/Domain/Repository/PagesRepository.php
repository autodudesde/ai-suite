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

use AutoDudes\AiSuite\Domain\Model\Pages;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PagesRepository extends AbstractRepository
{
    protected array $ignorePageTypes = [3, 4, 6, 7, 199, 254, 255];

    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'pages',
        string $sortBy = 'sorting'
    ) {
        parent::__construct($connectionPool, $table, $sortBy);
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws DBALException
     */
    public function findAvailablePages($uidList = []): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select('*')
            ->from($this->table);
        $constraints = [
            $queryBuilder->expr()->eq('l10n_parent', 0)
        ];
        if(count($uidList) > 0) {
            $constraints[] = $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uidList, Connection::PARAM_INT_ARRAY));
        }
        $queryBuilder->where(...$constraints);
        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    public function addPage(Pages $page): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->insert($this->table)->values($page->toDatabase());
        $queryBuilder->executeStatement();
        return $queryBuilder->getConnection()->lastInsertId();
    }

    /**
     * @throws Exception
     * @throws AspectNotFoundException
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function fetchNecessaryPageData(array $massActionData, array $foundPageUids, string $mode = 'pages'): array {
        $context = GeneralUtility::makeInstance(Context::class);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $context->getPropertyFromAspect('workspace', 'id')));
        $fields = ['uid', 'title', 'slug'];
        if($mode === 'pages') {
            $fields[] = $massActionData['column'] . ' AS columnValue';
        }
        if($mode === 'pages') {
            $queryBuilder->select(...$fields)
                ->from($this->table)
                ->where(
                    $queryBuilder->expr()->notIn('doktype', $queryBuilder->createNamedParameter($this->ignorePageTypes, Connection::PARAM_INT_ARRAY))
                );
        } else {
            $queryBuilder->select(...$fields)
                ->from($this->table);
        }
        $languageParts = explode('__', $massActionData['sysLanguage']);
        if(isset($languageParts[1]) && (int)$languageParts[1] > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('l10n_parent', $queryBuilder->createNamedParameter($foundPageUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter((int)$languageParts[1], Connection::PARAM_INT))

            );
        } else {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($foundPageUids, Connection::PARAM_INT_ARRAY)),
            );
        }
        if($mode === 'pages' && (int)$massActionData['pageType'] > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter($massActionData['pageType'], Connection::PARAM_INT))
            );
        }
        if($mode === 'pages' && $massActionData['showOnlyEmpty']) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq($massActionData['column'], $queryBuilder->createNamedParameter('', Connection::PARAM_STR)),
                    $queryBuilder->expr()->isNull($massActionData['column'])
                )
            );
        }
        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     *
     * @todo: PageRepository should not be responsible for fetching tt_content
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
            ->fetchAllAssociative();
    }

    /**
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     *
     * @todo: PageRepository should not be responsible for fetching sys_file_references
     */
    public function fetchSysFileReferences(array $pagesUids, string $column, int $sysLanguageUid, bool $showOnlyEmpty): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->select('sfr.uid', 'sfr.pid', 'sfr.tablenames', 'sfr.fieldname', 'sfr.uid_local', 'sfr.uid_foreign', 'sfr.' . $column . ' AS columnValue', 'sf.name AS fileName', 'sf.mime_type AS fileMimeType', 'sf.size AS size')
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
            );
        if($showOnlyEmpty === true) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('sfr.' . $column),
                    $queryBuilder->expr()->eq('sfr.' . $column, $queryBuilder->createNamedParameter('', Connection::PARAM_STR))
                )
            );
        }
        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function checkPageTranslationExists(int $pageId, int $languageUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $pageTranslationExists = $queryBuilder
            ->count('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

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
            ->fetchAssociative();

        return $result ? (int)$result['uid'] : null;
    }

    public function getFileReferencesOnPage(int $pageId, int $languageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $fileReferences = $queryBuilder
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
            ->fetchAllAssociative();

        return $fileReferences;
    }

    public function getPageRecord(int $pageId, int $languageId): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $query = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, ParameterType::INTEGER))
            );

        $result = $query->executeQuery()->fetchAssociative();

        return $result ?: null;
    }

    /**
     * Fetch pages for translation with filter options and additional statistics
     *
     * @throws Exception
     * @throws AspectNotFoundException
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function fetchPagesForTranslation(array $foundPageUids, int $sourceLanguageUid, int $targetLanguageUid, array $massActionData): array
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $context->getPropertyFromAspect('workspace', 'id')));

        $queryBuilder->select('uid', 'title', 'slug', 'doktype')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($foundPageUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($sourceLanguageUid, Connection::PARAM_INT))
            );

        // Filter by page type if specified
        if (isset($massActionData['pageType']) && (int)$massActionData['pageType'] > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter($massActionData['pageType'], Connection::PARAM_INT))
            );
        }

        $sourcePages = $queryBuilder->executeQuery()->fetchAllAssociative();

        // Filter out pages that already have translations in target language and enhance with statistics
        $pages = [];
        foreach ($sourcePages as $page) {
            $pageUid = (int)$page['uid'];
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
     * Check if a page is already translated in the target language
     *
     * @throws Exception
     */
    protected function isAlreadyTranslated(int $pageUid, int $targetLanguageUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $translationCount = $queryBuilder
            ->count('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($targetLanguageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        return (int)$translationCount > 0;
    }

    /**
     * Get the UID of an existing page translation
     *
     * @throws Exception
     */
    public function getTranslatedPageUid(int $sourcePageUid, int $targetLanguageUid): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $result = $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($sourcePageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($targetLanguageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $result ? (int)$result['uid'] : null;
    }

    /**
     * @throws Exception
     */
    public function countContentElementsOnPage(int $pageUid, int $sourceLanguageUid, int $targetLanguageUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

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
            ->fetchAllAssociative();

        if (empty($sourceElements)) {
            return 0;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $targetElements = $queryBuilder
            ->select('l18n_parent')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($targetLanguageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $translatedParentUids = array_column($targetElements, 'l18n_parent');

        $untranslatedCount = 0;
        foreach ($sourceElements as $sourceElement) {
            $elementUid = (int)$sourceElement['uid'];
            if (!in_array($elementUid, $translatedParentUids)) {
                $untranslatedCount++;
            }
        }

        return $untranslatedCount;
    }

    /**
     * Count translatable page properties for a page
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
            'twitter_description'
        ];

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $pageRecord = $queryBuilder
            ->select(...$translatableFields)
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$pageRecord) {
            return 0;
        }

        $count = 0;
        foreach ($translatableFields as $field) {
            if (!empty($pageRecord[$field])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get translated page UID - alias for getTranslatedPageUid for consistency
     *
     * @throws Exception
     */
    public function getPageTranslationUid(int $sourcePageUid, int $targetLanguageUid): ?int
    {
        return $this->getTranslatedPageUid($sourcePageUid, $targetLanguageUid);
    }
}
