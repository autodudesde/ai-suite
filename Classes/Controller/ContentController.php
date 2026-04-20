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
use AutoDudes\AiSuite\Service\ContentService;
use AutoDudes\AiSuite\Service\RichTextElementService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class ContentController extends AbstractBackendController
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
        protected readonly UuidService $uuidService,
        protected readonly ContentService $contentService,
        protected readonly RichTextElementService $richTextElementService,
        protected readonly Context $context,
        protected readonly PageContentFactory $pageContentFactory,
        protected readonly LoggerInterface $logger,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly ViewFactoryService $viewFactoryService,
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
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->initialize($request);
            $identifier = $request->getAttribute('route')->getOption('_identifier');

            switch ($identifier) {
                case 'ai_suite_content_request':
                    return $this->requestContentAction($request);

                case 'ai_suite_content_save':
                    return $this->saveContentAction($request);

                default:
                    return $this->createContentAction($request);
            }
        } catch (AiSuiteException $e) {
            $this->view->assign('error', true);
            $this->view->addFlashMessage(
                !empty($e->getMessage()) ? $e->getMessage() : $this->aiSuiteContext->localizationService->translate(''.$e->getMessageKey()),
                $this->aiSuiteContext->localizationService->translate(''.$e->getTitleKey()),
                ContextualFeedbackSeverity::ERROR
            );
            if (!empty($e->getReturnUrl())) {
                return new RedirectResponse($e->getReturnUrl());
            }

            return $this->view->renderResponse($e->getTemplate());
        } catch (\Throwable $e) {
            $this->view->assign('error', true);
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->view->renderResponse('Content/CreateContent');
        }
    }

    public function createContentAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $table = array_key_first($params['edit']);
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::CONTENT, 'createContentElement', ['text', 'image']);
        $defVals = [];
        if (array_key_exists('defVals', $params)) {
            $defVals = $params['defVals'];
            $content = [
                'sysLanguageUid' => (int) $defVals[$table]['sys_language_uid'],
                'colPos' => (int) $defVals[$table]['colPos'],
                'pid' => (int) $defVals[$table]['pid'],
                'CType' => $defVals[$table]['CType'] ?? 'text',
            ];
            $content['containerParentUid'] = isset($params['defVals'][$table]['tx_container_parent']) ? (int) $params['defVals'][$table]['tx_container_parent'] : 0;
        } else {
            $content['pid'] = (int) $params['pid'];
            $content['CType'] = $params['recordType'] ?? '';
            $content['sysLanguageUid'] = !empty($params['sysLanguageUid']) ? (int) $params['sysLanguageUid'] : 0;
        }
        $content['returnUrl'] = $params['returnUrl'] ?? '';
        if (array_key_exists('edit', $params) && array_key_exists($table, $params['edit'])) {
            $content['uidPid'] = key($params['edit'][$table]) ?? $params['id'];
        } else {
            $content['uidPid'] = $params['id'];
        }

        $requestFields = $this->contentService->fetchRequestFields($request, $defVals, $content['CType'], $content['pid'], $table);
        $selectedTcaColumns = isset($params['selectedTcaColumns']) ? json_decode($params['selectedTcaColumns'], true) : $requestFields;
        $scope = count($defVals) > 0 ? 'contentElement' : 'newsRecord';
        $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', $scope, $content['pid']);

        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang_module.xlf');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/content/creation.js');

        $this->view->assignMultiple([
            'content' => $content,
            'table' => $table,
            'textGenerationLibraries' => $this->aiSuiteContext->libraryService->prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [], $params['textGenerationLibraryKey'] ?? ''),
            'imageGenerationLibraries' => $this->aiSuiteContext->libraryService->prepareLibraries($librariesAnswer->getResponseData()['imageGenerationLibraries'] ?? [], $params['imageGenerationLibraryKey'] ?? ''),
            'additionalImageSettings' => $this->aiSuiteContext->libraryService->prepareAdditionalImageSettings($params['additionalImageSettings'] ?? ''),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'] ?? false,
            'promptTemplates' => $this->aiSuiteContext->promptTemplateService->getAllPromptTemplates(
                count($defVals) > 0 ? 'contentElement' : 'newsRecord',
                count($defVals) > 0 ? $params['defVals'][$table]['CType'] : '',
                $content['sysLanguageUid']
            ),
            'initialPrompt' => $params['initialPrompt'] ?? '',
            'availableTcaColumns' => $requestFields,
            'selectedTcaColumns' => $selectedTcaColumns,
            'defVals' => $defVals,
            'showMaxImageHint' => true,
            'uuid' => $this->uuidService->generateUuid(),
            'contentTypeTitle' => '0' === $content['CType'] ? 'news' : $content['CType'],
            'globalInstructions' => $globalInstructions,
        ]);

        return $this->view->renderResponse('Content/CreateContent');
    }

    /**
     * @throws AspectNotFoundException
     * @throws SiteNotFoundException
     * @throws RouteNotFoundException
     * @throws AiSuiteException
     */
    public function requestContentAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = (array) $request->getParsedBody();
        $content = json_decode($parsedBody['content'], true) ?? [];
        $selectedTcaColumns = $parsedBody['selectedTcaColumns'] ?? [];
        $availableTcaColumns = json_decode($parsedBody['availableTcaColumns'], true) ?? [];
        $defVals = json_decode($parsedBody['defVals'], true) ?? [];
        $textAi = !empty($parsedBody['libraries']['textGenerationLibrary']) ? $parsedBody['libraries']['textGenerationLibrary'] : '';
        $imageAi = !empty($parsedBody['libraries']['imageGenerationLibrary']) ? $parsedBody['libraries']['imageGenerationLibrary'] : '';

        $uriParams = [
            'edit' => [
                $request->getQueryParams()['table'] => [
                    $content['uidPid'] => 'new',
                ],
            ],
            'returnUrl' => $content['returnUrl'],
            'defVals' => $defVals,
            'initialPrompt' => $parsedBody['initialPrompt'],
            'selectedTcaColumns' => json_encode($selectedTcaColumns),
            'textGenerationLibraryKey' => $textAi,
            'imageGenerationLibraryKey' => $imageAi,
            'additionalImageSettings' => $parsedBody['additionalImageSettings'] ?? '',
        ];
        if ('tx_news_domain_model_news' === $request->getQueryParams()['table']) {
            $uriParams['recordType'] = '0';
            $uriParams['recordTable'] = 'tx_news_domain_model_news';
            $uriParams['pid'] = $content['pid'];
        }

        $regenerateActionUri = (string) $this->uriBuilder->buildUriFromRoute('ai_suite_record_edit', $uriParams);
        $content['regenerateReturnUrl'] = $regenerateActionUri;
        $this->view->assign('regenerateActionUri', $regenerateActionUri);

        try {
            $langIsoCode = $this->aiSuiteContext->siteService->getIsoCodeByLanguageId($content['sysLanguageUid'], $content['pid']);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->view->addFlashMessage(
                $exception->getMessage(),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.cannotSendRequest.title'),
                ContextualFeedbackSeverity::ERROR
            );
            $this->view->assign('error', true);

            return $this->view->renderResponse('Content/RequestContent');
        }

        $requestFields = [];
        foreach ($selectedTcaColumns as $type => $fields) {
            $requestFields[$type] = [
                'label' => $availableTcaColumns[$type]['label'],
            ];
            if (array_key_exists('foreignField', $availableTcaColumns[$type])) {
                $requestFields[$type]['foreignField'] = $availableTcaColumns[$type]['foreignField'];
            }
            if (array_key_exists('text', $fields)) {
                foreach ($fields['text'] as $fieldName => $renderType) {
                    if ('' !== $renderType) {
                        $requestFields[$type]['text'][$fieldName] = $availableTcaColumns[$type]['text'][$fieldName];
                    }
                }
            }
            if (array_key_exists('image', $fields)) {
                foreach ($fields['image'] as $fieldName => $renderType) {
                    if ('' !== $renderType) {
                        $requestFields[$type]['image'][$fieldName] = $availableTcaColumns[$type]['image'][$fieldName];
                    }
                }
            }
        }
        $scope = count($defVals) > 0 ? 'contentElement' : 'newsRecord';
        $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', $scope, $content['pid']);
        $models = $this->contentService->checkRequestModels($requestFields, ['text' => $textAi, 'image' => $imageAi]);
        $answer = $this->requestService->sendDataRequest(
            'createContentElement',
            [
                'request_fields' => json_encode($requestFields),
                'c_type' => $content['CType'],
                'additional_image_settings' => $parsedBody['additionalImageSettings'] ?? '',
                'uuid' => $parsedBody['uuid'] ?? '',
                'global_instructions' => $globalInstructions,
            ],
            $parsedBody['initialPrompt'],
            strtoupper($langIsoCode),
            $models
        );
        if ('Error' === $answer->getType()) {
            throw new AiSuiteException('Content/RequestContent', '', 'aiSuite.module.errorValidContentElementResponse.title', $answer->getResponseData()['message']);
        }
        $contentElementData = json_decode($answer->getResponseData()['contentElementData'], true);
        foreach ($contentElementData as $tableName => $fields) {
            foreach ($fields as $key => $field) {
                if (is_array($field) && array_key_exists('text', $field)) {
                    foreach ($field['text'] as $fieldName => $renderType) {
                        if (array_key_exists('rteConfig', $contentElementData[$tableName][$key]['text'][$fieldName])) {
                            $rteConfigData = is_array($contentElementData[$tableName][$key]['text'][$fieldName]['rteConfig'])
                                ? $contentElementData[$tableName][$key]['text'][$fieldName]['rteConfig']
                                : json_decode($contentElementData[$tableName][$key]['text'][$fieldName]['rteConfig'], true);
                            $contentElementData[$tableName][$key]['text'][$fieldName]['rteConfig'] = $this->richTextElementService->fetchRteConfig($rteConfigData);
                        }
                    }
                }
            }
        }
        $this->view->assignMultiple([
            'content' => $content,
            'contentElementData' => $contentElementData,
            'selectedTcaColumns' => $requestFields,
            'initialImageAi' => $imageAi,
            'uuid' => $this->uuidService->generateUuid(),
        ]);
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/content/validation.js');
        $this->view->addFlashMessage(
            $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.fetchingDataSuccessful.message'),
            $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.fetchingDataSuccessful.title')
        );

        return $this->view->renderResponse('Content/RequestContent');
    }

    /**
     * @throws AiSuiteException
     * @throws InsufficientFolderAccessPermissionsException
     */
    public function saveContentAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = (array) $request->getParsedBody();
        $content = json_decode($parsedBody['content'], true) ?? [];
        $selectedTcaColumns = json_decode($parsedBody['selectedTcaColumns'], true) ?? [];
        $contentElementTextData = $parsedBody['contentElementData'] ?? [];
        $contentElementImageData = [];

        if (array_key_exists('fileData', $parsedBody)) {
            foreach ($parsedBody['fileData']['contentElementData'] as $table => $fieldsArray) {
                foreach ($fieldsArray as $key => $fields) {
                    foreach ($fields as $fieldName => $fieldData) {
                        if (array_key_exists('newImageUrl', $fieldData)) {
                            $contentElementImageData[$table][$key][$fieldName]['newImageUrl'] = $fieldData['newImageUrl'];
                            $imageTitle = !empty($fieldData['imageTitle']) ? $fieldData['imageTitle'] : '';
                            $imageTitle = !empty($fieldData['imageTitleFreeText']) ? $fieldData['imageTitleFreeText'] : $imageTitle;
                            $contentElementImageData[$table][$key][$fieldName]['imageTitle'] = $imageTitle;
                        }
                    }
                }
            }
        }

        $contentElementIrreFields = [];
        foreach ($selectedTcaColumns as $table => $fields) {
            if (array_key_exists('foreignField', $fields)) {
                $contentElementIrreFields[$table] = $fields['foreignField'];
            }
        }
        $this->pageContentFactory->createContentElementData($content, $contentElementTextData, $contentElementImageData, $contentElementIrreFields);

        return new RedirectResponse($content['returnUrl']);
    }

    public function ckeditorLibrariesAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::RTE_CONTENT, 'editContent', ['text']);

        if ('Error' === $librariesAnswer->getType()) {
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => false,
                        'output' => '<div class="alert alert-danger" role="alert">'.$this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorFetchingLibraries.title').'</div>',
                    ]
                )
            );

            return $response;
        }
        $pageId = ((array) $request->getParsedBody())['pageId'] ?? 0;
        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => true,
                    'output' => [
                        'libraries' => $this->aiSuiteContext->libraryService->prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries']),
                        'promptTemplates' => $this->aiSuiteContext->promptTemplateService->getAllPromptTemplates('editContent'),
                        'globalInstructions' => $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'editContent', (int) $pageId),
                        'uuid' => $this->uuidService->generateUuid(),
                    ],
                ]
            )
        );

        return $response;
    }

    public function ckeditorEasyLanguageLibrariesAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::RTE_CONTENT, 'editContent', ['text']);

        if ('Error' === $librariesAnswer->getType()) {
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => false,
                        'output' => '<div class="alert alert-danger" role="alert">'.$this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorFetchingLibraries.title').'</div>',
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
            (string) json_encode(
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

    public function ckeditorRequestAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $parsedBody = (array) $request->getParsedBody();
        $pageUid = $parsedBody['pageId'] ?? 0;
        $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'editContent', (int) $pageUid);
        $answer = $this->requestService->sendDataRequest(
            'editContent',
            [
                'uuid' => $parsedBody['uuid'],
                'selectedContent' => $parsedBody['selectedContent'] ?? '',
                'wholeContent' => $parsedBody['wholeContent'] ?? '',
                'type' => $parsedBody['type'] ?? '',
                'globalInstructions' => $globalInstructions,
            ],
            $parsedBody['prompt'] ?? '',
            $parsedBody['languageCode'] ?? 'en',
            [
                'text' => $parsedBody['textModel'],
            ],
        );
        if ('Error' === $answer->getType()) {
            $this->logError($answer->getResponseData()['message'], $response, 503);

            return $response;
        }
        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => true,
                    'output' => $answer->getResponseData()['editContentResult'],
                ]
            )
        );

        return $response;
    }
}
