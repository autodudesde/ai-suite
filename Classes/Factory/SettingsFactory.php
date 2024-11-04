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

use AutoDudes\AiSuite\Utility\BackendUserUtility;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SettingsFactory
{
    protected ExtensionConfiguration $extensionConfiguration;
    protected LoggerInterface $logger;

    protected array $extConf;

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct()
    {
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->extConf = $this->extensionConfiguration->get('ai_suite');
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    public function mergeExtConfAndUserGroupSettings(): array
    {
        try {
            if(BackendUserUtility::isAdmin()) {
                return $this->extConf;
            }
            foreach ($this->extConf as $key => $value) {
                $groupKeyValue = BackendUserUtility::checkGroupSpecificInputs($key);
                if($groupKeyValue === '0' || $groupKeyValue === '1') {
                    $this->extConf[$key] = (int)$groupKeyValue;
                } else {
                    $this->extConf[$key] = $groupKeyValue ?: $value;
                }
            }
            return $this->extConf;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return $this->extConf;
        }
    }
}
