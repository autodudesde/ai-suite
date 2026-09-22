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
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderReadPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException;
use TYPO3\CMS\Core\Resource\Exception\NotInMountPointException;
use TYPO3\CMS\Core\Resource\Folder;
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
        protected readonly ResourceFactory $resourceFactory,
        protected readonly LoggerInterface $logger,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
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
            $this->logger->warning('Permission check failed', [
                'permissionsKey' => $permissionsKey,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

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
    public function getSearchableWebmounts(int $id, int $depth = 99, int $workspaceId = 0): array
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
        $permsClause = $backendUser->getPagePermsClause(Permission::PAGE_SHOW);

        $idList = $mountPoints;
        $pageTreeRepository = $workspaceId > 0
            ? GeneralUtility::makeInstance(PageTreeRepository::class, $workspaceId)
            : $this->pageTreeRepository;
        $pageTreeRepository->setAdditionalWhereClause($permsClause);
        $pages = $pageTreeRepository->getFlattenedPages($mountPoints, $depth);
        foreach ($pages as $page) {
            $idList[] = (int) $page['uid'];
        }

        $idList = array_values(array_unique($idList));

        $hidden = GeneralUtility::intExplode(',', (string) ($backendUser->getTSConfig()['options.']['hideRecords.']['pages'] ?? ''), true);

        return [] === $hidden ? $idList : array_values(array_diff($idList, $hidden));
    }

    /**
     * @return array<int|string, string>
     */
    public function getAccessablePageTypes(): array
    {
        $pageTypes = $this->tcaCompatibilityService->getFieldItems('pages', 'doktype');
        $availablePageTypes = [
            -1 => $this->localizationService->translate('module:aiSuite.module.preparePages.allPageTypes'),
        ];
        if ([] !== $pageTypes) {
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
     * @param array<int|string> $pageUids
     *
     * @return list<int>
     */
    public function filterPageUidsByEditAccess(array $pageUids): array
    {
        $backendUser = $this->getBackendUser();
        if (null === $backendUser) {
            return [];
        }
        if ($backendUser->isAdmin()) {
            return array_values(array_map('intval', $pageUids));
        }

        $accessiblePageUids = [];
        foreach ($pageUids as $pageUid) {
            $pageUid = (int) $pageUid;
            $pageInWebMount = $backendUser->isInWebMount($pageUid);
            $pageEditAccess = BackendUtility::readPageAccess($pageUid, $backendUser->getPagePermsClause(2));
            if (null !== $pageInWebMount && is_array($pageEditAccess)) {
                $accessiblePageUids[] = $pageUid;
            }
        }

        return $accessiblePageUids;
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

    /**
     * @throws NotInMountPointException
     * @throws InsufficientFolderReadPermissionsException
     */
    public function getReadableFolder(string $combinedIdentifier): Folder
    {
        $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
        $storage = $folder->getStorage();

        if (!$storage->isBrowsable() || !$storage->isWithinFileMountBoundaries($folder)) {
            throw new NotInMountPointException(
                sprintf('Folder "%s" is outside the backend user\'s filemounts.', $combinedIdentifier),
                1730000001,
            );
        }

        if (!$folder->checkActionPermission('read')) {
            throw new InsufficientFolderReadPermissionsException(
                sprintf('No read permission on folder "%s".', $combinedIdentifier),
                1730000002,
            );
        }

        return $folder;
    }

    /**
     * @throws NotInMountPointException
     * @throws InsufficientFolderWritePermissionsException
     */
    public function getWriteableFolder(string $combinedIdentifier): Folder
    {
        $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
        $storage = $folder->getStorage();

        if (!$storage->isBrowsable() || !$storage->isWithinFileMountBoundaries($folder)) {
            throw new NotInMountPointException(
                sprintf('Folder "%s" is outside the backend user\'s filemounts.', $combinedIdentifier),
                1730000003,
            );
        }

        if (!$folder->checkActionPermission('write')) {
            throw new InsufficientFolderWritePermissionsException(
                sprintf('No write permission on folder "%s".', $combinedIdentifier),
                1730000004,
            );
        }

        return $folder;
    }

    /**
     * Whether this user may edit the record at all.
     *
     * Needed wherever a request names its own table and uid — a form field reporting that its
     * suggestion was accepted, for instance. Without it the endpoint speaks for every record in the
     * installation, and a register that anyone can write into proves nothing.
     */
    public function canEditRecord(string $table, int $uid): bool
    {
        $beUser = $this->getBackendUser();
        if (null === $beUser || '' === $table || $uid <= 0) {
            return false;
        }

        if ($beUser->isAdmin()) {
            return true;
        }

        if (!$beUser->check('tables_modify', $table)) {
            return false;
        }

        $row = BackendUtility::getRecord($table, $uid);
        if (null === $row || !$beUser->recordEditAccessInternals($table, $row)) {
            return false;
        }

        if ('sys_file_metadata' === $table) {
            return $this->canEditFileMetadata((int) ($row['file'] ?? 0));
        }

        if ('sys_file_reference' === $table) {
            return $this->canEditFileReferenceMetadata((int) ($row['uid_local'] ?? 0));
        }

        if ('pages' === $table) {
            return $beUser->doesUserHaveAccess($row, Permission::PAGE_EDIT);
        }

        $page = BackendUtility::getRecord('pages', (int) ($row['pid'] ?? 0));

        return null !== $page && $beUser->doesUserHaveAccess($page, Permission::CONTENT_EDIT);
    }

    public function canEditFileMetadata(int $fileUid): bool
    {
        return $this->canEditFile($fileUid, 'sys_file_metadata', 'editMeta');
    }

    public function canEditFileReferenceMetadata(int $fileUid): bool
    {
        return $this->canEditFile($fileUid, 'sys_file_reference', 'read');
    }

    public function canReadFolder(Folder $folder): bool
    {
        try {
            return $folder->checkActionPermission('read')
                && $folder->getStorage()->isWithinFileMountBoundaries($folder);
        } catch (\Exception $e) {
            $this->logger->warning('Folder read permission check failed', [
                'folder' => $folder->getCombinedIdentifier(),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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

    private function canEditFile(int $fileUid, string $tablesModifyKey, string $fileAction): bool
    {
        $backendUser = $this->getBackendUser();
        if (null === $backendUser) {
            return false;
        }

        if ($backendUser->isAdmin()) {
            return true;
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);

            return $file->isIndexed()
                && $file->checkActionPermission($fileAction)
                && $backendUser->check('tables_modify', $tablesModifyKey);
        } catch (\Exception $e) {
            $this->logger->warning('File edit permission check failed', [
                'fileUid' => $fileUid,
                'fileAction' => $fileAction,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
