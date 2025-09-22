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

use AutoDudes\AiSuite\Service\BackendUserService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class SettingsFactory
{
    protected ExtensionConfiguration $extensionConfiguration;
    protected BackendUserService $backendUserService;
    protected LoggerInterface $logger;

    protected array $extConf;

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct(
        ExtensionConfiguration $extensionConfiguration,
        BackendUserService $backendUserService,
        LoggerInterface $logger
    ) {
        $this->extensionConfiguration = $extensionConfiguration;
        $this->backendUserService = $backendUserService;
        $this->logger = $logger;

        $this->extConf = $this->extensionConfiguration->get('ai_suite');
    }

    public function mergeExtConfAndUserGroupSettings(): array
    {
        try {
            if ($this->backendUserService->getBackendUser()->isAdmin()) {
                return $this->extConf;
            }
            foreach ($this->extConf as $key => $value) {
                $this->extConf[$key] = $this->backendUserService->checkGroupSpecificInputs($key) ?: ($value ?? '');
            }
            return $this->extConf;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return $this->extConf;
        }
    }
}
