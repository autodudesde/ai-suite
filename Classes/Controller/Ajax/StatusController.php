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

use AutoDudes\AiSuite\Utility\SiteUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\Response;

class StatusController extends AbstractAjaxController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getStatusAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $langIsoCode = SiteUtility::getLangIsoCode((int)$request->getParsedBody()['pageId']);
        } catch (Exception $exception) {
            $this->logError($exception->getMessage(), $response, 503);
            return $response;
        }

        $answer = $this->requestService->sendDataRequest(
            'requestStatusUpdate',
            [
                'uuid' => $request->getParsedBody()['uuid']
            ],
            '',
            $langIsoCode,
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
}
