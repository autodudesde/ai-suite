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

use AutoDudes\AiSuite\Service\CliCommandAvailabilityService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @property CliCommandAvailabilityService $cliCommandAvailabilityService
 */
trait AjaxResponseTrait
{
    protected function logError(string $errorMessage, Response &$response, int $statusCode = 400): Response
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

    protected function resolveHandledByCli(bool $requested, string $workflowType): bool
    {
        if (!$requested) {
            return false;
        }

        // @phpstan-ignore property.notFound
        return $this->cliCommandAvailabilityService->isCliExecutionAvailable($workflowType);
    }
}
