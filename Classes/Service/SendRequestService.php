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
use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Exception\AiSuiteServerException;
use AutoDudes\AiSuite\Factory\SettingsFactory;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RequestFactory;

class SendRequestService
{
    public const JSON_SAFE_FLAGS = JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE;

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
        'targetLanguageNotSupported' => 'aiSuite.error.server.targetLanguageNotSupported',
        'thirdPartyApi' => 'aiSuite.error.server.thirdPartyApi',
        'webSearchUnavailable' => 'aiSuite.error.server.webSearchUnavailable',
        'promptViolation' => 'aiSuite.error.server.promptViolation',
        'payloadTooLarge' => 'aiSuite.error.server.payloadTooLarge',
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
        protected readonly GlobalInstructionsRepository $globalInstructionsRepository,
        protected readonly SystemDomainResolver $systemDomainResolver,
    ) {
        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
    }

    public function sendRequest(ServerRequest $serverRequest): ClientAnswer
    {
        if (empty($this->extConf['aiSuiteApiKey'])) {
            $this->logger->info('AI Suite request skipped: no API key configured');

            return $this->buildErrorAnswer($this->localizationService->translate('aiSuite.error.apiKeyMissing.message'), 'missingApiKey');
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
                    'body' => substr($responseBody, 0, 512),
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
        $clientAddresses = $this->resolveClientAddresses();
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
                $clientAddresses['ip'],
                $clientAddresses['forwardIp'],
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
    public function sendDataRequest(string $targetEndpoint, array $additionalData = [], string $prompt = '', string $langIsoCode = '', array $models = [], ?string $requestSystemDomain = null): ClientAnswer
    {
        if ([] !== $models) {
            $additionalData['keys'] = $this->modelService->fetchKeysByModel($this->extConf, $models);
        }
        $clientAddresses = $this->resolveClientAddresses();
        $answer = $this->sendRequest(
            new ServerRequest(
                $this->extConf,
                $targetEndpoint,
                $additionalData,
                $prompt,
                $langIsoCode,
                $models,
                $this->systemDomainResolver->resolve($requestSystemDomain),
                $clientAddresses['ip'],
                $clientAddresses['forwardIp'],
            )
        );
        if ('Error' === $answer->getType()) {
            return $answer;
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
        if ('' === $baseUrl) {
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

    /**
     * @return array{ip: string, forwardIp: string}
     */
    private function resolveClientAddresses(): array
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return ['ip' => '', 'forwardIp' => ''];
        }

        $normalizedParams = $request->getAttribute('normalizedParams');

        return [
            'ip' => $normalizedParams instanceof NormalizedParams ? $normalizedParams->getRemoteAddress() : '',
            'forwardIp' => $request->getHeaderLine('X-Forwarded-For'),
        ];
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
