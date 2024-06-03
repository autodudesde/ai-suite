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
use AutoDudes\AiSuite\Service\SendRequestService;
use TYPO3\CMS\Core\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class StatusController extends ActionController
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

    public function getStatusAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $languageId = $this->context->getPropertyFromAspect('language', 'id');
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId((int)$request->getParsedBody()['pageId']);
            $language = $site->getLanguageById($languageId);
            $langIsoCode = $language->getTwoLetterIsoCode();
        } catch(Exception $exception) {
            $this->logError($exception->getMessage(), $response, 503);
            return $response;
        }

        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'requestStatusUpdate',
                [
                    'uuid' => $request->getParsedBody()['uuid']
                ],
                '',
                $langIsoCode,
                []
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
                    'output' => $answer->getResponseData()['status'],
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
