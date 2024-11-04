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

namespace AutoDudes\AiSuite\Utility;

use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendUserUtility
{
    public static function checkPermissions(string $permissionsKey): bool
    {
        try {
            $backendUser = self::getBackendUser();
            return $backendUser->check('custom_options', $permissionsKey);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function isAdmin(): bool
    {
        $backendUser = self::getBackendUser();
        return $backendUser->isAdmin();
    }

    public static function checkGroupSpecificInputs(string $inputKey): string|int
    {
        try {
            $backendUser = self::getBackendUser();
            foreach ($backendUser->userGroups as $group) {
                if (array_key_exists($inputKey, $group)) {
                    return $group[$inputKey];
                }
            }
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public static function getSearchableWebmounts(int $id, int $depth): array
    {
        $backendUser = BackendUserUtility::getBackendUser();

        if (!$backendUser->isAdmin() && $id === 0) {
            $mountPoints = array_map('intval', $backendUser->returnWebmounts());
            $mountPoints = array_unique($mountPoints);
        } else {
            $mountPoints = [$id];
        }
        $expressionBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages')
            ->expr();
        $permsClause = $backendUser->getPagePermsClause(Permission::PAGE_SHOW);
        // This will hide records from display - it has nothing to do with user rights!!
        $pidList = GeneralUtility::intExplode(',', (string)($backendUser->getTSConfig()['options.']['hideRecords.']['pages'] ?? '1'), true);
        if (!empty($pidList)) {
            $permsClause .= ' AND ' . $expressionBuilder->notIn('pages.uid', $pidList);
        }

        $idList = $mountPoints;
        $repository = GeneralUtility::makeInstance(PageTreeRepository::class);
        $repository->setAdditionalWhereClause($permsClause);
        $pages = $repository->getFlattenedPages($mountPoints, $depth);
        foreach ($pages as $page) {
            $idList[] = (int)$page['uid'];
        }
        return array_unique($idList);
    }

    public static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
