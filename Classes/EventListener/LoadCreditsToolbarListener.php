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

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackendUserService;
use TYPO3\CMS\Backend\Controller\Event\AfterBackendPageRenderEvent;
use TYPO3\CMS\Core\Page\PageRenderer;

final class LoadCreditsToolbarListener
{
    public function __construct(
        private readonly BackendUserService $backendUserService,
        private readonly PageRenderer $pageRenderer,
    ) {}

    public function __invoke(AfterBackendPageRenderEvent $event): void
    {
        if (!$this->backendUserService->checkPermissions('tx_aisuite_features:enable_toolbar_stats_item')) {
            return;
        }

        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/backend/credits-toolbar.js');
    }
}
