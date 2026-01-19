<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Exception\FetchedContentFailedException;
use AutoDudes\AiSuite\Exception\UnableToFetchNewsRecordException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MetadataService
{
    protected PagesRepository $pagesRepository;
    protected PageRepository $pageRepository;
    protected RequestFactory $requestFactory;
    protected RequestsRepository $requestsRepository;
    protected ResourceFactory $resourceFactory;
    protected BackendUserService $backendUserService;
    protected TranslationService $translationService;
    protected SiteService $siteService;
    protected BasicAuthService $basicAuthService;
    protected SendRequestService $sendRequestService;
    protected GlobalInstructionService $globalInstructionService;
    protected UuidService $uuidService;
    protected LoggerInterface $logger;

    protected array $pageMetadataColumns = [
        'title',
        'nav_title',
        'subtitle',
        'seo_title',
        'description',
        'og_title',
        'og_description',
        'twitter_title',
        'twitter_description',
        'abstract',
    ];

    public function __construct(
        PagesRepository $pagesRepository,
        PageRepository $pageRepository,
        RequestFactory $requestFactory,
        RequestsRepository $requestsRepository,
        ResourceFactory $resourceFactory,
        BackendUserService $backendUserService,
        TranslationService $translationService,
        SiteService $siteService,
        BasicAuthService $basicAuthService,
        SendRequestService $sendRequestService,
        GlobalInstructionService $globalInstructionService,
        UuidService $uuidService,
        LoggerInterface $logger
    ) {
        $this->pagesRepository = $pagesRepository;
        $this->pageRepository = $pageRepository;
        $this->requestFactory = $requestFactory;
        $this->requestsRepository = $requestsRepository;
        $this->resourceFactory = $resourceFactory;
        $this->backendUserService = $backendUserService;
        $this->translationService = $translationService;
        $this->siteService = $siteService;
        $this->basicAuthService = $basicAuthService;
        $this->sendRequestService = $sendRequestService;
        $this->globalInstructionService = $globalInstructionService;
        $this->uuidService = $uuidService;
        $this->logger = $logger;
    }

    /**
     * @throws FetchedContentFailedException
     * @throws UnableToFetchNewsRecordException
     * @throws UnableToLinkToPageException
     * @throws Exception
     */
    public function fetchContent(ServerRequestInterface $request): string
    {
        if ($request->getParsedBody()['table'] === 'tx_news_domain_model_news') {
            return $this->fetchContentOfNewsArticle(
                (int)$request->getParsedBody()['id'],
                (int)$request->getParsedBody()['newsDetailPlugin']
            );
        } elseif ($request->getParsedBody()['table'] === 'sys_file_metadata' || $request->getParsedBody()['table'] === 'sys_file_reference') {
            return $this->getFileContent((int)$request->getParsedBody()['sysFileId']);
        } else {
            $previewUrl = $this->getPreviewUrl((int)$request->getParsedBody()['pageId']);
            return $this->fetchContentFromUrl($previewUrl);
        }
    }

    /**
     * @throws FileDoesNotExistException
     */
    public function getFileContent(int $sysFileId): string
    {
        $file = $this->resourceFactory->getFileObject($sysFileId);
        try {
            $data = $file->getContents();
            if (empty($data)) {
                $decodedIdentifier = urldecode($file->getIdentifier());
                $file->setIdentifier($decodedIdentifier);
                $data = $file->getContents();
            }
        } catch (\Throwable $e) {
            $decodedIdentifier = urldecode($file->getIdentifier());
            $file->setIdentifier($decodedIdentifier);
            $data = $file->getContents();
        }
        return 'data:' . $file->getMimeType() . ';base64,' . base64_encode($data);
    }

    public function getFilename(int $sysFileId): string
    {
        $file = $this->resourceFactory->getFileObject($sysFileId);
        try {
            return $file->getName();
        } catch (\Throwable $e) {
            return "";
        }
    }

    /**
     * @throws Exception
     * @throws UnableToFetchNewsRecordException
     * @throws UnableToLinkToPageException
     */
    protected function fetchContentOfNewsArticle(int $newsId, int $newsDetailPluginId): string
    {
        $additionalQueryParameters = [
            'tx_news_pi1[action]' => 'detail',
            'tx_news_pi1[controller]' => 'News',
            'tx_news_pi1[news]' => $newsId
        ];
        $previewUrl = $this->getPreviewUrl($newsDetailPluginId, $additionalQueryParameters);
        return $this->fetchContentFromUrl($previewUrl);
    }

    /**
     * @throws FetchedContentFailedException
     */
    public function fetchContentFromUrl(string $previewUrl): string
    {
        try {
            return $this->getContentFromPreviewUrl($previewUrl);
        } catch (FetchedContentFailedException $e) {
            $previewUrl = rtrim($previewUrl, '/');
            return $this->getContentFromPreviewUrl($previewUrl);
        }
    }

    /**
     * @throws FetchedContentFailedException
     */
    public function getContentFromPreviewUrl(string $previewUrl): string
    {
        $options = [];
        if (array_key_exists('be_typo_user', $_COOKIE)) {
            $options = [
                'headers' => ['Cookie' => 'be_typo_user=' . $_COOKIE['be_typo_user']],
            ];
        }

        $basicAuth = $this->basicAuthService->getBasicAuth();
        if (!empty($basicAuth)) {
            if (!isset($options['headers'])) {
                $options['headers'] = [];
            }
            $options['headers']['Authorization'] = 'Basic ' . $basicAuth;
        }

        $response = $this->requestFactory->request($previewUrl, 'GET', $options);
        $fetchedContent = $response->getBody()->getContents();

        if (empty($fetchedContent)) {
            throw new FetchedContentFailedException($this->translationService->translate('AiSuite.fetchContentFailed'));
        }
        return $fetchedContent;
    }

    /**
     * @throws UnableToLinkToPageException
     * @throws UnableToFetchNewsRecordException
     */
    public function getPreviewUrl(int $pageId, array $additionalQueryParameters = []): string
    {
        $page = $this->pageRepository->getPage($pageId);
        if ($page['is_siteroot'] === 1 && $page['l10n_parent'] > 0) {
            $pageId = $page['l10n_parent'];
        }
        $additionalGetVars = '_language=' . $page['sys_language_uid'];
        foreach ($additionalQueryParameters as $key => $value) {
            $additionalGetVars .= '&' . $key . '=' . $value;
        }

        $previewUriBuilder = PreviewUriBuilder::create($pageId);
        $previewUri = $previewUriBuilder
            ->withLanguage($page['sys_language_uid'])
            ->withAdditionalQueryParameters($additionalGetVars)
            ->buildUri();

        if ($previewUri === null) {
            if (array_key_exists('tx_news_pi1[news]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[action]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[controller]', $additionalQueryParameters)) {
                throw new UnableToFetchNewsRecordException($this->translationService->translate('AiSuite.unableToFetchNewsRecord', [$additionalQueryParameters['tx_news_pi1[news]'], $pageId]));
            }
            throw new UnableToLinkToPageException($this->translationService->translate('AiSuite.unableToLinkToPage', [$pageId, $page['sys_language_uid']]));
        }
        return $this->siteService->buildAbsoluteUri($previewUri);
    }

    public function getMetadataColumns(): array
    {
        $metadataColumns = [
            'seo_title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'
        ];
        return $this->getAvailableColumns($metadataColumns, 'pages');
    }

    public function getFileMetadataColumns(): array
    {
        $metadataColumns = [
            'title', 'alternative'
        ];
        return $this->getAvailableColumns($metadataColumns, 'sys_file_reference');
    }

    public function getPageMetadataForTranslation(int $pageId): array
    {
        $metadataFields = $this->collectPageMetadataFields($pageId);

        return [
            'count' => count($metadataFields),
            'fields' => array_keys($metadataFields),
            'hasTranslatableContent' => !empty($metadataFields),
        ];
    }

    public function collectPageMetadataFields(int $pageId): array
    {
        $formData = $this->getFormData($pageId);

        $metadataFields = [];

        foreach ($this->pageMetadataColumns as $column) {
            if (isset($formData['databaseRow'][$column])) {
                $this->translationService->checkSingleField($formData, $column, $metadataFields);
            }
        }

        return $metadataFields;
    }

    public function hasFilePermissions(int $fileUid): bool
    {
        if ($this->backendUserService->getBackendUser()->isAdmin()) {
            return true;
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);

            return $file->isIndexed()
                && $file->checkActionPermission('editMeta')
                && $this->backendUserService->getBackendUser()->check('tables_modify', 'sys_file_metadata');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getFormData(int $pageId): array
    {
        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class);
        $formDataCompilerInput = [
            'request' => $GLOBALS['TYPO3_REQUEST'],
            'tableName' => 'pages',
            'vanillaUid' => $pageId,
            'command' => 'edit',
            'returnUrl' => '',
            'defaultValues' => [],
        ];

        return $formDataCompiler->compile($formDataCompilerInput, GeneralUtility::makeInstance(TcaDatabaseRecord::class));
    }

    private function getAvailableColumns(array $columns, string $xlfPrefix): array
    {
        $availableColumns = [];
        foreach ($columns as $columnName) {
            if ($this->backendUserService->getBackendUser()->check('non_exclude_fields', $xlfPrefix . ':' . $columnName)) {
                $availableColumns[$columnName] = $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:massActionSection.' . $xlfPrefix . '.' . $columnName);
            }
        }
        return $availableColumns;
    }

    public function generateAndSaveMetadataDirectly(FileInterface $file, int $fileMetadataUid, array $extConf): void
    {
        try {
            $fieldsToGenerate = [];
            if ((bool)$extConf['metadataAutogenerateTitle']) {
                $fieldsToGenerate[] = 'title';
            }
            if ((bool)$extConf['metadataAutogenerateAlternative']) {
                $fieldsToGenerate[] = 'alternative';
            }

            $fileContent = $this->getFileContent($file->getUid());

            $folder = $file->getParentFolder();
            $folderCombinedIdentifier = $folder->getCombinedIdentifier();

            $globalInstructions = $this->globalInstructionService->buildGlobalInstruction(
                'files',
                'metadata',
                null,
                $folderCombinedIdentifier
            );
            $globalInstructionsOverride = $this->globalInstructionService->checkOverridePredefinedPrompt(
                'files',
                'metadata',
                [$folderCombinedIdentifier]
            );

            $textAiModel = $extConf['metadataAutogenerateModel'] ?? '';

            $availableSourceLanguages = $this->siteService->getAvailableLanguages(true, 0, true);
            $firstLanguageKey = array_key_first($availableSourceLanguages);
            $languageParts = explode('__', $firstLanguageKey);

            $datamap = [
                'sys_file_metadata' => [
                    $fileMetadataUid => []
                ]
            ];

            foreach ($fieldsToGenerate as $fieldName) {
                try {
                    $uuid = $this->uuidService->generateUuid();

                    $answer = $this->sendRequestService->sendDataRequest(
                        'createMetadata',
                        [
                            'uuid' => $uuid,
                            'field_label' => $fieldName,
                            'request_content' => $fileContent,
                            'global_instructions' => $globalInstructions,
                            'override_predefined_prompt' => $globalInstructionsOverride,
                        ],
                        '',
                        $languageParts[0],
                        [
                            'text' => $textAiModel,
                        ]
                    );

                    if ($answer->getType() === 'Error') {
                        $this->logger->error('Error generating metadata for field ' . $fieldName . ' of file ' . $file->getUid() . ': ' . $answer->getResponseData()['message']);
                        continue;
                    }

                    $metadataResult = $answer->getResponseData()['metadataResult'] ?? [];
                    if (!empty($metadataResult) && is_array($metadataResult)) {
                        $generatedValue = $metadataResult[0] ?? '';
                        if (!empty($generatedValue)) {
                            $datamap['sys_file_metadata'][$fileMetadataUid][$fieldName] = $generatedValue;
                            $this->flashMessage(
                                $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.generatedField.message', [$fieldName]),
                                $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.generatedField.title'),
                                ContextualFeedbackSeverity::OK
                            );
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error generating metadata for field ' . $fieldName . ' of file ' . $file->getUid() . ': ' . $e->getMessage());
                    $this->flashMessage(
                        $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.errorGeneratingField.message', [$fieldName]),
                        $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.errorGeneratingField.title'),
                        ContextualFeedbackSeverity::ERROR
                    );
                }
            }

            if (!empty($datamap['sys_file_metadata'][$fileMetadataUid])) {
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start($datamap, []);
                $dataHandler->process_datamap();

                if (count($dataHandler->errorLog) > 0) {
                    $this->logger->error('Error saving metadata for file ' . $file->getUid() . ': ' . implode(', ', $dataHandler->errorLog));
                    $this->flashMessage(
                        $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.errorSavingFile.message', [$file->getName()]),
                        $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.errorSavingFile.title'),
                        ContextualFeedbackSeverity::ERROR
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in generateAndSaveMetadataDirectly for file ' . $file->getUid() . ': ' . $e->getMessage());
            $this->flashMessage(
                $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.unexpectedErrorAutoGeneration.message', [$file->getName()]),
                $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.flashMessage.metadata.unexpectedErrorAutoGeneration.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
    }

    public function flashMessage(string $message, string $title, ContextualFeedbackSeverity $severity): void
    {
        $message = GeneralUtility::makeInstance(FlashMessage::class,
            $message,
            $title,
            $severity,
            true);
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);
    }
}
