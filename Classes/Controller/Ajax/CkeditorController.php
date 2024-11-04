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

use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Utility\LibraryUtility;
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class CkeditorController extends AbstractAjaxController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function librariesAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::RTE_CONTENT, 'editContent', ['text']);

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
                        'libraries' => LibraryUtility::prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries']),
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

        $answer = $this->requestService->sendDataRequest(
            'editContent',
            [
                'uuid' => $request->getParsedBody()['uuid'],
                'selectedContent' => $request->getParsedBody()['selectedContent'] ?? '',
                'wholeContent' => $request->getParsedBody()['wholeContent'] ?? '',
            ],
            $request->getParsedBody()['prompt'] ?? '',
            $request->getParsedBody()['languageCode'] ?? 'en',
            [
                'text' => $request->getParsedBody()['textModel'],
            ],
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
}
