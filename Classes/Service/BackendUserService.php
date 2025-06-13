<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendUserService implements SingletonInterface
{
    protected array $ignorePageTypes = [3, 4, 6, 7, 199, 254, 255];

    protected SiteService $siteService;

    protected TranslationService $translationService;
    protected PageTreeRepository $pageTreeRepository;

    protected PagesRepository $pagesRepository;

    protected ConnectionPool $connectionPool;
    public function __construct(
        SiteService $siteService,
        TranslationService $translationService,
        PageTreeRepository $pageTreeRepository,
        PagesRepository $pagesRepository,
        ConnectionPool $connectionPool
    ) {
        $this->siteService = $siteService;
        $this->translationService = $translationService;
        $this->pageTreeRepository = $pageTreeRepository;
        $this->pagesRepository = $pagesRepository;
        $this->connectionPool = $connectionPool;
    }

    public function checkPermissions(string $permissionsKey): bool
    {
        try {
            $backendUser = $this->getBackendUser();
            return $backendUser->check('custom_options', $permissionsKey);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function checkGroupSpecificInputs(string $inputKey): string|int
    {
        try {
            foreach ($this->getBackendUser()->userGroups as $group) {
                if (isset($group[$inputKey]) && $group[$inputKey] !== null && $group[$inputKey] !== '') {
                    return $group[$inputKey];
                }
            }
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public function getSearchableWebmounts(int $id, int $depth): array
    {
        $backendUser = $this->getBackendUser();

        if (!$backendUser->isAdmin() && $id === 0) {
            $mountPoints = array_map('intval', $backendUser->returnWebmounts());
            $mountPoints = array_unique($mountPoints);
        } else {
            $mountPoints = [$id];
        }

        $idList = $mountPoints;
        $pageTree = $this->pageTreeRepository->getTree($id, null, $mountPoints, true);
        $idList[] = $pageTree['uid'];
        if(array_key_exists('_children', $pageTree) && count($pageTree['_children']) > 0) {
            $idList = $this->iterateOverPageTree($pageTree['_children'], $idList);
        }
        $uniqueIdList = array_unique($idList);
        asort($uniqueIdList);
        return $uniqueIdList;
    }

    public function iterateOverPageTree(array $pageTree, array &$pageIds): array
    {
        foreach ($pageTree as $page) {
            if(!in_array($page['uid'], $pageIds)) {
                $pageIds[] = $page['uid'];
            }
            if (array_key_exists('_children', $page) && count($page['_children']) > 0) {
                $this->iterateOverPageTree($page['_children'], $pageIds);
            }
        }
        return $pageIds;
    }

    public function getAccessablePageTypes(): array
    {
        $pageTypes = $GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'] ?? [];
        $availablePageTypes = [
            -1 => $this->translationService->translate('tx_aisuite.module.preparePages.allPageTypes'),
        ];
        if (is_array($pageTypes) && $pageTypes !== []) {
            foreach ($pageTypes as $pageType) {
                if (is_array($pageType) && isset($pageType['1']) && $pageType['1'] !== '--div--' &&
                    $this->getBackendUser()->check('pagetypes_select', $pageType['1']) &&
                    (!in_array($pageType['1'], $this->ignorePageTypes))
                ) {
                    $availablePageTypes[$pageType['1']] = $this->translationService->translate($pageType['0']);
                }
            }
        }
        return $availablePageTypes;
    }

    public function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    public function getTreeLevels(int $startPageId, int $depth): array
    {
        $pageTree = $this->pageTreeRepository->getTree($startPageId, null, [], true);
        $pageTreeLevels = $this->pageTreeRepository->getTreeLevels($pageTree, $depth);
        $idList[] = $pageTreeLevels['uid'];
        if(array_key_exists('_children', $pageTreeLevels) && count($pageTreeLevels['_children']) > 0) {
            $idList = $this->iterateOverPageTree($pageTreeLevels['_children'], $idList);
        }
        $uniqueIdList = array_unique($idList);
        asort($uniqueIdList);
        return $uniqueIdList;
    }

    public function getPagesByPidAndDepth(int $pid, int $depth): array
    {
        $pageTree = GeneralUtility::makeInstance(PageTreeView::class);
        $permissions = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageTree->init('AND ' . $permissions);
        $rootPageId = $this->siteService->getSiteRootPageId($pid);
        $pageRecordOfRootPid = BackendUtility::getRecord('pages', $rootPageId);
        $pageTree->tree[] = ['row' => ['uid' => $rootPageId, 'title' => $pageRecordOfRootPid['title'] ?? '']];
        $pageTree->getTree($rootPageId, $depth);

        $result = [];
        foreach ($pageTree->tree as $page) {
            if($page['row']['uid'] > 0) {
                $result[$page['row']['uid']] = $page['row']['title'] ?? '';
            }
        }
        ksort($result, SORT_NUMERIC);
        return $result;
    }
}
