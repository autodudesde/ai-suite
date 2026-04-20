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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Controller\Trait\AjaxResponseTrait;
use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
class TranslationController extends AbstractBackendController
{
    use AjaxResponseTrait;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly ViewFactoryService $viewFactoryService,
        protected readonly UuidService $uuidService,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $uriBuilder,
            $pageRenderer,
            $flashMessageService,
            $requestService,
            $translationService,
            $eventDispatcher,
            $aiSuiteContext,
        );
    }

    public function librariesAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::TRANSLATE, 'translate', ['text']);

        if ('Error' === $librariesAnswer->getType()) {
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => false,
                        'output' => $librariesAnswer->getResponseData()['message'],
                    ]
                )
            );

            return $response;
        }

        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => true,
                    'output' => [
                        'libraries' => $this->aiSuiteContext->libraryService->prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries']),
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
            (string) json_encode(
                [
                    'success' => true,
                    'output' => [
                        'permissions' => [
                            'enable_translation' => $this->aiSuiteContext->backendUserService->checkPermissions('tx_aisuite_features:enable_translation'),
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
        $params = (array) $request->getParsedBody();
        $pageId = (int) $params['pageId'];

        try {
            $translationProvider = GeneralUtility::makeInstance(TranslationConfigurationProvider::class);
            $sysLanguages = $translationProvider->getSystemLanguages($pageId);
            $availableSourceLanguages = array_values(
                array_filter($sysLanguages, static function (array $languageRecord): bool {
                    return 0 === (int) $languageRecord['uid'];
                })
            );
            $availableTargetLanguages = $this->translationService->getAvailableTargetLanguages($sysLanguages, $pageId);
            $librariesAnswer = $this->requestService->sendLibrariesRequest(
                GenerationLibraryEnumeration::TRANSLATE,
                'translate',
                ['text']
            );

            if ('Error' === $librariesAnswer->getType()) {
                $this->logError($librariesAnswer->getResponseData()['message'], $response);

                return $response;
            }

            $libraries = $this->aiSuiteContext->libraryService->prepareLibraries(
                $librariesAnswer->getResponseData()['textGenerationLibraries']
            );

            $content = $this->viewFactoryService->renderTemplate(
                $request,
                'WizardSlideOne',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Translation/',
                [
                    'availableSourceLanguages' => $availableSourceLanguages,
                    'availableTargetLanguages' => $availableTargetLanguages,
                    'translationLibraries' => $libraries,
                    'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
                    'pageId' => $pageId,
                ]
            );

            $response->getBody()->write(
                (string) json_encode([
                    'success' => true,
                    'output' => $content,
                    'uuid' => $this->uuidService->generateUuid(),
                ])
            );
        } catch (\Exception $e) {
            $this->logError('Error loading translation wizard slide one: '.$e->getMessage(), $response);
        }

        return $response;
    }
}
