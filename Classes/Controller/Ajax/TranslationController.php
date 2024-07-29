<?php

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Controller\Ajax;

use AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest\ServerRequest;
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Utility\ModelUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use TYPO3\CMS\Core\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class TranslationController extends ActionController
{
    protected array $extConf;
    protected SendRequestService $requestService;
    protected Context $context;
    protected LoggerInterface $logger;

    public function __construct(
        array $extConf,
        SendRequestService $requestService,
        Context $context,
        LoggerInterface $logger
    ) {
        $this->extConf = $extConf;
        $this->requestService = $requestService;
        $this->context = $context;
        $this->logger = $logger;
    }

    public function librariesAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'generationLibraries',
                [
                    'library_types' => GenerationLibrariesEnumeration::TRANSLATE,
                    'target_endpoint' => 'translate',
                    'keys' => ModelUtility::fetchKeysByModelType($this->extConf,['text'])
                ]
            )
        );

        if ($librariesAnswer->getType() === 'Error') {
            $this->logger->error(LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite'));
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'output' => LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite')
                    ]
                )
            );
            return $response;
        }

        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => [
                        'libraries' => $librariesAnswer->getResponseData()['textGenerationLibraries'],
                        'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
                        'uuid' => UuidUtility::generateUuid(),
                    ],
                ]
            )
        );
        return $response;
    }

    private function logError(string $errorMessage, Response $response, int $statusCode = 400): void
    {
        $this->logger->error($errorMessage);
        $response->withStatus($statusCode);
        $response->getBody()->write(json_encode(['success' => false, 'status' => $statusCode,'error' => $errorMessage]));
    }
}
