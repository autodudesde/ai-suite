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
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Factory\PageContentFactory;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Utility\ModelUtility;
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ImageController extends ActionController
{
    protected array $extConf;
    protected SendRequestService $requestService;
    protected RequestsRepository $requestsRepository;
    protected PageContentFactory $pageContentFactory;
    protected Context $context;
    protected ResourceFactory $fileFactory;
    protected Filesystem $filesystem;
    protected LoggerInterface $logger;

    public function __construct(
        array $extConf,
        SendRequestService $requestService,
        RequestsRepository $requestsRepository,
        PageContentFactory $pageContentFactory,
        Context $context,
        ResourceFactory $fileFactory,
        Filesystem $filesystem,
        LoggerInterface $logger
    ) {
        $this->extConf = $extConf;
        $this->requestService = $requestService;
        $this->requestsRepository = $requestsRepository;
        $this->pageContentFactory = $pageContentFactory;
        $this->context = $context;
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }

    public function getImageWizardSlideOneAction(ServerRequestInterface $request): ResponseInterface
    {
        $librariesAnswer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'generationLibraries',
                [
                    'library_types' => GenerationLibrariesEnumeration::IMAGE,
                    'target_endpoint' => 'createImage',
                    'keys' => ModelUtility::fetchKeysByModelType($this->extConf,['image'])
                ]
            )
        );

        if ($librariesAnswer->getType() === 'Error') {
            $this->logger->error(LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite'));
            return new HtmlResponse('<div class="alert alert-danger" role="alert">' . LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite') . '</div>');
        }

        $params['promptTemplates'] = PromptTemplateUtility::getAllPromptTemplates('imageWizard');
        $params['imageGenerationLibraries'] = $librariesAnswer->getResponseData()['imageGenerationLibraries'];
        $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];
        $params['uuid'] = UuidUtility::generateUuid();
        $output = $this->getContentFromTemplate(
            $request,
            'WizardSlideOne',
            $params
        );
        return new HtmlResponse($output);
    }

    /**
     * @throws SiteNotFoundException
     * @throws AspectNotFoundException
     */
    public function getImageWizardSlideTwoAction(ServerRequestInterface $request): ResponseInterface
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
                'createImage',
                [
                    'uuid' => $request->getParsedBody()['uuid'],
                    'progress' => 'prepare',
                    'keys' => ModelUtility::fetchKeysByModel($this->extConf, [$request->getParsedBody()['imageAiModel']])
                ],
                $request->getParsedBody()['imagePrompt'],
                $langIsoCode ?? 'en', // TODO: get language from request or somewhere else
                [
                    'image' => $request->getParsedBody()['imageAiModel'],
                ]
            )
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 503);
            return $response;
        }
        if(array_key_exists('free_requests', $answer->getResponseData()) && array_key_exists('free_requests', $answer->getResponseData())) {
            $this->requestsRepository->setRequests($answer->getResponseData()['free_requests'], $answer->getResponseData()['paid_requests']);
            BackendUtility::setUpdateSignal('updateTopbar');
        }
        $params = [
            'imageAiModel' => $request->getParsedBody()['imageAiModel'],
            'imageSuggestions' => $answer->getResponseData()['images'],
            'imageTitleSuggestions' => $answer->getResponseData()['imageTitles'] ?? [],
            'fieldName' => $request->getParsedBody()['fieldName'] ?? '',
            'table' => $request->getParsedBody()['table'] ?? '',
            'position' => $request->getParsedBody()['position'] ?? '',
            'pageId' => $request->getParsedBody()['pageId'],
            'uuid' => $request->getParsedBody()['uuid']
        ];
        $output = $this->getContentFromTemplate(
            $request,
            'WizardSlideTwo',
            $params
        );
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => $output
                ]
            )
        );
        return $response;

    }

    /**
     * @throws SiteNotFoundException
     * @throws AspectNotFoundException
     */
    public function getImageWizardSlideThreeAction(ServerRequestInterface $request): ResponseInterface
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
                'createImage',
                [
                    'uuid' => $request->getParsedBody()['uuid'],
                    'progress' => 'finish',
                    'customId' => $request->getParsedBody()['customId'],
                    'mId' => $request->getParsedBody()['mId'],
                    'index' => $request->getParsedBody()['index'],
                    'keys' => ModelUtility::fetchKeysByModel($this->extConf, [$request->getParsedBody()['imageAiModel']])
                ],
                $request->getParsedBody()['imagePrompt'],
                $langIsoCode ?? 'en', // TODO: get language from request or somewhere else
                [
                    'image' => $request->getParsedBody()['imageAiModel'],
                ]
            )
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 503);
            return $response;
        }
        if(array_key_exists('free_requests', $answer->getResponseData()) && array_key_exists('free_requests', $answer->getResponseData())) {
            $this->requestsRepository->setRequests($answer->getResponseData()['free_requests'], $answer->getResponseData()['paid_requests']);
            BackendUtility::setUpdateSignal('updateTopbar');
        }
        $params = [
            'imageSuggestions' => $answer->getResponseData()['images'],
            'imageTitleSuggestions' => $answer->getResponseData()['imageTitles'] ?? [],
            'fieldName' => $request->getParsedBody()['fieldName'] ?? '',
            'table' => $request->getParsedBody()['table'] ?? '',
            'position' => $request->getParsedBody()['position'] ?? '',
        ];
        $output = $this->getContentFromTemplate(
            $request,
            'WizardSlideThree',
            $params
        );
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => $output
                ]
            )
        );
        return $response;
    }

    /**
     * @throws SiteNotFoundException
     * @throws AspectNotFoundException
     */
    public function regenerateImageAction(ServerRequestInterface $request): ResponseInterface
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
                'createImage',
                [
                    'uuid' => $request->getParsedBody()['uuid'],
                    'progress' => 'finish',
                    'customId' => $request->getParsedBody()['customId'] ?? '',
                    'mId' => $request->getParsedBody()['mId'] ?? '',
                    'index' => $request->getParsedBody()['index'] ?? 0,
                    'keys' => ModelUtility::fetchKeysByModel($this->extConf, [$request->getParsedBody()['imageAiModel']])
                ],
                $request->getParsedBody()['imagePrompt'],
                $langIsoCode,
                [
                    'image' => $request->getParsedBody()['imageAiModel'],
                ]
            )
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 500);
            return $response;
        }
        if(array_key_exists('free_requests', $answer->getResponseData()) && array_key_exists('free_requests', $answer->getResponseData())) {
            $this->requestsRepository->setRequests($answer->getResponseData()['free_requests'], $answer->getResponseData()['paid_requests']);
            BackendUtility::setUpdateSignal('updateTopbar');
        }

        $params = [
            'imageSuggestions' => $answer->getResponseData()['images'],
            'imageTitleSuggestions' => $answer->getResponseData()['imageTitles'],
            'table' => $request->getParsedBody()['table'],
            'pageId' => $request->getParsedBody()['pageId'],
            'fieldName' => $request->getParsedBody()['fieldName'],
            'position' => $request->getParsedBody()['position'],
            'uuid' => $request->getParsedBody()['uuid']
        ];

        $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $standaloneView->setTemplateRootPaths(['EXT:ai_suite/Resources/Private/Templates/Ajax/Image/']);
        $standaloneView->setPartialRootPaths(['EXT:ai_suite/Resources/Private/Partials/']);
        $standaloneView->getRenderingContext()->setControllerName('Image');
        $standaloneView->setTemplate('RegenerateImage');
        $standaloneView->assignMultiple($params);

        $output = $standaloneView->render();
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => $output
                ]
            )
        );

        return $response;
    }

    public function saveGeneratedImageAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $imageUrl = $parsedBody['imageUrl'];
        $imageTitle = array_key_exists('imageTitle', $parsedBody) ? $parsedBody['imageTitle'] : '';

        $response = new Response();
        try {
            $newSysFileUid = $this->pageContentFactory->addImage($imageUrl, $imageTitle);

            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                        'sysFileUid' => $newSysFileUid
                    ]
                )
            );
            return $response;
        } catch (InsufficientFolderAccessPermissionsException $e) {
            $this->logError($e->getMessage(), $response, 403);
        }
        return $response;
    }

    public function fileProcessAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $parsedBody = $request->getParsedBody();
            $fileTarget = $parsedBody['fileTarget'];
            $fileTargetObject = $this->fileFactory->retrieveFileOrFolderObject($fileTarget);

            $destinationPath = Environment::getPublicPath() . $fileTargetObject->getPublicUrl();

            $this->filesystem->copy(
                $parsedBody['fileUrl'],
                $destinationPath . $parsedBody['fileName']
            );
            $newFile = $fileTargetObject ->getFile($parsedBody['fileName']);
            $newFile->getMetaData()->offsetSet('title', $parsedBody['fileTitle']);
            $newFile->getMetaData()->offsetSet('alternative', $parsedBody['fileTitle']);
            $newFile->getMetaData()->save();
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function getContentFromTemplate(
        ServerRequestInterface $request,
        string $templateName,
        array $params = []
    ) {
        $partialRootPaths = ['EXT:ai_suite/Resources/Private/Partials/'];
        $templateRootPaths = ['EXT:ai_suite/Resources/Private/Templates/Ajax/Image/'];
        $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $standaloneView->setTemplateRootPaths($templateRootPaths);
        $standaloneView->setPartialRootPaths($partialRootPaths);
        $standaloneView->getRenderingContext()->setControllerName('Image');
        $standaloneView->setTemplate($templateName);
        $standaloneView->assignMultiple($params);

        $moduleTemplate = GeneralUtility::makeInstance(ModuleTemplateFactory::class)->create($request);
        $moduleTemplate->getDocHeaderComponent()->disable();
        $moduleTemplate->setContent($standaloneView->render());
        return $moduleTemplate->renderContent();
    }

    private function logError(string $errorMessage, Response $response, int $statusCode = 400): void
    {
        $this->logger->error($errorMessage);
        $response->withStatus($statusCode);
        $response->getBody()->write(json_encode(['success' => false, 'status' => $statusCode,'error' => $errorMessage]));
    }
}
