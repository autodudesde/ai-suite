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

class PageStructureFactory
{
    public function __construct(
        protected readonly PagesRepository $pagesRepository,
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
            if (!is_array($pageData) || '' === trim((string) ($pageData['title'] ?? ''))) {
                continue;
            }
            $page = Pages::createEmpty();
            $page
                ->setTitle($pageData['title'])
                ->setSeoTitle($pageData['seoTitle'] ?? '')
                ->setDescription($pageData['seoDescription'] ?? '')
                ->setPid($parentPageUid)
            ;
            if (0 === $parentPageUid) {
                $page->setIsSiteroot(1);
                $page->setHidden(1);
            }
            $newUid = (int) $this->pagesRepository->addPage($page);
            if ($newUid <= 0) {
                // The DataHandler refused the page. Its children would otherwise be created at the
                // root, which is the one place they must not appear.
                continue;
            }

            ++$newPagesCount;
            if (isset($pageData['children']) && is_array($pageData['children'])) {
                $newPagesCount += $this->createFromArray($pageData['children'], $newUid);
            }
        }

        return $newPagesCount;
    }
}
