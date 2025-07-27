<?php

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;

class BasicAuthService implements SingletonInterface
{
    protected ExtensionConfiguration $extensionConfiguration;
    protected array $authConf = [];

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        $this->extensionConfiguration = $extensionConfiguration;
        $this->authConf = $this->extensionConfiguration->get('ai_suite', 'basicAuth');
    }

    public function getBasicAuth(): string
    {
        if ((bool)$this->authConf['enable']) {
            if(!empty($this->authConf['user']) && !empty($this->authConf['pass'])) {
                return base64_encode($this->authConf['user']. ':' . $this->authConf['pass']);
            }
            throw new \RuntimeException('Basic Auth is enabled, but no user or password is configured.', 1698251234);
        }
        return '';
    }
}
