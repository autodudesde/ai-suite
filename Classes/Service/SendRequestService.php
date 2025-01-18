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
use TYPO3\CMS\Core\SingletonInterface;

class SendRequestService implements SingletonInterface
{
    protected RequestFactory $requestFactory;
    protected RequestsRepository $requestsRepository;
    protected SettingsFactory $settingsFactory;
    protected ModelService $modelService;
    protected TranslationService $translationService;
    protected LoggerInterface $logger;

    protected array $extConf;

    public function __construct(
        RequestFactory $requestFactory,
        RequestsRepository $requestsRepository,
        SettingsFactory $settingsFactory,
        ModelService $modelService,
        TranslationService $translationService,
        LoggerInterface $logger
    ) {
        $this->requestFactory = $requestFactory;
        $this->requestsRepository = $requestsRepository;
        $this->settingsFactory = $settingsFactory;
        $this->modelService = $modelService;
        $this->translationService = $translationService;
        $this->logger = $logger;

        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
    }

    public function sendRequest(ServerRequest $serverRequest): ClientAnswer
    {
        try {
            $request = $this->requestFactory->request(
                $serverRequest->getEndpoint(),
                'POST',
                $serverRequest->getDataForRequest()
            );
            $requestContent = json_decode($request->getBody()->getContents(), true);
            if ($requestContent === null) {
                throw new AiSuiteServerException('Could not fetch a valid response from request', 500);
            }
            return new ClientAnswer($requestContent, $requestContent['type']);
        } catch (ClientException|ServerException $exception) {
            $this->logger->error($exception->getMessage());
            return $this->buildErrorAnswer('AI Suite server endpoint is currently not available.');
        } catch (AiSuiteServerException $exception) {
            $this->logger->error($exception->getMessage());
            return $this->buildErrorAnswer($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return $this->buildErrorAnswer('Unexpected AI Suite server endpoint error');
        }
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

    public function sendLibrariesRequest(string $libraryTypes, string $targetEndpoint, array $keyModelTypes): ClientAnswer
    {
        $librariesAnswer = $this->sendRequest(
            new ServerRequest(
                $this->extConf,
                'generationLibraries',
                [
                    'library_types' => $libraryTypes,
                    'target_endpoint' => $targetEndpoint,
                    'keys' => $this->modelService->fetchKeysByModelType($this->extConf, $keyModelTypes)
                ]
            )
        );

        if ($librariesAnswer->getType() === 'Error') {
            $this->logger->error($this->translationService->translate('aiSuite.module.errorFetchingLibraries.title'));
            return $this->buildErrorAnswer('<div class="alert alert-danger" role="alert">' . $this->translationService->translate('aiSuite.module.errorFetchingLibraries.title') . '</div>');
        }
        return $librariesAnswer;
    }

    public function sendDataRequest(string $targetEndpoint, array $additionalData = [],  string $prompt = '', string $langIsoCode = '', array $models = []): ClientAnswer {
        if(count($models) > 0) {
            $modelTypes = [];
            foreach ($models as $model) {
                $modelTypes[] = $model;
            }
            $additionalData['keys'] = $this->modelService->fetchKeysByModel($this->extConf, $modelTypes);
        }
        $answer = $this->sendRequest(
            new ServerRequest($this->extConf, $targetEndpoint, $additionalData, $prompt, $langIsoCode, $models)
        );
        if ($answer->getType() === 'Error') {
            return $answer;
        }
        if (array_key_exists('free_requests', $answer->getResponseData()) &&
            array_key_exists('free_requests', $answer->getResponseData()) &&
            array_key_exists('abo_requests', $answer->getResponseData())
        ) {
            $this->requestsRepository->setRequests(
                $answer->getResponseData()['free_requests'],
                $answer->getResponseData()['paid_requests'],
                $answer->getResponseData()['abo_requests'],
                $answer->getResponseData()['model_type'] ?? ''
            );
            BackendUtility::setUpdateSignal('updateTopbar');
        }
        return $answer;
    }
}
