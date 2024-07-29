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
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use TYPO3\CMS\Core\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class CkeditorController extends ActionController
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
                    'library_types' => GenerationLibrariesEnumeration::RTE_CONTENT,
                    'target_endpoint' => 'editContent',
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
                        'output' => '<div class="alert alert-danger" role="alert">' . LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite') . '</div>'
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
                        'promptTemplates' => PromptTemplateUtility::getAllPromptTemplates('editContent'),
                        'uuid' => UuidUtility::generateUuid(),
                    ],
                ]
            )
        );
        return $response;
    }
    public function requestAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'editContent',
                [
                    'uuid' => $request->getParsedBody()['uuid'],
                    'keys' => ModelUtility::fetchKeysByModelType($this->extConf,['text']),
                    'selectedContent' => $request->getParsedBody()['selectedContent'] ?? '',
                    'wholeContent' => $request->getParsedBody()['wholeContent'] ?? '',
                ],
                $request->getParsedBody()['prompt'] ?? '',
                $request->getParsedBody()['languageCode'] ?? 'en',
                [
                    'text' => $request->getParsedBody()['textModel'],
                ],
            )
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 503);
            return $response;
        }
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => $answer->getResponseData()['editContentResult'],
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
