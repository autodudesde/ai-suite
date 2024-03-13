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
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Factory\PageContentFactory;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use TYPO3\CMS\Core\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ImageController extends ActionController
{
    protected array $extConf;
    protected SendRequestService $requestService;
    protected PageContentFactory $pageContentFactory;
    protected Context $context;
    protected LoggerInterface $logger;

    public function __construct(
        array $extConf,
        SendRequestService $requestService,
        PageContentFactory $pageContentFactory,
        Context $context,
        LoggerInterface $logger
    ) {
        $this->extConf = $extConf;
        $this->requestService = $requestService;
        $this->pageContentFactory = $pageContentFactory;
        $this->context = $context;
        $this->logger = $logger;
    }

    public function getImageWizardSlideOneAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        $parsedBody = $request->getParsedBody();
        $params = [
            'imageAiValue' => $parsedBody['imageAiValue'],
            'promptValue' => $parsedBody['promptValue']
        ];
        $librariesAnswer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'generationLibraries',
                [
                    'library_types' => GenerationLibrariesEnumeration::IMAGE
                ]
            )
        );
        if ($librariesAnswer->getType() === 'Error') {
            $this->logError(LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite'), $response, 500);
            return $response;
        }

        $params['promptTemplates'] = PromptTemplateUtility::getAllPromptTemplates('imageWizard');
        $params['imageGenerationLibraries'] = $librariesAnswer->getResponseData()['imageGenerationLibraries'];
        $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];
        $output = $this->getContentFromTemplate(
            $request,
            'WizardSlideOne',
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
    public function getImageWizardSlideTwoAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $languageId = $this->context->getPropertyFromAspect('language', 'id');
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($request->getParsedBody()['pageId']);
            $language = $site->getLanguageById($languageId);
            $langIsoCode = $language->getLocale()->getLanguageCode();
        } catch(Exception $exception) {
            $this->logError($exception->getMessage(), $response, 503);
            return $response;
        }

        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'createImage',
                [],
                $request->getParsedBody()['imagePrompt'],
                $langIsoCode,
                [
                    'image' => $request->getParsedBody()['imageAi'],
                ]
            )
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 503);
            return $response;
        }
        $params = [
            'imageSuggestions' => $answer->getResponseData()['images'],
            'imageTitleSuggestions' => $answer->getResponseData()['imageTitles'],
            'fieldName' => $request->getParsedBody()['fieldName'],
            'table' => $request->getParsedBody()['table'],
            'position' => $request->getParsedBody()['position'],
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
    public function regenerateImageAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $languageId = $this->context->getPropertyFromAspect('language', 'id');
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($request->getParsedBody()['pageId']);
            $language = $site->getLanguageById($languageId);
            $langIsoCode = $language->getLocale()->getLanguageCode();
        } catch(Exception $exception) {
            $this->logError($exception->getMessage(), $response, 503);
            return $response;
        }

        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'createImage',
                [],
                $request->getParsedBody()['imagePrompt'],
                $langIsoCode,
                [
                    'image' => $request->getParsedBody()['imageAi'],
                ]
            )
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 500);
            return $response;
        }
        $params = [
            'imageSuggestions' => $answer->getResponseData()['images'],
            'imageTitleSuggestions' => $answer->getResponseData()['imageTitles'],
            'table' => $request->getParsedBody()['table'],
            'pageId' => $request->getParsedBody()['pageId'],
            'fieldName' => $request->getParsedBody()['fieldName'],
            'position' => $request->getParsedBody()['position'],
            'imagePrompt' => $request->getParsedBody()['imagePrompt'],
            'imageAi' => $request->getParsedBody()['imageAi'],
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
