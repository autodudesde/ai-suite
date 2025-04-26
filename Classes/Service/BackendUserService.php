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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendUserService implements SingletonInterface
{
    protected array $ignorePageTypes = [3, 4, 6, 7, 199, 254, 255];

    protected TranslationService $translationService;
    protected PageTreeRepository $pageTreeRepository;

    protected PagesRepository $pagesRepository;

    protected ConnectionPool $connectionPool;
    public function __construct(
        TranslationService $translationService,
        PageTreeRepository $pageTreeRepository,
        PagesRepository $pagesRepository,
        ConnectionPool $connectionPool
    ) {
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
                if (array_key_exists($inputKey, $group)) {
                    return $group[$inputKey];
                }
            }
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public function getSearchableWebmounts(int $id, int $depth = 99): array
    {
        if (!$this->getBackendUser()->isAdmin() && $id === 0) {
            $mountPoints = array_map('intval', $this->getBackendUser()->getWebmounts());
            $mountPoints = array_unique($mountPoints);
        } else {
            $mountPoints = [$id];
        }
        $expressionBuilder = $this->connectionPool->getQueryBuilderForTable('pages')->expr();
        $permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        // This will hide records from display - it has nothing to do with user rights!!
        $pidList = GeneralUtility::intExplode(',', (string)($this->getBackendUser()->getTSConfig()['options.']['hideRecords.']['pages'] ?? '1'), true);
        if (!empty($pidList)) {
            $permsClause .= ' AND ' . $expressionBuilder->notIn('pages.uid', $pidList);
        }

        $idList = $mountPoints;
        $this->pageTreeRepository->setAdditionalWhereClause($permsClause);
        $pages = $this->pageTreeRepository->getFlattenedPages($mountPoints, $depth);
        foreach ($pages as $page) {
            $idList[] = (int)$page['uid'];
        }
        return array_unique($idList);
    }

    public function getAccessablePageTypes(): array
    {
        $pageTypes = $GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'] ?? [];
        $availablePageTypes = [
            -1 => $this->translationService->translate('tx_aisuite.module.preparePages.allPageTypes'),
        ];
        if (is_array($pageTypes) && $pageTypes !== []) {
            foreach ($pageTypes as $pageType) {
                if (is_array($pageType) && isset($pageType['value']) && $pageType['value'] !== '--div--' &&
                    $this->getBackendUser()->check('pagetypes_select', $pageType['value']) &&
                    !in_array($pageType['value'], $this->ignorePageTypes)
                ) {
                    $availablePageTypes[$pageType['value']] = $this->translationService->translate($pageType['label']);
                }
            }
        }
        return $availablePageTypes;
    }

    public function fetchAccessablePages(): array
    {
        $foundPages = $this->pagesRepository->findAvailablePages();
        return $this->getPagesInWebMountAndWithEditAccess($foundPages);
    }

    public function getPagesInWebMountAndWithEditAccess(array $foundPages): array
    {
        foreach ($foundPages as $page) {
            $pageInWebMount = $this->getBackendUser()->isInWebMount($page['uid']);
            $pageEditAccess = BackendUtility::readPageAccess($page['uid'], $this->getBackendUser()->getPagePermsClause(2));
            if ($pageInWebMount !== null && is_array($pageEditAccess)) {
                $pagesSelect[$page['uid']] = $page['title'];
            }
        }
        return $pagesSelect ?? [];
    }

    public function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
