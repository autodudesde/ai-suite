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

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendUserService implements SingletonInterface
{
    /** @var list<int> */
    protected array $ignorePageTypes = [3, 4, 6, 7, 199, 254, 255];

    public function __construct(
        protected readonly LocalizationService $localizationService,
        protected readonly PageTreeRepository $pageTreeRepository,
        protected readonly PagesRepository $pagesRepository,
        protected readonly ConnectionPool $connectionPool,
        protected readonly ResourceFactory $resourceFactory,
    ) {}

    public function checkPermissions(string $permissionsKey): bool
    {
        $backendUser = $this->getBackendUser();
        if (null === $backendUser) {
            return false;
        }

        try {
            return $backendUser->check('custom_options', $permissionsKey);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function checkGroupSpecificInputs(string $inputKey): int|string
    {
        $backendUser = $this->getBackendUser();
        if (null === $backendUser) {
            return '';
        }

        foreach ($backendUser->userGroups as $group) {
            if (isset($group[$inputKey]) && '' !== $group[$inputKey]) {
                return $group[$inputKey];
            }
        }

        return '';
    }

    /**
     * @return list<int>
     */
    public function getSearchableWebmounts(int $id, int $depth = 99): array
    {
        $backendUser = $this->getBackendUser();
        if (null === $backendUser) {
            return [];
        }

        if (!$backendUser->isAdmin() && 0 === $id) {
            $mountPoints = array_map('intval', $backendUser->getWebmounts());
            $mountPoints = array_unique($mountPoints);
        } else {
            $mountPoints = [$id];
        }
        $expressionBuilder = $this->connectionPool->getQueryBuilderForTable('pages')->expr();
        $permsClause = $backendUser->getPagePermsClause(Permission::PAGE_SHOW);
        // This will hide records from display - it has nothing to do with user rights!!
        $pidList = GeneralUtility::intExplode(',', (string) ($backendUser->getTSConfig()['options.']['hideRecords.']['pages'] ?? '1'), true);
        if (!empty($pidList)) {
            $permsClause .= ' AND '.$expressionBuilder->notIn('pages.uid', $pidList);
        }

        $idList = $mountPoints;
        $this->pageTreeRepository->setAdditionalWhereClause($permsClause);
        $pages = $this->pageTreeRepository->getFlattenedPages($mountPoints, $depth);
        foreach ($pages as $page) {
            $idList[] = (int) $page['uid'];
        }

        return array_values(array_unique($idList));
    }

    /**
     * @return array<int|string, string>
     */
    public function getAccessablePageTypes(): array
    {
        $pageTypes = $GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'] ?? [];
        $availablePageTypes = [
            -1 => $this->localizationService->translate('module:aiSuite.module.preparePages.allPageTypes'),
        ];
        if (is_array($pageTypes) && [] !== $pageTypes) {
            $backendUser = $this->getBackendUser();
            if (null === $backendUser) {
                return $availablePageTypes;
            }

            foreach ($pageTypes as $pageType) {
                if (is_array($pageType) && isset($pageType['value']) && '--div--' !== $pageType['value']
                    && $backendUser->check('pagetypes_select', $pageType['value'])
                    && !in_array($pageType['value'], $this->ignorePageTypes)
                ) {
                    $availablePageTypes[$pageType['value']] = $this->localizationService->translate($pageType['label']);
                }
            }
        }

        return $availablePageTypes;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchAccessablePages(): array
    {
        $foundPages = $this->pagesRepository->findAvailablePages();

        return $this->getPagesInWebMountAndWithEditAccess($foundPages);
    }

    /**
     * @param list<array<string, mixed>> $foundPages
     *
     * @return array<string, mixed>
     */
    public function getPagesInWebMountAndWithEditAccess(array $foundPages): array
    {
        $backendUser = $this->getBackendUser();
        if (null === $backendUser) {
            return [];
        }

        foreach ($foundPages as $page) {
            $pageInWebMount = $backendUser->isInWebMount($page['uid']);
            $pageEditAccess = BackendUtility::readPageAccess($page['uid'], $backendUser->getPagePermsClause(2));
            if (null !== $pageInWebMount && is_array($pageEditAccess)) {
                $pagesSelect[$page['uid']] = $page['title'];
            }
        }

        return $pagesSelect ?? [];
    }

    /**
     * @param array<string, mixed> $globalInstruction
     */
    public function hasFileAccessPermissions(array $globalInstruction): bool
    {
        $backendUser = $this->getBackendUser();
        if (null === $backendUser) {
            return false;
        }

        if ($backendUser->isAdmin()) {
            return true;
        }

        $selectedDirectories = GeneralUtility::trimExplode(',', $globalInstruction['selected_directories'] ?? '', true);

        foreach ($selectedDirectories as $selectedDir) {
            if (!$this->hasDirectoryReadAccess($selectedDir)) {
                return false;
            }
        }

        return true;
    }

    public function hasDirectoryReadAccess(string $directoryPath): bool
    {
        $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($directoryPath);

        if (!$folder->getStorage()->isBrowsable()) {
            return false;
        }

        if (!$folder->getStorage()->isWithinFileMountBoundaries($folder)) {
            return false;
        }

        return $folder->checkActionPermission('read');
    }

    public function isPathWithinStorageMountBoundaries(string $currentPath, string $parentPath): bool
    {
        $currentFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($currentPath);
        $parentFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($parentPath);

        if ($currentFolder->getStorage() !== $parentFolder->getStorage()) {
            return false;
        }

        $storage = $currentFolder->getStorage();

        if (!$storage->isWithinFileMountBoundaries($currentFolder)
            || !$storage->isWithinFileMountBoundaries($parentFolder)) {
            return false;
        }

        $currentIdentifier = $currentFolder->getIdentifier();
        $parentIdentifier = $parentFolder->getIdentifier();

        $currentNormalized = rtrim($currentIdentifier, '/').'/';
        $parentNormalized = rtrim($parentIdentifier, '/').'/';

        return str_starts_with($currentNormalized, $parentNormalized);
    }

    public function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
