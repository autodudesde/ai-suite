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

use AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer\ClientAnswer;
use AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest\ServerRequest;
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Exception\AiSuiteServerException;
use AutoDudes\AiSuite\Factory\SettingsFactory;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\RequestFactory;

class SendRequestService
{
    public const JSON_SAFE_FLAGS = JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE;

    public const CONFIGURED_MODEL_SETTINGS = ['metadataAutogenerateModel', 'easyLanguageLibrary', 'auditDefaultTextModel'];

    private const SERVER_CONNECT_TIMEOUT = 10;

    private const SERVER_TIMEOUT = 180;

    /**
     * @var array<string, string>
     */
    private const ERROR_TYPE_MESSAGE_MAP = [
        'missingApiKey' => 'aiSuite.error.apiKeyMissing.message',
        'invalidApiKey' => 'aiSuite.error.server.unauthorized',
        'apiKeyNotFound' => 'aiSuite.error.server.unauthorized',
        'apiKeyExpired' => 'aiSuite.error.server.apiKeyExpired',
        'notEnoughRequests' => 'aiSuite.error.server.notEnoughRequests',
        'requestLimitReached' => 'aiSuite.error.server.requestLimitReached',
        'missingAiModelApiKey' => 'aiSuite.error.server.missingAiModelApiKey',
        'invalidRequest' => 'aiSuite.error.server.invalidRequest',
        'requestRateLimited' => 'aiSuite.error.server.requestRateLimited',
        'gdprModelBlocked' => 'aiSuite.error.server.gdprModelBlocked',
        'modelNotAvailable' => 'aiSuite.error.server.modelNotAvailable',
        'keyIsFreddi' => 'aiSuite.error.server.keyIsFreddi',
        'targetLanguageNotSupported' => 'aiSuite.error.server.targetLanguageNotSupported',
        'thirdPartyApi' => 'aiSuite.error.server.thirdPartyApi',
        'imageRehostFailed' => 'aiSuite.error.server.imageRehostFailed',
        'auditGatewayUnavailable' => 'aiSuite.error.server.auditGatewayUnavailable',
        'webSearchUnavailable' => 'aiSuite.error.server.webSearchUnavailable',
        'promptViolation' => 'aiSuite.error.server.promptViolation',
        'moderationUnavailable' => 'aiSuite.error.server.moderationUnavailable',
        'payloadTooLarge' => 'aiSuite.error.server.payloadTooLarge',
        'requestStatusNotFound' => 'aiSuite.error.server.requestStatusNotFound',
    ];

    /** @var array<string, mixed> */
    protected array $extConf;

    public function __construct(
        protected readonly RequestFactory $requestFactory,
        protected readonly RequestsRepository $requestsRepository,
        protected readonly SettingsFactory $settingsFactory,
        protected readonly ModelService $modelService,
        protected readonly LocalizationService $localizationService,
        protected readonly LoggerInterface $logger,
        protected readonly SystemDomainResolver $systemDomainResolver,
        protected readonly ProvenanceCaptureService $provenanceCapture,
        protected readonly BackendUserService $backendUserService,
    ) {
        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
    }

    public function sendRequest(ServerRequest $serverRequest): ClientAnswer
    {
        if (empty($this->extConf['aiSuiteApiKey'])) {
            $this->logger->warning('AI Suite request skipped: no API key configured');

            return $this->buildErrorAnswer($this->localizationService->translate('aiSuite.error.apiKeyMissing.message'), 'missingApiKey');
        }
        if (!self::isHttpsUrl((string) ($this->extConf['aiSuiteServer'] ?? ''))) {
            $this->logger->warning('AI Suite request skipped: the server URL does not use https');

            return $this->buildErrorAnswer($this->localizationService->translate('aiSuite.error.serverNotHttps'));
        }

        try {
            $data = $serverRequest->getDataForRequest();
            $data['connect_timeout'] = self::SERVER_CONNECT_TIMEOUT;
            $data['timeout'] = self::SERVER_TIMEOUT;
            $data['http_errors'] = false;
            $endpoint = $serverRequest->getEndpoint();
            $request = $this->requestFactory->request(
                $endpoint,
                'POST',
                $data
            );
            $statusCode = $request->getStatusCode();
            $responseBody = $request->getBody()->getContents();

            if ($statusCode >= 400) {
                $this->logger->error('AI Suite Server answered with an HTTP error status', [
                    'endpoint' => $endpoint,
                    'statusCode' => $statusCode,
                    'errorType' => (string) ((json_decode($responseBody, true)['body']['errorType'] ?? null) ?: ''),
                ]);

                if (413 === $statusCode) {
                    return $this->buildErrorAnswer(
                        $this->localizationService->translate('aiSuite.error.server.payloadTooLarge'),
                        'payloadTooLarge'
                    );
                }

                return $this->buildErrorAnswer(
                    $this->localizationService->translate('aiSuite.error.server.httpStatus', [$statusCode])
                );
            }

            $requestContent = json_decode($responseBody, true);
            if (null === $requestContent) {
                throw new AiSuiteServerException('Could not fetch a valid response from request', 500);
            }

            $answer = new ClientAnswer($requestContent, $requestContent['type']);

            if ('Error' === $answer->getType()) {
                $requestContent['body']['message'] = $this->getClientErrorMessage($answer);
                $answer->setResponseData($requestContent);
            }

            return $answer;
        } catch (ClientException|ServerException $exception) {
            $this->logger->error($exception->getMessage(), ['statusCode' => $exception->getResponse()->getStatusCode()]);

            return $this->buildErrorAnswer($this->localizationService->translate('aiSuite.error.server.notAvailable'));
        } catch (AiSuiteServerException $exception) {
            $this->logger->error($exception->getMessage());

            return $this->buildErrorAnswer($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception::class]);

            return $this->buildErrorAnswer($this->localizationService->translate('aiSuite.error.server.unexpected'));
        }
    }

    /**
     * @param list<string> $keyModelTypes
     */
    public function sendLibrariesRequest(string $libraryTypes, string $targetEndpoint, array $keyModelTypes): ClientAnswer
    {
        $librariesAnswer = $this->sendRequest(
            new ServerRequest(
                $this->extConf,
                'generationLibraries',
                [
                    'library_types' => $libraryTypes,
                    'target_endpoint' => $targetEndpoint,
                    'keys' => $this->modelService->fetchKeysByModelType($this->extConf, $keyModelTypes),
                    'force_gdpa' => !empty($this->extConf['forceGdpa']) ? 1 : 0,
                ],
                '',
                '',
                [],
                $this->systemDomainResolver->resolve(),
                $this->systemDomainResolver->instanceIdentity(),
            )
        );

        if ('Error' === $librariesAnswer->getType()) {
            $errorType = $librariesAnswer->getErrorType();

            $message = $this->getClientErrorMessage($librariesAnswer);
            if ('' === $message) {
                $message = $this->localizationService->translate('module:aiSuite.module.errorFetchingLibraries.title');
            }

            $this->logger->error($this->localizationService->translate('module:aiSuite.module.errorFetchingLibraries.title'), [
                'errorType' => $errorType,
                'serverMessage' => $librariesAnswer->getMessage(),
            ]);

            return $this->buildErrorAnswer($message, $errorType);
        }

        return $librariesAnswer;
    }

    /**
     * @param array<string, mixed>  $additionalData
     * @param array<string, string> $models
     */
    public function sendDataRequest(string $targetEndpoint, array $additionalData = [], string $prompt = '', string $langIsoCode = '', array $models = [], ?string $requestSystemDomain = null, string $configuredModelSetting = ''): ClientAnswer
    {
        $refusedModels = $this->refusedModels($models, $configuredModelSetting);
        if ([] !== $refusedModels) {
            $this->logger->warning('Refused a request with models the user may not use', ['models' => $refusedModels]);

            return $this->buildErrorAnswer($this->localizationService->translate('aiSuite.error.modelNotAllowed', [implode(', ', $refusedModels)]));
        }
        if ([] !== $models) {
            $additionalData['keys'] = $this->modelService->fetchKeysByModel($this->extConf, $models);
        }
        $answer = $this->sendRequest(
            new ServerRequest(
                $this->extConf,
                $targetEndpoint,
                $additionalData,
                $prompt,
                $langIsoCode,
                $models,
                $this->systemDomainResolver->resolve($requestSystemDomain),
                $this->systemDomainResolver->instanceIdentity(),
            )
        );
        if ('Error' === $answer->getType()) {
            return $answer;
        }
        $provenance = $answer->getResponseData()['provenance'] ?? null;
        if (is_array($provenance)) {
            $this->provenanceCapture->refineModel((string) ($provenance['model'] ?? ''));
        }
        if (array_key_exists('free_requests', $answer->getResponseData())
            && array_key_exists('paid_requests', $answer->getResponseData())
            && array_key_exists('abo_requests', $answer->getResponseData())
        ) {
            $this->requestsRepository->setRequests(
                $answer->getResponseData()['free_requests'],
                $answer->getResponseData()['paid_requests'],
                $answer->getResponseData()['abo_requests'],
                $answer->getResponseData()['model_type'] ?? '',
                $this->extConf['aiSuiteApiKey']
            );

            try {
                BackendUtility::setUpdateSignal('updateTopbar');
            } catch (\Throwable) {
                // Silently ignore — setUpdateSignal requires a backend session
            }
        }

        return $answer;
    }

    public function isServerReachable(): bool
    {
        $baseUrl = (string) ($this->extConf['aiSuiteServer'] ?? '');
        if (!self::isHttpsUrl($baseUrl)) {
            return false;
        }

        try {
            $response = $this->requestFactory->request($baseUrl, 'HEAD', [
                'timeout' => 2,
                'connect_timeout' => 2,
                'http_errors' => false,
            ]);

            return $response->getStatusCode() < 500;
        } catch (\Throwable $e) {
            $this->logger->info('AI Suite Server reachability probe failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getClientErrorMessage(ClientAnswer $answer): string
    {
        $errorType = $answer->getErrorType();

        if ('' !== $errorType && array_key_exists($errorType, self::ERROR_TYPE_MESSAGE_MAP)) {
            return $this->localizationService->translate(self::ERROR_TYPE_MESSAGE_MAP[$errorType]);
        }

        return trim($answer->getMessage());
    }

    public static function isHttpsUrl(string $url): bool
    {
        return 'https' === strtolower((string) parse_url($url, PHP_URL_SCHEME)) && '' !== (string) parse_url($url, PHP_URL_HOST);
    }

    /**
     * @return array{ip: string, forwardIp: string}
     */
    /**
     * @param array<array-key, mixed> $models
     *
     * @return list<string>
     */
    private function refusedModels(array $models, string $configuredModelSetting): array
    {
        $backendUser = $this->backendUserService->getBackendUser();
        if (null === $backendUser || $backendUser->isAdmin()) {
            return [];
        }

        $configured = in_array($configuredModelSetting, self::CONFIGURED_MODEL_SETTINGS, true)
            ? trim((string) ($this->extConf[$configuredModelSetting] ?? ''))
            : '';

        $refused = [];
        foreach ($models as $model) {
            $model = is_string($model) ? trim($model) : '';
            if ('' === $model || ('' !== $configured && $model === $configured)) {
                continue;
            }
            if (!$this->backendUserService->checkPermissions('tx_aisuite_models:'.$model)) {
                $refused[] = $model;
            }
        }

        return array_values(array_unique($refused));
    }

    private function buildErrorAnswer(string $message, string $errorType = ''): ClientAnswer
    {
        return new ClientAnswer(
            [
                'body' => [
                    'message' => $message,
                    'errorType' => $errorType,
                ],
                'type' => 'Error',
            ],
            'Error'
        );
    }
}
