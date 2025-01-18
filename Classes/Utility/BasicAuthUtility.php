<?php
declare(strict_types=1);

namespace AutoDudes\AiSuite\Utility;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BasicAuthUtility
{
    /**
     * returns the basic auth prefix
     * for example https://myuser:mypassword@myhost
     *
     * see rfc3986 section 3.2.1
     *
     * @return string
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     */
    public static function getBasicAuth(): string
    {
        $auth = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ai_suite', 'basicauth');

        if ((bool)$auth['enable'] && !empty($auth['user']) && !empty($auth['pass'])) {
            return rawurlencode($auth['user'])
                . ':'
                . rawurlencode($auth['pass'])
                . '@';
        }
        return '';
    }
}