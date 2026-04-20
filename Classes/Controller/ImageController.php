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
use AutoDudes\AiSuite\Exception\AiSuiteException;
use AutoDudes\AiSuite\Factory\PageContentFactory;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;

#[AsController]
class ImageController extends AbstractBackendController
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
        protected readonly PageContentFactory $pageContentFactory,
        protected readonly ResourceFactory $fileFactory,
        protected readonly Filesystem $filesystem,
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

    public function getImageWizardSlideOneAction(ServerRequestInterface $request): ResponseInterface
    {
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::IMAGE, 'createImage', ['image']);
        if ('Error' === $librariesAnswer->getType()) {
            $this->logger->error($this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorFetchingLibraries.title'));

            return new HtmlResponse($librariesAnswer->getResponseData()['message']);
        }

        $params['promptTemplates'] = $this->aiSuiteContext->promptTemplateService->getAllPromptTemplates('imageWizard');
        $params['imageGenerationLibraries'] = $this->aiSuiteContext->libraryService->prepareLibraries($librariesAnswer->getResponseData()['imageGenerationLibraries']);
        $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];
        $params['uuid'] = $this->uuidService->generateUuid();
        $params['sysLanguages'] = $this->aiSuiteContext->siteService->getAvailableLanguages();
        $output = $this->viewFactoryService->renderTemplate(
            $request,
            'WizardSlideOne',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Image/',
            $params
        );

        return new HtmlResponse($output);
    }

    public function getImageWizardSlideTwoAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $parsedBody = (array) $request->getParsedBody();
            $pageId = $parsedBody['pageId'] ?? 0;
            $langIsoCode = $parsedBody['langIsoCode'] ?? $this->aiSuiteContext->siteService->getIsoCodeByLanguageId((int) $parsedBody['languageId'], (int) $pageId);
            if (isset($parsedBody['pageId'])) {
                $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'imageWizard', (int) $parsedBody['pageId']);
            } else {
                $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('files', 'imageWizard', null, $parsedBody['targetFolder'] ?? '');
            }
            $answer = $this->requestService->sendDataRequest(
                'createImage',
                [
                    'uuid' => $parsedBody['uuid'],
                    'progress' => 'prepare',
                    'global_instructions' => $globalInstructions,
                ],
                $parsedBody['imagePrompt'],
                $langIsoCode,
                [
                    'image' => $parsedBody['imageAiModel'],
                ]
            );
            if ('Error' === $answer->getType()) {
                $this->logError($answer->getResponseData()['message'], $response, 503);

                return $response;
            }
            $params = [
                'imageAiModel' => $parsedBody['imageAiModel'],
                'imageSuggestions' => $answer->getResponseData()['images'],
                'imageTitleSuggestions' => $answer->getResponseData()['imageTitles'] ?? [],
                'fieldName' => $parsedBody['fieldName'] ?? '',
                'table' => $parsedBody['table'] ?? '',
                'position' => $parsedBody['position'] ?? '',
                'uuid' => $parsedBody['uuid'],
            ];
            $output = $this->viewFactoryService->renderTemplate(
                $request,
                'WizardSlideTwo',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Image/',
                $params
            );
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => true,
                        'output' => $output,
                    ]
                )
            );
        } catch (\Exception $e) {
            $this->logError($e->getMessage(), $response, 403);
        }

        return $response;
    }

    public function getImageWizardSlideThreeAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $parsedBody = (array) $request->getParsedBody();
        $langIsoCode = $parsedBody['langIsoCode'] ?? $this->aiSuiteContext->siteService->getIsoCodeByLanguageId((int) $parsedBody['languageId'], (int) $parsedBody['pageId']);
        $answer = $this->requestService->sendDataRequest(
            'createImage',
            [
                'uuid' => $parsedBody['uuid'],
                'progress' => 'finish',
                'customId' => $parsedBody['customId'],
                'mId' => $parsedBody['mId'],
                'index' => $parsedBody['index'],
            ],
            $parsedBody['imagePrompt'],
            $langIsoCode,
            [
                'image' => $parsedBody['imageAiModel'],
            ]
        );
        if ('Error' === $answer->getType()) {
            $this->logError($answer->getResponseData()['message'], $response, 503);

            return $response;
        }
        $params = [
            'imageSuggestions' => $answer->getResponseData()['images'],
            'imageTitleSuggestions' => $answer->getResponseData()['imageTitles'] ?? [],
            'fieldName' => $parsedBody['fieldName'] ?? '',
            'table' => $parsedBody['table'] ?? '',
            'position' => $parsedBody['position'] ?? '',
        ];
        $output = $this->viewFactoryService->renderTemplate(
            $request,
            'WizardSlideThree',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Image/',
            $params
        );
        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => true,
                    'output' => $output,
                ]
            )
        );

        return $response;
    }

    public function regenerateImageAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $parsedBody = (array) $request->getParsedBody();
        $langIsoCode = $parsedBody['langIsoCode'] ?? $this->aiSuiteContext->siteService->getIsoCodeByLanguageId((int) $parsedBody['languageId'], (int) $parsedBody['pageId']);
        $answer = $this->requestService->sendDataRequest(
            'createImage',
            [
                'uuid' => $parsedBody['uuid'],
                'progress' => 'finish',
                'customId' => $parsedBody['customId'] ?? '',
                'mId' => $parsedBody['mId'] ?? '',
                'index' => $parsedBody['index'] ?? 0,
            ],
            $parsedBody['imagePrompt'],
            $langIsoCode,
            [
                'image' => $parsedBody['imageAiModel'],
            ]
        );
        if ('Error' === $answer->getType()) {
            $this->logError($answer->getResponseData()['message'], $response, 500);

            return $response;
        }
        $params = [
            'imageSuggestions' => $answer->getResponseData()['images'],
            'imageTitleSuggestions' => $answer->getResponseData()['imageTitles'],
            'table' => $parsedBody['table'],
            'pageId' => $parsedBody['pageId'],
            'languageId' => $parsedBody['languageId'],
            'fieldName' => $parsedBody['fieldName'],
            'position' => $parsedBody['position'],
            'uuid' => $parsedBody['uuid'],
        ];

        $output = $this->viewFactoryService->renderTemplate(
            $request,
            'RegenerateImage',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Image/',
            $params
        );
        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => true,
                    'output' => $output,
                ]
            )
        );

        return $response;
    }

    public function saveGeneratedImageAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = (array) $request->getParsedBody();
        $imageUrl = $parsedBody['imageUrl'];
        $imageTitle = array_key_exists('imageTitle', $parsedBody) ? $parsedBody['imageTitle'] : '';

        $response = new Response();

        try {
            $newSysFileUid = $this->pageContentFactory->addImage($imageUrl, $imageTitle);

            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => true,
                        'sysFileUid' => $newSysFileUid,
                    ]
                )
            );

            return $response;
        } catch (AiSuiteException $e) {
            $errorMessage = !empty($e->getMessage()) ? $e->getMessage() : $this->aiSuiteContext->localizationService->translate($e->getMessageKey());
            $this->logError($errorMessage, $response, 403);
        } catch (InsufficientFolderAccessPermissionsException $e) {
            $this->logError($e->getMessage(), $response, 403);
        }

        return $response;
    }

    public function fileProcessAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $parsedBody = (array) $request->getParsedBody();
            $fileTarget = $parsedBody['fileTarget'];
            $fileTargetObject = $this->fileFactory->retrieveFileOrFolderObject($fileTarget);
            assert($fileTargetObject instanceof Folder);

            $destinationPath = Environment::getPublicPath().$fileTargetObject->getPublicUrl();

            $this->filesystem->copy(
                $parsedBody['fileUrl'],
                $destinationPath.$parsedBody['fileName']
            );

            /** @var File $newFile */
            $newFile = $fileTargetObject->getFile($parsedBody['fileName']);
            $newFile->getMetaData()->offsetSet('title', $parsedBody['fileTitle']);
            $newFile->getMetaData()->offsetSet('alternative', $parsedBody['fileTitle']);
            $newFile->getMetaData()->save();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
