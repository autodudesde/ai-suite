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
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class TranslationController extends AbstractAjaxController
{
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
                        'libraries' => $this->libraryService->prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries']),
                        'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
                        'uuid' => $this->uuidService->generateUuid(),
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
                            'enable_translation' => $this->backendUserService->checkPermissions('tx_aisuite_features:enable_translation'),
                        ],
                    ],
                ]
            )
        );
        return $response;
    }

    public function getTranslationWizardSlideOneAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = $request->getParsedBody();
        $pageId = (int)($params['pageId']);

        try {
            $translationProvider = GeneralUtility::makeInstance(TranslationConfigurationProvider::class);
            $sysLanguages = $translationProvider->getSystemLanguages($pageId);
            $availableSourceLanguages = array_values(
                array_filter($sysLanguages, static function (array $languageRecord): bool {
                    return (int)$languageRecord['uid'] === 0;
                })
            );
            $availableTargetLanguages = $this->translationService->getAvailableTargetLanguages($sysLanguages, $pageId);
            $librariesAnswer = $this->requestService->sendLibrariesRequest(
                GenerationLibrariesEnumeration::TRANSLATE,
                'translate',
                ['text']
            );

            if ($librariesAnswer->getType() === 'Error') {
                $this->logError($librariesAnswer->getResponseData()['message'], $response);
                return $response;
            }

            $libraries = $this->libraryService->prepareLibraries(
                $librariesAnswer->getResponseData()['textGenerationLibraries']
            );

            $content = $this->getContentFromTemplate(
                $request,
                'WizardSlideOne',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Translation/',
                [
                    'availableSourceLanguages' => $availableSourceLanguages,
                    'availableTargetLanguages' => $availableTargetLanguages,
                    'translationLibraries' => $libraries,
                    'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
                    'pageId' => $pageId
                ]
            );

            $response->getBody()->write(
                json_encode([
                    'success' => true,
                    'output' => $content,
                    'uuid' => $this->uuidService->generateUuid()
                ])
            );
        } catch (\Exception $e) {
            $this->logError('Error loading translation wizard slide one: ' . $e->getMessage(), $response);
        }

        return $response;
    }
}
