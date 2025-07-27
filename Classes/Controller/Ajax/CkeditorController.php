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
use AutoDudes\AiSuite\Service\BackendUserService;
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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class CkeditorController extends AbstractAjaxController
{
    protected ExtensionConfiguration $extensionConfiguration;
    public function __construct(
        BackendUserService $backendUserService,
        SendRequestService $requestService,
        PromptTemplateService $promptTemplateService,
        LibraryService $libraryService,
        UuidService $uuidService,
        SiteService $siteService,
        TranslationService $translationService,
        ViewFactoryInterface $viewFactory,
        LoggerInterface $logger,
        EventDispatcher $eventDispatcher,
        ExtensionConfiguration $extensionConfiguration,
    ) {
        parent::__construct(
            $backendUserService,
            $requestService,
            $promptTemplateService,
            $libraryService,
            $uuidService,
            $siteService,
            $translationService,
            $viewFactory,
            $logger,
            $eventDispatcher
        );
        $this->extensionConfiguration = $extensionConfiguration;
    }

    public function librariesAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::RTE_CONTENT, 'editContent', ['text']);

        if ($librariesAnswer->getType() === 'Error') {
            $this->logger->error($this->translationService->translate('aiSuite.module.errorFetchingLibraries.title'));
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'output' => '<div class="alert alert-danger" role="alert">' . $this->translationService->translate('aiSuite.module.errorFetchingLibraries.title') . '</div>'
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
                        'libraries' => $this->libraryService->prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries']),
                        'promptTemplates' => $this->promptTemplateService->getAllPromptTemplates('editContent'),
                        'uuid' => $this->uuidService->generateUuid(),
                    ],
                ]
            )
        );
        return $response;
    }

    public function easyLanguageLibrariesAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::RTE_CONTENT, 'editContent', ['text']);

        if ($librariesAnswer->getType() === 'Error') {
            $this->logger->error($this->translationService->translate('aiSuite.module.errorFetchingLibraries.title'));
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'output' => '<div class="alert alert-danger" role="alert">' . $this->translationService->translate('aiSuite.module.errorFetchingLibraries.title') . '</div>'
                    ]
                )
            );
            return $response;
        }
        $configuredLibrary = $this->extensionConfiguration->get('ai_suite', 'easyLanguageLibrary');
        $library = array_filter(
            $librariesAnswer->getResponseData()['textGenerationLibraries'],
            function ($library) use ($configuredLibrary) {
                return $library['model_identifier'] === $configuredLibrary;
            }
        );
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => [
                        'library' => $library,
                        'uuid' => $this->uuidService->generateUuid(),
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
                'type' => $request->getParsedBody()['type'] ?? '',
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
