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

namespace AutoDudes\AiSuite\Factory;

use AutoDudes\AiSuite\Domain\Model\Pages;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\PagePermissionAssembler;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageStructureFactory
{
    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly PagesRepository $pagesRepository,
        protected readonly PagePermissionAssembler $pagePermissionAssembler,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function createFromArray(array $data, int $parentPageUid): int
    {
        $newPagesCount = 0;
        if (-1 === $parentPageUid) {
            $parentPageUid = 0;
        }
        foreach ($data as $pageData) {
            $beUserUid = $this->backendUserService->getBackendUser()?->user['uid'] ?? 0;
            $beUserGroup = $this->backendUserService->getBackendUser()?->firstMainGroup ?? 0;
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
                ->setPid($parentPageUid)
            ;
            if (0 === $parentPageUid) {
                $page->setIsSiteroot(1);
                $page->setHidden(1);
            }
            $newUid = $this->pagesRepository->addPage($page);
            $this->createSlug($newUid);
            ++$newPagesCount;
            if (isset($pageData['children']) && is_array($pageData['children'])) {
                $newPagesCount += $this->createFromArray($pageData['children'], (int) $newUid);
            }
        }

        return $newPagesCount;
    }

    protected function createSlug(string $uid): void
    {
        $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'];
        $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, 'pages', 'slug', $fieldConfig);
        $originalRecord = BackendUtility::getRecord('pages', $uid);
        if (null === $originalRecord) {
            return;
        }
        $slug = $slugHelper->generate($originalRecord, $originalRecord['pid']);
        $this->pagesRepository->updateQuery('uid', $uid, 'slug', $slug);
    }
}
