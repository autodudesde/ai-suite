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

namespace AutoDudes\AiSuite\Controller\Trait;

use AutoDudes\AiSuite\Domain\Model\Dto\BackgroundTask;
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Service\SendRequestService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait AjaxResponseTrait
{
    protected function logError(string $errorMessage, Response $response, int $statusCode = 400): Response
    {
        $this->logger->error($errorMessage);
        $response = $response->withStatus($statusCode);
        $response->getBody()->write((string) json_encode(['success' => false, 'status' => $statusCode, 'error' => $errorMessage]));

        return $response;
    }

    /**
     * @param array<string, mixed> $output
     */
    protected function jsonSuccess(Response $response, array $output = []): Response
    {
        $data = ['success' => true];
        if ([] !== $output) {
            $data['output'] = $output;
        }
        $response->getBody()->write((string) json_encode($data));

        return $response;
    }

    protected function jsonError(Response $response, string $error, int $statusCode = 400): Response
    {
        $response->getBody()->write((string) json_encode([
            'success' => false,
            'error' => $error,
        ]));

        return $response;
    }

    /**
     * @return array<string, mixed>|Response
     */
    protected function validateParsedBody(
        ServerRequestInterface $request,
        string $key,
        Response $response,
    ): array|Response {
        $parsedBody = $request->getParsedBody();
        if (!is_array($parsedBody) || !array_key_exists($key, $parsedBody)) {
            $this->logger->error('Invalid request: missing key '.$key);
            $response->getBody()->write((string) json_encode([
                'success' => false,
                'error' => $this->aiSuiteContext->localizationService->translate('aiSuite.error.invalidRequest'),
            ]));

            return $response;
        }

        return $parsedBody[$key];
    }

    /**
     * Sends a workflow request to the AI server and inserts background tasks on success.
     *
     * @param list<array<string, mixed>> $payload
     * @param list<BackgroundTask>       $bulkPayload
     * @param array<string, mixed>       $extraParams Extra data params (e.g. glossary, deepl_glossary_id)
     */
    protected function sendWorkflowRequest(
        array $payload,
        array $bulkPayload,
        string $parentUuid,
        string $scope,
        string $type,
        string $languageCode,
        string $modelKey,
        string $model,
        Response $response,
        SendRequestService $requestService,
        BackgroundTaskRepository $backgroundTaskRepository,
        array $extraParams = [],
    ): ?Response {
        if (0 === count($payload)) {
            return null;
        }

        $answer = $requestService->sendDataRequest(
            'createMassAction',
            array_merge([
                'uuid' => $parentUuid,
                'payload' => $payload,
                'scope' => $scope,
                'type' => $type,
            ], $extraParams),
            '',
            $languageCode,
            [$modelKey => $model]
        );

        if ('Error' === $answer->getType()) {
            return $this->logError($answer->getResponseData()['message'], $response, 503);
        }

        $backgroundTaskRepository->insertBackgroundTasks($bulkPayload);

        return null;
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $datamap
     * @param array<string, array<int|string, array<string, mixed>>> $cmdmap
     */
    protected function executeDataHandler(array $datamap, array $cmdmap = []): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, $cmdmap);
        if ([] !== $datamap) {
            $dataHandler->process_datamap();
        }
        if ([] !== $cmdmap) {
            $dataHandler->process_cmdmap();
        }
        if (count($dataHandler->errorLog) > 0) {
            throw new \RuntimeException(implode(', ', $dataHandler->errorLog));
        }
    }
}
