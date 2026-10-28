<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\SingletonInterface;

class RoutePermissionService implements SingletonInterface
{
    public const OPTION = 'aiSuitePermission';

    private const OWN_NAMESPACE = 'AutoDudes\AiSuite\\';

    public function __construct(
        private readonly ModuleProvider $moduleProvider,
    ) {}

    public function governs(mixed $target): bool
    {
        return is_string($target) && str_starts_with($target, self::OWN_NAMESPACE);
    }

    /**
     * @param array<string, mixed> $routeOptions
     */
    public function isGranted(array $routeOptions, BackendUserAuthentication $backendUser): bool
    {
        if ($backendUser->isAdmin()) {
            return true;
        }

        $module = $routeOptions['inheritAccessFromModule'] ?? null;
        if (is_string($module) && !$this->moduleProvider->accessGranted($module, $backendUser)) {
            return false;
        }

        $flags = $routeOptions[self::OPTION] ?? null;
        if (!is_array($flags)) {
            return false;
        }
        if ([] === $flags) {
            return true;
        }
        foreach ($flags as $flag) {
            if (is_string($flag) && $backendUser->check('custom_options', 'tx_aisuite_features:'.$flag)) {
                return true;
            }
        }

        return false;
    }
}
