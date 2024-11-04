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

namespace AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\Exception;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ServerRequest
{
    protected array $extConf;
    protected string $endpoint;
    protected array $additionalFormData = [];
    protected string $prompt = '';
    protected string $language = '';
    protected array $models = [];

    public function __construct(
        array $extConf,
        string $endpoint,
        array $additionalFormData = [],
        string $prompt = '',
        string $language = '',
        array $models = []
    ) {
        $this->extConf = $extConf;
        $this->endpoint = $this->extConf['aiSuiteServer'] . 'api/' . $endpoint;
        $this->additionalFormData = $additionalFormData;
        $this->prompt = $prompt;
        $this->language = $language;
        $this->models = $models;
    }

    public function getDataForRequest(): array
    {
        return [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->extConf['aiSuiteApiKey']
            ],
            'form_params' => array_merge($this->getGeneralFormData(), $this->additionalFormData)
        ];
    }

    /**
     * @throws Exception
     */
    public function getGeneralFormData(): array
    {
        return [
            'prompt' => $this->prompt,
            'language' => $this->language,
            'models' => json_encode($this->models),
            'request_system_domain' => GeneralUtility::getIndpEnv('HTTP_HOST'),
            'request_system_ip' => GeneralUtility::getIndpEnv('REMOTE_ADDR'),
            'request_system_forward_ip' => GeneralUtility::getIndpEnv('HTTP_X_FORWARDED_FOR'),
            'typo3_version' => GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion(),
            'ext_version' => ExtensionManagementUtility::getExtensionVersion('ai_suite')
        ];
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
}
