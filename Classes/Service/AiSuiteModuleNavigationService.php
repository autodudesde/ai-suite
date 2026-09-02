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

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Core\SingletonInterface;

class AiSuiteModuleNavigationService implements SingletonInterface
{
    /**
     * @var list<array{route: string, labelKey: string, permission: null|string, icon: string}>
     */
    private const ENTRIES = [
        [
            'route' => 'web_aisuite',
            'labelKey' => 'module:aiSuite.module.actionmenu.dashboard',
            'permission' => null,
            'icon' => 'actions-menu',
        ],
        [
            'route' => 'web_aisuite.audit',
            'labelKey' => 'module:aiSuite.module.actionmenu.audit',
            'permission' => 'tx_aisuite_features:enable_audit',
            'icon' => 'actions-search',
        ],
        [
            'route' => 'web_aisuite.workflow',
            'labelKey' => 'module:aiSuite.module.actionmenu.workflow',
            'permission' => 'tx_aisuite_features:enable_massaction_generation',
            'icon' => 'actions-duplicate',
        ],
        [
            'route' => 'web_aisuite.backgroundtask',
            'labelKey' => 'module:aiSuite.module.actionmenu.backgroundTask',
            'permission' => 'tx_aisuite_features:enable_background_task_handling',
            'icon' => 'overlay-scheduled',
        ],
        [
            'route' => 'web_aisuite.global_instructions',
            'labelKey' => 'module:aiSuite.module.actionmenu.globalInstructions',
            'permission' => 'tx_aisuite_features:enable_global_instructions_button',
            'icon' => 'apps-pagetree-page-content-from-page-root',
        ],
        [
            'route' => 'web_aisuite.prompt',
            'labelKey' => 'module:aiSuite.module.actionmenu.promptTemplate',
            'permission' => 'tx_aisuite_features:enable_prompt_template_button',
            'icon' => 'actions-file-text',
        ],
        [
            'route' => 'web_aisuite.page',
            'labelKey' => 'module:aiSuite.module.actionmenu.pages',
            'permission' => 'tx_aisuite_features:enable_pages_generation',
            'icon' => 'actions-file-text',
        ],
        [
            'route' => 'web_aisuite.agencies',
            'labelKey' => 'module:aiSuite.module.actionmenu.agencies',
            'permission' => 'tx_aisuite_features:enable_agency',
            'icon' => 'content-store',
        ],
        [
            'route' => 'web_aisuite.settings',
            'labelKey' => 'module:aiSuite.module.actionmenu.globalSettings',
            'permission' => 'tx_aisuite_features:enable_global_settings',
            'icon' => 'actions-cog',
        ],
        [
            'route' => 'web_aisuite.statistics',
            'labelKey' => 'module:aiSuite.module.actionmenu.statistics',
            'permission' => 'tx_aisuite_features:enable_statistics',
            'icon' => 'content-widget-chart-bar',
        ],
    ];

    public function __construct(
        private readonly BackendUserService $backendUserService,
        private readonly ModuleProvider $moduleProvider,
    ) {}

    /**
     * @return list<array{route: string, labelKey: string, permission: null|string, icon: string}>
     */
    public function getEntries(): array
    {
        return self::ENTRIES;
    }

    /**
     * @return list<array{route: string, labelKey: string, permission: null|string, icon: string}>
     */
    public function getPermittedEntries(): array
    {
        return array_values(array_filter(
            self::ENTRIES,
            fn (array $entry): bool => (null === $entry['permission']
                || $this->backendUserService->checkPermissions($entry['permission']))
                && $this->moduleAccessGranted($entry['route']),
        ));
    }

    private function moduleAccessGranted(string $moduleIdentifier): bool
    {
        $backendUser = $this->backendUserService->getBackendUser();
        if (null === $backendUser) {
            return false;
        }
        if ($backendUser->isAdmin()) {
            return true;
        }

        try {
            // accessGranted kennt nur das Modul, nicht seine Routen
            $moduleName = explode('.', $moduleIdentifier)[0];

            return $this->moduleProvider->accessGranted($moduleName, $backendUser);
        } catch (\Throwable) {
            return false;
        }
    }
}
