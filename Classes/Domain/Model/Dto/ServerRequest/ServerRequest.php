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

namespace AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\Exception;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ServerRequest
{
    protected readonly string $endpoint;

    /**
     * @param array<string, mixed>  $additionalFormData
     * @param array<string, mixed>  $extConf
     * @param array<string, string> $models
     */
    public function __construct(
        protected readonly array $extConf,
        string $endpoint,
        protected readonly array $additionalFormData = [],
        protected readonly string $prompt = '',
        protected readonly string $language = '',
        protected readonly array $models = [],
    ) {
        $this->endpoint = $this->extConf['aiSuiteServer'].'api/'.$endpoint;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDataForRequest(): array
    {
        return [
            'headers' => [
                'Authorization' => 'Bearer '.($this->extConf['aiSuiteApiKey'] ?? ''),
            ],
            'form_params' => array_merge($this->getGeneralFormData(), $this->additionalFormData),
        ];
    }

    /**
     * @return array<string, mixed>
     *
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
            'ext_version' => ExtensionManagementUtility::getExtensionVersion('ai_suite'),
        ];
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
}
