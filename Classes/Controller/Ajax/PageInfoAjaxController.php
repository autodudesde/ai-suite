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

namespace AutoDudes\AiSuite\Controller\Ajax;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

#[AsController]
class PageInfoAjaxController
{
    public function infoAction(ServerRequestInterface $request): ResponseInterface
    {
        $pageId = (int) (((array) $request->getParsedBody())['pageId'] ?? 0);
        if ($pageId <= 0) {
            return new JsonResponse(['success' => false], 400);
        }

        $page = BackendUtility::readPageAccess(
            $pageId,
            $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW),
        );
        if (false === $page) {
            return new JsonResponse(['success' => false], 403);
        }

        return new JsonResponse([
            'success' => true,
            'output' => [
                'uid' => $pageId,
                'title' => sprintf('%s [%d]', (string) ($page['title'] ?? ''), $pageId),
            ],
        ]);
    }
}
