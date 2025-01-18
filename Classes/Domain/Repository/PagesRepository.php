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
     */
    public function findAiStructurePages($sortBy = 'sorting'): array
    {
        $this->sortBy = $sortBy;
        return $this->selectQuery('doktype', 1);
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
        if(count($uidList) > 0) {
            $queryBuilder->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uidList, Connection::PARAM_INT_ARRAY))
            );
        }
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
        if((int)$massActionData['sysLanguage'] > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('l10n_parent', $queryBuilder->createNamedParameter($foundPageUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter((int)$massActionData['sysLanguage'], Connection::PARAM_INT))

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
                $queryBuilder->expr()->eq($massActionData['column'], $queryBuilder->createNamedParameter('', Connection::PARAM_STR))
            );
        }
        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function fetchPageData(array $backgroundTasks): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder->select('uid', 'sys_language_uid', 'title', 'slug', 'seo_title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter(array_column($backgroundTasks, 'page_uid'), Connection::PARAM_INT_ARRAY))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
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
                $queryBuilder->expr()->eq('tt_content.CType', $queryBuilder->createNamedParameter('news_newsdetail'))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function fetchSysFileReferences(array $pagesUids, string $column, int $sysLanguageUid, bool $showOnlyEmpty): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->select('sfr.uid', 'sfr.pid', 'sfr.tablenames', 'sfr.fieldname', 'sfr.uid_local', 'sfr.uid_foreign', 'sfr.' . $column . ' AS columnValue', 'sf.name AS fileName')
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
            );
        if($showOnlyEmpty === true) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('sfr.' . $column, $queryBuilder->createNamedParameter('', Connection::PARAM_STR))
            );
        }
        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
