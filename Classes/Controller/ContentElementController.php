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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Domain\Model\Dto\PageContent;
use AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest\ServerRequest;
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Factory\PageContentFactory;
use AutoDudes\AiSuite\Service\ContentElementService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ContentElementController extends AbstractBackendController
{
    protected SendRequestService $requestService;
    protected ContentElementService $contentElementService;
    protected Context $context;
    protected PageContentFactory $pageContentFactory;

    public function __construct(
        array $extConf,
        SendRequestService $requestService,
        ContentElementService $contentElementService,
        Context $context,
        PageContentFactory $pageContentFactory
    ) {
        parent::__construct($extConf);
        $this->extConf = $extConf;
        $this->requestService = $requestService;
        $this->contentElementService = $contentElementService;
        $this->context = $context;
        $this->pageContentFactory = $pageContentFactory;
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
    }

    public function initializeCreateContentElementAction(): void
    {
        // necessary for "Try again" action
        $this->request = $this->request->withArgument('request', $this->request);
    }

    public function createContentElementAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleData = $request->getAttribute('moduleData');
        $request = $request->withAttribute('extbase', new ExtbaseRequestParameters(ContentController::class));
        $extbaseRequest = new Request($request);
        $extbaseRequest = $extbaseRequest->withControllerName('ContentElement');
        $extbaseRequest = $extbaseRequest->withControllerActionName('createContentElement');
        $extbaseRequest = $extbaseRequest->withControllerExtensionName('AiSuite');
        $this->moduleTemplate = $this->moduleTemplateFactory->create($extbaseRequest);
        $this->moduleTemplate->setTitle('AI Suite');
        $this->moduleTemplate->setModuleId('aiSuite');

        $librariesAnswer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'generationLibraries',
                [
                    'library_types' => GenerationLibrariesEnumeration::CONTENT_ELEMENT
                ]
            )
        );
        if ($librariesAnswer->getType() === 'Error') {
            $this->moduleTemplate->addFlashMessage($librariesAnswer->getResponseData()['message'], LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite'), ContextualFeedbackSeverity::ERROR);
            $this->moduleTemplate->assign('error', true);
            return $this->htmlResponse($this->moduleTemplate->render());
        }

        $content = PageContent::createEmpty();
        $content->setSysLanguageUid($request->getQueryParams()['defVals']['tt_content']['sys_language_uid'] ?? 0);
        $content->setColPos($request->getQueryParams()['defVals']['tt_content']['colPos'] ?? 0);
        $content->setPid($request->getQueryParams()['defVals']['tt_content']['pid'] ?? $request->getQueryParams()['id']);
        $content->setCType($request->getQueryParams()['defVals']['tt_content']['CType'] ?? 'text');
        $content->setReturnUrl($request->getQueryParams()['returnUrl'] ?? '');
        if(array_key_exists('edit', $request->getQueryParams()) && array_key_exists('tt_content', $request->getQueryParams()['edit'])) {
            $content->setUidPid(key($request->getQueryParams()['edit']['tt_content']) ?? $request->getQueryParams()['id']);
        } else {
            $content->setUidPid($request->getQueryParams()['id']);
        }

        $defVals = $request->getQueryParams()['defVals'] ?? [];
        $requestFields = $this->contentElementService->fetchRequestFields($request, $defVals, $content->getCType(), $content->getPid());
        $content->setAvailableTcaColumns($requestFields);

        // set error action uri
        $errorActionUri = (string)$this->backendUriBuilder->buildUriFromRoute('ai_suite_record_edit', [
            'edit' => [
                'tt_content' => [
                    $content->getUidPid() => 'new',
                ],
            ],
            'returnUrl' => $content->getReturnUrl(),
            'defVals' => $defVals
        ]);

        $content->setErrorReturnUrl($errorActionUri);

        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/content-element/creation.js');
        $moduleName = 'web_aisuite';
        $uriParameters = [
            'id' => $content->getPid(),
            'action' => 'requestContentElement',
            'controller' => 'ContentElement',
        ];
        $actionUri = (string)$this->backendUriBuilder->buildUriFromRoute($moduleName, $uriParameters);

        if(isset($request->getQueryParams()['selectedTcaColumns'])) {
            $selectedTcaColumns = json_decode($request->getQueryParams()['selectedTcaColumns'], true);
        } else {
            $selectedTcaColumns = $requestFields;
        }
        $this->moduleTemplate->assignMultiple([
            'content' => $content,
            'actionUri' => $actionUri,
            'errorActionUri' => $errorActionUri,
            'textGenerationLibraries' => $librariesAnswer->getResponseData()['textGenerationLibraries'],
            'imageGenerationLibraries' => $librariesAnswer->getResponseData()['imageGenerationLibraries'],
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
            'promptTemplates' => PromptTemplateUtility::getAllPromptTemplates(
                'contentElement',
                $request->getQueryParams()['defVals']['tt_content']['CType'] ?? 'text',
                $content->getSysLanguageUid()
            ),
            'initialPrompt' => $request->getQueryParams()['initialPrompt'] ?? '',
            'selectedTcaColumns' => $selectedTcaColumns,
        ]);
        return $this->htmlResponse($this->moduleTemplate->render());
    }

    public function initializeRequestContentElementAction(): void
    {
        if(!$this->request->hasArgument('content')) {
            $this->request = $this->request->withArgument('content', PageContent::createEmpty());
        }
    }

    /**
     * @throws AspectNotFoundException
     * @throws SiteNotFoundException
     */
    public function requestContentElementAction(PageContent $content): ResponseInterface
    {
        if($content->getPid() === 0 && $content->getUid() == 0 && $content->getUidPid() === 0) {
            $this->moduleTemplate->assign('error', true);
            $this->moduleTemplate->addFlashMessage(
                LocalizationUtility::translate('aiSuite.module.errorMissingArguments.message', 'ai_suite'),
                LocalizationUtility::translate('aiSuite.module.errorMissingArguments.title', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->htmlResponse($this->moduleTemplate->render());
        }
        $selectedTcaColumns = $this->request->getParsedBody()['content']['selectedTcaColumns'] ?? [];
        $availableTcaColumns = json_decode($this->request->getParsedBody()['content']['availableTcaColumns'],true) ?? [];

        try {
            $languageId = $this->context->getPropertyFromAspect('language', 'id');
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($content->getPid());
            $language = $site->getLanguageById($languageId);
            $langIsoCode = $language->getLocale()->getLanguageCode();
        } catch(Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->addFlashMessage(
                $exception->getMessage(),
                LocalizationUtility::translate('aiSuite.module.cannotSendRequest.title', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            $this->moduleTemplate->assign('errorActionUri', $content->getErrorReturnUrl());
            return $this->htmlResponse($this->moduleTemplate->render());
        }
        $textAi = !empty($this->request->getParsedBody()['libraries']['textGenerationLibrary']) ? $this->request->getParsedBody()['libraries']['textGenerationLibrary'] : '';
        $imageAi = !empty($this->request->getParsedBody()['libraries']['imageGenerationLibrary']) ? $this->request->getParsedBody()['libraries']['imageGenerationLibrary'] : '';

        $requestFields = [];
        foreach ($selectedTcaColumns as $type => $fields) {
            $requestFields[$type] = [
                'label' => $availableTcaColumns[$type]['label'],
            ];
            if(array_key_exists('foreignField', $availableTcaColumns[$type])) {
                $requestFields[$type]['foreignField'] = $availableTcaColumns[$type]['foreignField'];
            }
            if(array_key_exists('text', $fields)) {
                foreach ($fields['text'] as $fieldName => $renderType) {
                    if($renderType !== '') {
                        $requestFields[$type]['text'][$fieldName] = $availableTcaColumns[$type]['text'][$fieldName];
                    }
                }
            }
            if(array_key_exists('image', $fields)) {
                foreach ($fields['image'] as $fieldName => $renderType) {
                    if($renderType !== '') {
                        $requestFields[$type]['image'][$fieldName] = $availableTcaColumns[$type]['image'][$fieldName];
                    }
                }
            }
        }
        $content->setSelectedTcaColumns($requestFields);
        $models = $this->contentElementService->checkRequestModels($requestFields, ['text' => $textAi, 'image' => $imageAi]);
        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'createContentElement',
                [
                    'request_fields' => json_encode($requestFields),
                    'c_type' => $content->getCType(),
                ],
                $content->getInitialPrompt(),
                strtoupper($langIsoCode),
                $models
            )
        );
        if ($answer->getType() === 'Error') {
            $this->moduleTemplate->addFlashMessage(
                $answer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.errorValidContentElementResponse.title', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            $this->moduleTemplate->assign('errorActionUri', $content->getErrorReturnUrl());
            return $this->htmlResponse($this->moduleTemplate->render());
        }
        $contentElementData = json_decode($answer->getResponseData()['contentElementData'], true);
        $content->setContentElementData($contentElementData);
        $this->moduleTemplate->assign('content', $content);
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');

        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/content-element/validation.js');
        $moduleName = 'web_aisuite';
        $uriParameters = [
            'id' => $content->getPid(),
            'action' => 'createPageContent',
            'controller' => 'ContentElement'
        ];
        $actionUri = (string)$this->backendUriBuilder->buildUriFromRoute($moduleName, $uriParameters);
        $regenerateActionUri = (string)$this->backendUriBuilder->buildUriFromRoute('ai_suite_record_edit', [
            'edit' => [
                'tt_content' => [
                    $content->getUidPid() => 'new',
                ],
            ],
            'returnUrl' => $content->getReturnUrl(),
            'defVals' => [
                'tt_content' => [
                    'sys_language_uid' => $content->getSysLanguageUid(),
                    'colPos' => $content->getColPos(),
                    'pid' => $content->getPid(),
                    'CType' => $content->getCType(),
                ],
            ],
            'initialPrompt' => $content->getInitialPrompt(),
            'selectedTcaColumns' => json_encode($selectedTcaColumns),
        ]);
        $this->moduleTemplate->assign('regenerateActionUri', $regenerateActionUri);
        $this->moduleTemplate->assign('actionUri', $actionUri);
        $this->moduleTemplate->addFlashMessage(
            LocalizationUtility::translate('aiSuite.module.fetchingDataSuccessful.message', 'ai_suite'),
            LocalizationUtility::translate('aiSuite.module.fetchingDataSuccessful.title', 'ai_suite')
        );
        return $this->htmlResponse($this->moduleTemplate->render());
    }

    public function createPageContentAction(PageContent $content): ResponseInterface
    {
        try {
            $selectedTcaColumns = json_decode($this->request->getParsedBody()['content']['selectedTcaColumns'], true) ?? [];
            $contentElementTextData = $this->request->getParsedBody()['content']['contentElementData'] ?? [];
            $contentElementImageData = [];

            $parsedBody = $this->request->getParsedBody() ?? [];
            if(array_key_exists('fileData', $parsedBody)) {
                foreach($parsedBody['fileData']['content']['contentElementData'] as $table => $fieldsArray) {
                    foreach ($fieldsArray as $key => $fields) {
                        foreach($fields as $fieldName => $fieldData) {
                            if(array_key_exists('newImageUrl', $fieldData)) {
                                $contentElementImageData[$table][$key][$fieldName]['newImageUrl'] = $fieldData['newImageUrl'];
                                $contentElementImageData[$table][$key][$fieldName]['imageTitle'] = $fieldData['imageTitle'] ?? '';
                            }
                        }
                    }
                }
            }

            $contentElementIrreFields = [];
            foreach ($selectedTcaColumns as $table => $fields) {
                if(array_key_exists('foreignField', $fields)) {
                    $contentElementIrreFields[$table] = $fields['foreignField'];
                }
            }
            $this->pageContentFactory->createContentElementData($content, $contentElementTextData, $contentElementImageData, $contentElementIrreFields);
        } catch(Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->addFlashMessage(
                $exception->getMessage(),
                LocalizationUtility::translate('aiSuite.module.errorPageContentNotCreated.title', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            $this->moduleTemplate->assign('errorActionUri', $content->getErrorReturnUrl());
            return $this->htmlResponse($this->moduleTemplate->render());
        }
        return $this->redirectToUri($content->getReturnUrl());
    }
}
