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

use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Exception\AiSuiteException;
use AutoDudes\AiSuite\Factory\PageContentFactory;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\ContentService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\RichTextElementService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SessionService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentController extends AbstractBackendController
{
    protected UuidService $uuidService;
    protected ContentService $contentService;
    protected Context $context;
    protected PageContentFactory $pageContentFactory;
    protected LoggerInterface $logger;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        BackendUserService $backendUserService,
        LibraryService $libraryService,
        PromptTemplateService $promptTemplateService,
        SiteService $siteService,
        TranslationService $translationService,
        SessionService $sessionService,
        UuidService $uuidService,
        ContentService     $contentService,
        Context            $context,
        PageContentFactory $pageContentFactory,
        LoggerInterface    $logger,
        ResponseFactory $responseFactory
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $iconFactory,
            $uriBuilder,
            $pageRenderer,
            $flashMessageService,
            $requestService,
            $backendUserService,
            $libraryService,
            $promptTemplateService,
            $siteService,
            $translationService,
            $sessionService,
            $responseFactory
        );
        $this->uuidService = $uuidService;
        $this->contentService = $contentService;
        $this->context = $context;
        $this->pageContentFactory = $pageContentFactory;
        $this->logger = $logger;
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->initialize($request);
            $identifier = $request->getAttribute('route')->getOption('_identifier');
            return match ($identifier) {
                'ai_suite_content_request' => $this->requestContentAction($request),
                'ai_suite_content_save' => $this->saveContentAction($request),
                default => $this->createContentAction($request),
            };
        } catch (AiSuiteException $e) {
            if(!empty($e->getReturnUrl())) {
                return new RedirectResponse($e->getReturnUrl());
            }
            $assignMultiple = ['error' => true];
            $this->view->addFlashMessage(
                !empty($e->getMessage()) ? $e->getMessage() : $this->translationService->translate('' . $e->getMessageKey()),
                $this->translationService->translate('' . $e->getTitleKey()),
                AbstractMessage::ERROR
            );
            return $this->htmlResponse(
                $this->view->setContent(
                    $this->renderView(
                        $e->getTemplate(),
                        $assignMultiple
                    )
                )->renderContent()
            );
        } catch (\Throwable $e) {
            $assignMultiple = ['error' => true];
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->translationService->translate('aiSuite.error.default.title'),
                AbstractMessage::ERROR
            );
            return $this->htmlResponse(
                $this->view->setContent(
                    $this->renderView(
                        'Content/CreateContent',
                        $assignMultiple
                    )
                )->renderContent()
            );
        }
    }

    public function createContentAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $table = array_key_first($params['edit']);
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::CONTENT, 'createContentElement', ['text', 'image']);
        $defVals = [];
        if (array_key_exists('defVals', $params)) {
            $defVals = $params['defVals'];
            $content = [
                'sysLanguageUid' => (int)$defVals[$table]['sys_language_uid'],
                'colPos' => (int)$defVals[$table]['colPos'],
                'pid' => (int)$defVals[$table]['pid'],
                'CType' => $defVals[$table]['CType'] ?? 'text'
            ];
            $content['containerParentUid'] = isset($params['defVals'][$table]['tx_container_parent']) ? (int)$params['defVals'][$table]['tx_container_parent'] : 0;
        } else {
            $content['pid'] = (int)$params['pid'];
            $content['CType'] = $params['recordType'] ?? '';
            $content['sysLanguageUid'] = !empty($params['sysLanguageUid']) ? (int)$params['sysLanguageUid'] : 0;
        }
        $content['returnUrl'] = $params['returnUrl'] ?? '';
        if (array_key_exists('edit', $params) && array_key_exists($table, $params['edit'])) {
            $content['uidPid'] = key($params['edit'][$table]) ?? $params['id'];
        } else {
            $content['uidPid'] = $params['id'];
        }

        $requestFields = $this->contentService->fetchRequestFields($request, $defVals, $content['CType'], $content['pid'], $table);
        $selectedTcaColumns = isset($params['selectedTcaColumns']) ? json_decode($params['selectedTcaColumns'], true) : $requestFields;

        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/AiSuite/Content/Creation');

        $assignMultiple = [
            'content' => $content,
            'table' => $table,
            'textGenerationLibraries' => $this->libraryService->prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries'], $params['textGenerationLibraryKey'] ?? ''),
            'imageGenerationLibraries' => $this->libraryService->prepareLibraries($librariesAnswer->getResponseData()['imageGenerationLibraries'], $params['imageGenerationLibraryKey'] ?? ''),
            'additionalImageSettings' => $this->libraryService->prepareAdditionalImageSettings($params['additionalImageSettings'] ?? ''),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
            'promptTemplates' => $this->promptTemplateService->getAllPromptTemplates(
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
            'contentTypeTitle' => $content['CType'] === '0' ? 'news' : $content['CType']
        ];
        return $this->htmlResponse(
            $this->view->setContent(
                $this->renderView(
                    'Content/CreateContent',
                    $assignMultiple
                )
            )->renderContent()
        );
    }

    /**
     * @throws AspectNotFoundException
     * @throws SiteNotFoundException
     * @throws RouteNotFoundException
     * @throws AiSuiteException
     */
    public function requestContentAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
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
        if ($request->getQueryParams()['table'] === 'tx_news_domain_model_news') {
            $uriParams['recordType'] = '0';
            $uriParams['recordTable'] = 'tx_news_domain_model_news';
            $uriParams['pid'] = $content['pid'];
        }

        $regenerateActionUri = (string)$this->uriBuilder->buildUriFromRoute('ai_suite_record_edit', $uriParams);
        $content['regenerateReturnUrl'] = $regenerateActionUri;
        $assignMultiple = [
            'regenerateActionUri' => $regenerateActionUri
        ];

        try {
//            $site = $this->siteService->getSiteByPageId($content['pid']);
//            $siteLanguage = $site->getLanguageById($content['sysLanguageUid']);
            $langIsoCode = $this->siteService->getIsoCodeByLanguageId($content['sysLanguageUid'], $content['pid']);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->view->addFlashMessage(
                $exception->getMessage(),
                $this->translationService->translate('aiSuite.module.cannotSendRequest.title'),
                AbstractMessage::ERROR
            );
            $assignMultiple['error'] = true;
            return $this->htmlResponse(
                $this->view->setContent(
                    $this->renderView(
                        'Content/RequestContent',
                        $assignMultiple
                    )
                )->renderContent()
            );
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
                    if ($renderType !== '') {
                        $requestFields[$type]['text'][$fieldName] = $availableTcaColumns[$type]['text'][$fieldName];
                    }
                }
            }
            if (array_key_exists('image', $fields)) {
                foreach ($fields['image'] as $fieldName => $renderType) {
                    if ($renderType !== '') {
                        $requestFields[$type]['image'][$fieldName] = $availableTcaColumns[$type]['image'][$fieldName];
                    }
                }
            }
        }
        $models = $this->contentService->checkRequestModels($requestFields, ['text' => $textAi, 'image' => $imageAi]);
        $answer = $this->requestService->sendDataRequest(
            'createContentElement',
            [
                'request_fields' => json_encode($requestFields),
                'c_type' => $content['CType'],
                'additional_image_settings' => $parsedBody['additionalImageSettings'] ?? '',
                'uuid' => $parsedBody['uuid'] ?? '',
            ],
            $parsedBody['initialPrompt'],
            strtoupper($langIsoCode),
            $models
        );
        if ($answer->getType() === 'Error') {
            throw new AiSuiteException('Content/RequestContent', '', 'aiSuite.module.errorValidContentElementResponse.title', $answer->getResponseData()['message']);
        }
        $contentElementData = json_decode($answer->getResponseData()['contentElementData'], true);
        $counter = 0;
        foreach ($contentElementData as $tableName => $fields) {
            foreach ($fields as $key => $field) {
                if (is_array($field) && array_key_exists('text', $field)) {
                    foreach ($field['text'] as $fieldName => $renderType) {
                        if (array_key_exists('rteConfig', $contentElementData[$tableName][$key]['text'][$fieldName])) {
                            $rteConfigData = is_array($contentElementData[$tableName][$key]['text'][$fieldName]['rteConfig'])
                                ? $contentElementData[$tableName][$key]['text'][$fieldName]['rteConfig']
                                : json_decode($contentElementData[$tableName][$key]['text'][$fieldName]['rteConfig'], true);
                            $richTextElementService = GeneralUtility::makeInstance(RichTextElementService::class, $rteConfigData);
                            $contentElementData[$tableName][$key]['text'][$fieldName]['rteConfig'] = $richTextElementService->fetchRteConfig($counter++);
                        }
                    }
                }
            }
        }

        $assignMultiple = array_merge($assignMultiple,
            [
                'content' => $content,
                'contentElementData' => $contentElementData,
                'selectedTcaColumns' => $requestFields,
                'initialImageAi' => $imageAi,
                'uuid' => $this->uuidService->generateUuid(),
            ]
        );
//        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/content/validation.js');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/AiSuite/Content/Validation');
        $this->view->addFlashMessage(
            $this->translationService->translate('aiSuite.module.fetchingDataSuccessful.message'),
            $this->translationService->translate('aiSuite.module.fetchingDataSuccessful.title')
        );
        return $this->htmlResponse(
            $this->view->setContent(
                $this->renderView(
                    'Content/RequestContent',
                    $assignMultiple
                )
            )->renderContent()
        );
    }

    /**
     * @throws AiSuiteException
     * @throws InsufficientFolderAccessPermissionsException
     */
    public function saveContentAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
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
}
