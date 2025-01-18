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

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;

class FileListService implements SingletonInterface
{
    private const FILELIST_SESSION_ID = 'ai_suite_filelist_id';
    private const WEB_SESSION_ID = 'ai_suite_web_id';

    protected BackendUserService $backendUserService;

    public function __construct(BackendUserService $backendUserService)
    {
        $this->backendUserService = $backendUserService;
    }

    public function rememberFileListId(): void
    {
        if (strpos($_SERVER['REQUEST_URI'], 'module/file/') > 0 && array_key_exists('id', $_GET) && !empty($_GET['id'])) {
            if (strpos($_GET['id'], ':') > 0) {
                $this->backendUserService->getBackendUser()->setSessionData(self::FILELIST_SESSION_ID, $_GET['id']);
            }
        }
        if ((strpos($_SERVER['REQUEST_URI'], 'module/web/') > 0 || strpos($_SERVER['REQUEST_URI'], 'module/page/') > 0) && array_key_exists('id', $_GET) && !empty($_GET['id'])) {
            if (is_int($_GET['id'])) {
                $this->backendUserService->getBackendUser()->setSessionData(self::WEB_SESSION_ID, $_GET['id']);
            }
        }
    }

    public function getFileListId(array $params = []): string
    {
        return $params['id'] ?? $params['options']['directory'] ?? $this->backendUserService->getBackendUser()->getSessionData(self::FILELIST_SESSION_ID) ?? '';
    }

    public function getWebId(array $params = []): string
    {
        return $params['id'] ?? $this->backendUserService->getBackendUser()->getSessionData(self::FILELIST_SESSION_ID) ?? '';
    }

}
