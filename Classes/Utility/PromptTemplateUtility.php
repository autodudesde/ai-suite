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

use AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer\ClientAnswer;
use AutoDudes\AiSuite\Domain\Repository\CustomPromptTemplateRepository;
use AutoDudes\AiSuite\Domain\Repository\ServerPromptTemplateRepository;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PromptTemplateUtility
{
    public static function fetchPromptTemplates(ClientAnswer $answer): bool
    {
        if ($answer->getType() === 'PromptTemplates') {
            $templateList = $answer->getResponseData()['prompt_templates'];
            $serverPromptTemplateRepository = GeneralUtility::makeInstance(ServerPromptTemplateRepository::class);
            $serverPromptTemplateRepository->truncateTable();
            $serverPromptTemplateRepository->insertList($templateList);
            return true;
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public static function getAllPromptTemplates(string $scope = '', string $type = '', int $languageId = 0): array
    {
        $serverPromptTemplateRepository = GeneralUtility::makeInstance(ServerPromptTemplateRepository::class);
        $customPromptTemplateRepository = GeneralUtility::makeInstance(CustomPromptTemplateRepository::class);
        if ($scope !== '') {
            $list = $serverPromptTemplateRepository->findByScopeAndType($scope, $type, $languageId);
            $list = array_merge($list, $customPromptTemplateRepository->findByScopeAndType($scope, $type, $languageId));
        } else {
            $list = $serverPromptTemplateRepository->findAll();
            $list = array_merge($list, $customPromptTemplateRepository->findAll());
        }
        $sortBy = array_column($list, 'name');
        array_multisort($sortBy, SORT_ASC, $list);
        return $list;
    }

    public static function getSearchableWebmounts(int $id, int $depth): array
    {
        $backendUser = self::getBackendUserAuthentication();

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

    public static function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
