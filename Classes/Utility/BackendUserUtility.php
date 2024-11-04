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

    public static function checkGroupSpecificInputs(string $inputKey): string
    {
        try {
            $backendUser = self::getBackendUser();
            foreach ($backendUser->userGroups as $group) {
                if (array_key_exists($inputKey, $group)) {
                    return (string)$group[$inputKey];
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

        $idList = $mountPoints;
        $repository = GeneralUtility::makeInstance(PageTreeRepository::class);
        $pageTree = $repository->getTree($id, null, $mountPoints, true);
        $idList[] = $pageTree['uid'];
        if(array_key_exists('_children', $pageTree) && count($pageTree['_children']) > 0) {
            $idList = self::iterateOverPageTree($pageTree['_children'], $idList);
        }
        $uniqueIdList = array_unique($idList);
        asort($uniqueIdList);
        return $uniqueIdList;
    }

    public static function iterateOverPageTree(array $pageTree, array &$pageIds): array
    {
        foreach ($pageTree as $page) {
            if(!in_array($page['uid'], $pageIds)) {
                $pageIds[] = $page['uid'];
            }
            if (array_key_exists('_children', $page) && count($page['_children']) > 0) {
                self::iterateOverPageTree($page['_children'], $pageIds);
            }
        }
        return $pageIds;
    }

    public static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
