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

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\GlobalInstructionService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class StatusController extends AbstractAjaxController
{
    public function __construct(
        BackendUserService $backendUserService,
        SendRequestService $requestService,
        PromptTemplateService $promptTemplateService,
        GlobalInstructionService $globalInstructionService,
        LibraryService $libraryService,
        UuidService $uuidService,
        SiteService $siteService,
        TranslationService $translationService,
        ViewFactoryInterface $viewFactory,
        LoggerInterface $logger,
        EventDispatcher $eventDispatcher,
    ) {
        parent::__construct(
            $backendUserService,
            $requestService,
            $promptTemplateService,
            $globalInstructionService,
            $libraryService,
            $uuidService,
            $siteService,
            $translationService,
            $viewFactory,
            $logger,
            $eventDispatcher
        );
    }

    public function getStatusAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        $backendUser = $this->backendUserService->getBackendUser();
        if ($backendUser->user['lang'] === 'default') {
            $langIsoCode = 'en';
        } else {
            $langIsoCode = $backendUser->user['lang'];
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
