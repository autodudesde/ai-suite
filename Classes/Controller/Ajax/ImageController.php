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
use AutoDudes\AiSuite\Factory\PageContentFactory;
use AutoDudes\AiSuite\Utility\LibraryUtility;
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use AutoDudes\AiSuite\Utility\SiteUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ImageController extends AbstractAjaxController
{
    protected PageContentFactory $pageContentFactory;
    protected ResourceFactory $fileFactory;
    protected Filesystem $filesystem;

    public function __construct(
        PageContentFactory $pageContentFactory,
        ResourceFactory $fileFactory,
        Filesystem $filesystem
    ) {
        parent::__construct();
        $this->pageContentFactory = $pageContentFactory;
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
    }

    public function getImageWizardSlideOneAction(ServerRequestInterface $request): ResponseInterface
    {
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::IMAGE, 'createImage', ['image']);

        if ($librariesAnswer->getType() === 'Error') {
            $this->logger->error(LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite'));
            return new HtmlResponse($librariesAnswer->getResponseData()['message']);
        }

        $params['promptTemplates'] = PromptTemplateUtility::getAllPromptTemplates('imageWizard');
        $params['imageGenerationLibraries'] = LibraryUtility::prepareLibraries($librariesAnswer->getResponseData()['imageGenerationLibraries']);
        $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];
        $params['uuid'] = UuidUtility::generateUuid();
        $output = $this->getContentFromTemplate(
            $request,
            'WizardSlideOne',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Image/',
            'Image',
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
            $langIsoCode = SiteUtility::getLangIsoCode((int)$request->getParsedBody()['pageId']);
        } catch (Exception $exception) {
            $this->logError($exception->getMessage(), $response, 503);
            return $response;
        }

        $answer = $this->requestService->sendDataRequest(
            'createImage',
            [
                'uuid' => $request->getParsedBody()['uuid'],
                'progress' => 'prepare'
            ],
            $request->getParsedBody()['imagePrompt'],
            $langIsoCode,
            [
                'image' => $request->getParsedBody()['imageAiModel'],
            ]
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 503);
            return $response;
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
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Image/',
            'Image',
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
            $langIsoCode = SiteUtility::getLangIsoCode((int)$request->getParsedBody()['pageId']);
        } catch (Exception $exception) {
            $this->logError($exception->getMessage(), $response, 503);
            return $response;
        }

        $answer = $this->requestService->sendDataRequest(
            'createImage',
            [
                'uuid' => $request->getParsedBody()['uuid'],
                'progress' => 'finish',
                'customId' => $request->getParsedBody()['customId'],
                'mId' => $request->getParsedBody()['mId'],
                'index' => $request->getParsedBody()['index']
            ],
            $request->getParsedBody()['imagePrompt'],
            $langIsoCode,
            [
                'image' => $request->getParsedBody()['imageAiModel'],
            ]
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 503);
            return $response;
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
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Image/',
            'Image',
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
            $langIsoCode = SiteUtility::getLangIsoCode((int)$request->getParsedBody()['pageId']);
        } catch (Exception $exception) {
            $this->logError($exception->getMessage(), $response, 503);
            return $response;
        }

        $answer = $this->requestService->sendDataRequest(
            'createImage',
            [
                'uuid' => $request->getParsedBody()['uuid'],
                'progress' => 'finish',
                'customId' => $request->getParsedBody()['customId'] ?? '',
                'mId' => $request->getParsedBody()['mId'] ?? '',
                'index' => $request->getParsedBody()['index'] ?? 0
            ],
            $request->getParsedBody()['imagePrompt'],
            $langIsoCode,
            [
                'image' => $request->getParsedBody()['imageAiModel'],
            ]
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
            'uuid' => $request->getParsedBody()['uuid']
        ];

        $output = $this->getContentFromTemplate(
            $request,
            'RegenerateImage',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Image/',
            'Image',
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
}
