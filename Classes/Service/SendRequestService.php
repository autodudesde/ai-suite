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
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\RequestFactory;

class SendRequestService
{
    public const JSON_SAFE_FLAGS = JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE;

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
    ) {
        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
    }

    public function sendRequest(ServerRequest $serverRequest): ClientAnswer
    {
        try {
            $data = $serverRequest->getDataForRequest();
            $endpoint = $serverRequest->getEndpoint();
            $request = $this->requestFactory->request(
                $endpoint,
                'POST',
                $data
            );
            $requestContent = json_decode($request->getBody()->getContents(), true);
            if (null === $requestContent) {
                throw new AiSuiteServerException('Could not fetch a valid response from request', 500);
            }

            return new ClientAnswer($requestContent, $requestContent['type']);
        } catch (ClientException|ServerException $exception) {
            $this->logger->error($exception->getMessage());

            return $this->buildErrorAnswer($this->localizationService->translate('aiSuite.error.server.notAvailable'));
        } catch (AiSuiteServerException $exception) {
            $this->logger->error($exception->getMessage());

            return $this->buildErrorAnswer($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());

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
                ]
            )
        );

        if ('Error' === $librariesAnswer->getType()) {
            if (!empty($this->extConf['aiSuiteApiKey'])) {
                $this->logger->error($this->localizationService->translate('module:aiSuite.module.errorFetchingLibraries.title'));
            }

            return $this->buildErrorAnswer('<div class="alert alert-danger" role="alert">'.$this->localizationService->translate('module:aiSuite.module.errorFetchingLibraries.title').'</div>');
        }

        return $librariesAnswer;
    }

    /**
     * @param array<string, mixed>  $additionalData
     * @param array<string, string> $models
     */
    public function sendDataRequest(string $targetEndpoint, array $additionalData = [], string $prompt = '', string $langIsoCode = '', array $models = []): ClientAnswer
    {
        if ([] !== $models) {
            $additionalData['keys'] = $this->modelService->fetchKeysByModel($this->extConf, $models);
        }
        $answer = $this->sendRequest(
            new ServerRequest($this->extConf, $targetEndpoint, $additionalData, $prompt, $langIsoCode, $models)
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

    private function buildErrorAnswer(string $message): ClientAnswer
    {
        return new ClientAnswer(
            [
                'body' => [
                    'message' => $message,
                ],
                'type' => 'Error',
            ],
            'Error'
        );
    }
}
