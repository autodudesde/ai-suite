<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

class WorkflowSelectionGuard implements SingletonInterface
{
    public function __construct(
        private readonly BackendUserService $backendUserService,
    ) {}

    public function isWritableMetadataTarget(string $table, string $column): bool
    {
        return match ($table) {
            'pages' => in_array($column, MetadataService::PAGE_METADATA_COLUMNS, true),
            'sys_file_reference', 'sys_file_metadata' => in_array($column, MetadataService::FILE_METADATA_COLUMNS, true),
            default => false,
        };
    }

    public function mayUsePageMetadataColumn(string $column): bool
    {
        return $this->mayUseColumn('pages', $column, MetadataService::PAGE_METADATA_COLUMNS);
    }

    public function mayUseFileMetadataColumn(string $column, bool $allowAll = false): bool
    {
        return ($allowAll && 'all' === $column) || $this->mayUseColumn('sys_file_reference', $column, MetadataService::FILE_METADATA_COLUMNS);
    }

    /**
     * @param array<int|string> $pageUids
     */
    public function mayEditPages(array $pageUids): bool
    {
        $uids = array_values(array_unique(array_map('intval', $pageUids)));
        if ([] === $uids || min($uids) <= 0) {
            return false;
        }

        return count($this->backendUserService->filterPageUidsByEditAccess($uids)) === count($uids);
    }

    /**
     * @param array<int|string> $uids
     */
    public function mayEditRecords(string $table, array $uids): bool
    {
        if ([] === $uids) {
            return false;
        }
        foreach ($uids as $uid) {
            if (!$this->backendUserService->canEditRecord($table, (int) $uid)) {
                return false;
            }
        }

        return true;
    }

    public function mayTranslateInto(int $languageUid): bool
    {
        return $languageUid > 0 && ($this->backendUserService->getBackendUser()?->checkLanguageAccess($languageUid) ?? false);
    }

    /**
     * @param array<string, mixed> $backgroundTask
     */
    public function mayManageTask(array $backgroundTask): bool
    {
        $table = (string) ($backgroundTask['table_name'] ?? '');
        $uid = (int) ($backgroundTask['table_uid'] ?? 0);
        if ('pages' !== $table) {
            return $this->backendUserService->canEditRecord($table, $uid);
        }

        $backendUser = $this->backendUserService->getBackendUser();

        return null !== $backendUser
            && $uid > 0
            && is_array(BackendUtility::readPageAccess($uid, $backendUser->getPagePermsClause(Permission::PAGE_SHOW)));
    }

    /**
     * @param array<int|string, mixed> $fileReferences
     *
     * @return null|array<int, int>
     */
    public function fileReferenceTargets(array $fileReferences): ?array
    {
        if (!$this->mayEditRecords('sys_file_reference', array_keys($fileReferences))) {
            return null;
        }

        $targets = [];
        foreach (array_keys($fileReferences) as $referenceUid) {
            $fileUid = (int) (BackendUtility::getRecord('sys_file_reference', (int) $referenceUid)['uid_local'] ?? 0);
            if ($fileUid <= 0) {
                return null;
            }
            $targets[(int) $referenceUid] = $fileUid;
        }

        return $targets;
    }

    /**
     * @param list<string> $columns
     */
    private function mayUseColumn(string $table, string $column, array $columns): bool
    {
        $backendUser = $this->backendUserService->getBackendUser();

        return in_array($column, $columns, true)
            && null !== $backendUser
            && ($backendUser->isAdmin() || $backendUser->check('non_exclude_fields', $table.':'.$column));
    }
}
