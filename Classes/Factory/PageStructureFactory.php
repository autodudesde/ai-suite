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

namespace AutoDudes\AiSuite\Factory;

use AutoDudes\AiSuite\Domain\Model\Pages;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\PagePermissionAssembler;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageStructureFactory
{
    protected BackendUserService $backendUserService;
    protected PagesRepository $pagesRepository;
    protected PagePermissionAssembler $pagePermissionAssembler;

    public function __construct(
        BackendUserService $backendUserService,
        PagesRepository $pagesRepository,
        PagePermissionAssembler $pagePermissionAssembler
    ) {
        $this->backendUserService = $backendUserService;
        $this->pagesRepository = $pagesRepository;
        $this->pagePermissionAssembler = $pagePermissionAssembler;
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    public function createFromArray(array $data, int $parentPageUid): int
    {
        $newPagesCount = 0;
        if ($parentPageUid === -1) {
            $parentPageUid = 0;
        }
        foreach ($data as $pageData) {
            $beUserUid = $this->backendUserService->getBackendUser()->user['uid'];
            $beUserGroup = $this->backendUserService->getBackendUser()->firstMainGroup;
            $permissions = $this->pagePermissionAssembler->applyDefaults([], $parentPageUid, $beUserUid, $beUserGroup);
            $page = Pages::createEmpty();
            $page
                ->setTitle($pageData['title'])
                ->setSeoTitle($pageData['seoTitle'] ?? '')
                ->setDescription($pageData['seoDescription'] ?? '')
                ->setTstamp(time())
                ->setPermsUserid($permissions['perms_userid'])
                ->setPermsGroupid($permissions['perms_groupid'])
                ->setPermsUser($permissions['perms_user'])
                ->setPermsGroup($permissions['perms_group'])
                ->setPermsEverybody($permissions['perms_everybody'])
                ->setPid($parentPageUid);
            if ($parentPageUid === 0) {
                $page->setIsSiteroot(1);
                $page->setHidden(1);
            }
            $newUid = $this->pagesRepository->addPage($page);
            $this->createSlug($newUid);
            $newPagesCount++;
            if (isset($pageData['children']) && is_array($pageData['children'])) {
                $newPagesCount += $this->createFromArray($pageData['children'], $newUid);
            }
        }

        return $newPagesCount;
    }

    protected function createSlug(int $uid): void
    {
        $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'];
        $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, 'pages', 'slug', $fieldConfig);
        $originalRecord = BackendUtility::getRecord('pages', $uid);
        $slug = $slugHelper->generate($originalRecord, $originalRecord['pid']);
        $this->pagesRepository->updateQuery('uid', $uid, 'slug', $slug);
    }
}
