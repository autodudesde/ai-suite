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

    public static function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
