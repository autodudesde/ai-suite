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
use AutoDudes\AiSuite\Utility\BackendUserUtility;
use AutoDudes\AiSuite\Utility\LibraryUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\Response;

class TranslationController extends AbstractAjaxController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function librariesAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::TRANSLATE,'translate', ['text']);

        if ($librariesAnswer->getType() === 'Error') {
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'output' => $librariesAnswer->getResponseData()['message'],
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
                        'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
                        'uuid' => UuidUtility::generateUuid(),
                    ],
                ]
            )
        );
        return $response;
    }

    public function checkLocalizationPermissionsAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => [
                        'permissions' => [
                            'enable_translation' => BackendUserUtility::checkPermissions('tx_aisuite_features:enable_translation'),
                        ],
                    ],
                ]
            )
        );
        return $response;
    }
}
