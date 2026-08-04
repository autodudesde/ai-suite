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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
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
    public const SUPPORTED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /** @var list<string> */
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
        protected readonly PagesRepository $pagesRepository,
        protected readonly PageRepository $pageRepository,
        protected readonly RequestFactory $requestFactory,
        protected readonly RequestsRepository $requestsRepository,
        protected readonly ResourceFactory $resourceFactory,
        protected readonly BackendUserService $backendUserService,
        protected readonly TranslationService $translationService,
        protected readonly LocalizationService $localizationService,
        protected readonly SiteService $siteService,
        protected readonly BasicAuthService $basicAuthService,
        protected readonly SendRequestService $sendRequestService,
        protected readonly GlobalInstructionService $globalInstructionService,
        protected readonly UuidService $uuidService,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws FetchedContentFailedException
     * @throws UnableToFetchNewsRecordException
     * @throws UnableToLinkToPageException
     * @throws Exception
     */
    public function fetchContent(ServerRequestInterface $request): string
    {
        /** @var array<string, mixed> $parsedBody */
        $parsedBody = (array) $request->getParsedBody();
        $table = (string) ($parsedBody['table'] ?? '');
        if ('tx_news_domain_model_news' === $table) {
            $newsDetailPluginId = (int) ($parsedBody['newsDetailPlugin'] ?? 0);
            if ($newsDetailPluginId <= 0) {
                throw new UnableToFetchNewsRecordException(
                    $this->localizationService->translate('aiSuite.error.news.missingDetailPlugin')
                );
            }

            return $this->fetchContentOfNewsArticle(
                (int) ($parsedBody['id'] ?? 0),
                $newsDetailPluginId
            );
        }
        if ('sys_file_metadata' === $table || 'sys_file_reference' === $table) {
            return $this->getFileContent((int) ($parsedBody['sysFileId'] ?? 0));
        }
        $previewUrl = $this->getPreviewUrl((int) ($parsedBody['pageId'] ?? 0));

        return $this->fetchContentFromUrl($previewUrl);
    }

    /**
     * @throws FileDoesNotExistException
     * @throws FetchedContentFailedException
     */
    public function getFileContent(int $sysFileId): string
    {
        $file = $this->resourceFactory->getFileObject($sysFileId);

        if (!in_array($file->getMimeType(), self::SUPPORTED_IMAGE_MIME_TYPES, true)) {
            throw new FetchedContentFailedException(
                $this->localizationService->translate('aiSuite.file.unsupportedImageMimeType', [$file->getMimeType()])
            );
        }

        try {
            $data = $file->getContents();
            if (empty($data)) {
                $file = $this->reloadFileFromStorage($file);
                $data = $file->getContents();
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Could not read file contents directly, reloading from storage', [
                'sysFileId' => $sysFileId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            $file = $this->reloadFileFromStorage($file);
            $data = $file->getContents();
        }

        if (empty($data)) {
            throw new FetchedContentFailedException(
                $this->localizationService->translate('aiSuite.file.emptyFileContent')
            );
        }

        $detectedMimeType = $this->detectSupportedImageMimeType($data);
        if (null === $detectedMimeType) {
            $this->logger->warning('File content is not a supported image (declared MIME type does not match actual bytes)', [
                'sysFileId' => $sysFileId,
                'declaredMimeType' => $file->getMimeType(),
            ]);

            throw new FetchedContentFailedException(
                $this->localizationService->translate('aiSuite.file.unsupportedImageMimeType', [$file->getMimeType()])
            );
        }

        return 'data:'.$detectedMimeType.';base64,'.base64_encode($data);
    }

    public function getFilename(int $sysFileId): string
    {
        if ($sysFileId <= 0) {
            return '';
        }

        try {
            return $this->resourceFactory->getFileObject($sysFileId)->getName();
        } catch (\Throwable $e) {
            $this->logger->warning('Could not resolve filename for sys file', [
                'sysFileId' => $sysFileId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
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
                'headers' => ['Cookie' => 'be_typo_user='.$_COOKIE['be_typo_user']],
            ];
        }

        $basicAuth = $this->basicAuthService->getBasicAuth();
        if (!empty($basicAuth)) {
            if (!isset($options['headers'])) {
                $options['headers'] = [];
            }
            $options['headers']['Authorization'] = 'Basic '.$basicAuth;
        }

        $options['http_errors'] = false;

        $response = $this->requestFactory->request($previewUrl, 'GET', $options);
        $statusCode = $response->getStatusCode();
        $fetchedContent = $response->getBody()->getContents();

        if ($statusCode >= 400) {
            $this->logger->warning('Preview URL returned an HTTP error status', [
                'previewUrl' => $previewUrl,
                'statusCode' => $statusCode,
            ]);

            throw new FetchedContentFailedException($this->localizationService->translate('aiSuite.fetchContentFailed'));
        }

        if (empty($fetchedContent)) {
            throw new FetchedContentFailedException($this->localizationService->translate('aiSuite.fetchContentFailed'));
        }

        if (!$this->isPlausiblePageContent($fetchedContent)) {
            $this->logger->warning('Preview URL returned implausible content (possible error/handler page)', [
                'previewUrl' => $previewUrl,
                'statusCode' => $statusCode,
                'contentLength' => strlen(trim($fetchedContent)),
            ]);

            throw new FetchedContentFailedException($this->localizationService->translate('aiSuite.fetchContentInvalid'));
        }

        return $fetchedContent;
    }

    /**
     * @param array<string, mixed> $additionalQueryParameters
     *
     * @throws UnableToLinkToPageException
     * @throws UnableToFetchNewsRecordException
     */
    public function getPreviewUrl(int $pageId, array $additionalQueryParameters = []): string
    {
        $page = $this->pageRepository->getPage($pageId);
        if (1 === $page['is_siteroot'] && $page['l10n_parent'] > 0) {
            $pageId = $page['l10n_parent'];
        }
        $additionalGetVars = '_language='.$page['sys_language_uid'];
        foreach ($additionalQueryParameters as $key => $value) {
            $additionalGetVars .= '&'.$key.'='.$value;
        }

        $previewUriBuilder = PreviewUriBuilder::create($pageId);
        $previewUri = $previewUriBuilder
            ->withLanguage($page['sys_language_uid'])
            ->withAdditionalQueryParameters($additionalGetVars)
            ->buildUri()
        ;

        if (null === $previewUri) {
            if (array_key_exists('tx_news_pi1[news]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[action]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[controller]', $additionalQueryParameters)) {
                throw new UnableToFetchNewsRecordException($this->localizationService->translate('aiSuite.unableToFetchNewsRecord', [$additionalQueryParameters['tx_news_pi1[news]'], $pageId]));
            }

            throw new UnableToLinkToPageException($this->localizationService->translate('aiSuite.unableToLinkToPage', [$pageId, $page['sys_language_uid']]));
        }

        return $this->siteService->buildAbsoluteUri($previewUri);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadataColumns(): array
    {
        $metadataColumns = [
            'seo_title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description', 'abstract',
        ];

        return $this->getAvailableColumns($metadataColumns, 'pages');
    }

    /**
     * @return array<string, mixed>
     */
    public function getFileMetadataColumns(): array
    {
        $metadataColumns = [
            'title', 'alternative', 'description',
        ];

        return $this->getAvailableColumns($metadataColumns, 'sys_file_reference');
    }

    /**
     * @return array<string, mixed>
     */
    public function getPageMetadataForTranslation(int $pageId): array
    {
        $metadataFields = $this->collectPageMetadataFields($pageId);

        return [
            'count' => count($metadataFields),
            'fields' => array_keys($metadataFields),
            'hasTranslatableContent' => !empty($metadataFields),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $extConf
     */
    public function generateAndSaveMetadataDirectly(FileInterface $file, int $fileMetadataUid, array $extConf): void
    {
        try {
            $fieldsToGenerate = [];
            if ((bool) $extConf['metadataAutogenerateTitle']) {
                $fieldsToGenerate[] = 'title';
            }
            if ((bool) $extConf['metadataAutogenerateAlternative']) {
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
            $languageParts = explode('__', (string) $firstLanguageKey);

            $datamap = [
                'sys_file_metadata' => [
                    $fileMetadataUid => [],
                ],
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
                            'custom_prompt' => trim((string) ($extConf['metadataAutogeneratePrompt'] ?? '')),
                        ],
                        '',
                        $languageParts[0],
                        [
                            'text' => $textAiModel,
                        ]
                    );

                    if ('Error' === $answer->getType()) {
                        $this->logger->error('Error generating metadata for field '.$fieldName.' of file '.$file->getUid().': '.$answer->getResponseData()['message']);

                        continue;
                    }

                    $metadataResult = $answer->getResponseData()['metadataResult'] ?? [];
                    if (!empty($metadataResult) && is_array($metadataResult)) {
                        $generatedValue = $metadataResult[0] ?? '';
                        if (!empty($generatedValue)) {
                            $datamap['sys_file_metadata'][$fileMetadataUid][$fieldName] = $generatedValue;
                            $this->flashMessage(
                                $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.generatedField.message', [$fieldName]),
                                $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.generatedField.title'),
                                ContextualFeedbackSeverity::OK
                            );
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error generating metadata for field '.$fieldName.' of file '.$file->getUid().': '.$e->getMessage());
                    $this->flashMessage(
                        $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.errorGeneratingField.message', [$fieldName]),
                        $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.errorSavingFile.title'),
                        ContextualFeedbackSeverity::ERROR
                    );
                }
            }

            if (!empty($datamap['sys_file_metadata'][$fileMetadataUid])) {
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start($datamap, []);
                $dataHandler->process_datamap();

                if (count($dataHandler->errorLog) > 0) {
                    $this->logger->error('Error saving metadata for file '.$file->getUid().': '.implode(', ', $dataHandler->errorLog));
                    $this->flashMessage(
                        $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.errorSavingFile.message', [$file->getName()]),
                        $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.errorSavingFile.title'),
                        ContextualFeedbackSeverity::ERROR
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in generateAndSaveMetadataDirectly for file '.$file->getUid().': '.$e->getMessage());
            $this->flashMessage(
                $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.unexpectedErrorAutoGeneration.message', [$file->getName()]),
                $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.errorSavingFile.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
    }

    public function flashMessage(string $message, string $title, ContextualFeedbackSeverity $severity): void
    {
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity,
            true
        );
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);
    }

    protected function detectSupportedImageMimeType(string $data): ?string
    {
        if (str_starts_with($data, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($data, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }
        if (str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a')) {
            return 'image/gif';
        }
        if (str_starts_with($data, 'RIFF') && 'WEBP' === substr($data, 8, 4)) {
            return 'image/webp';
        }

        return null;
    }

    protected function isPlausiblePageContent(string $content): bool
    {
        $trimmed = trim($content);
        if (strlen($trimmed) < $this->getMinPlausiblePageContentLength()) {
            return false;
        }

        $lower = strtolower($trimmed);

        return str_contains($lower, '<html') || str_contains($lower, '<body');
    }

    protected function getMinPlausiblePageContentLength(): int
    {
        try {
            $extConf = $this->extensionConfiguration->get('ai_suite');

            return (int) ($extConf['minPlausiblePageContentLength'] ?? 100);
        } catch (\Exception $e) {
            $this->logger->warning('Could not read extension configuration for minPlausiblePageContentLength, using default of 100', [
                'error' => $e->getMessage(),
            ]);

            return 100;
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
            'tx_news_pi1[news]' => $newsId,
        ];
        $previewUrl = $this->getPreviewUrl($newsDetailPluginId, $additionalQueryParameters);

        return $this->fetchContentFromUrl($previewUrl);
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @throws FileDoesNotExistException
     */
    private function reloadFileFromStorage(FileInterface $file): FileInterface
    {
        $decodedIdentifier = urldecode($file->getIdentifier());
        $reloadedFile = $file->getStorage()->getFile($decodedIdentifier);
        if (null === $reloadedFile) {
            throw new FileDoesNotExistException(
                'Could not reload file "'.$decodedIdentifier.'" from storage '.$file->getStorage()->getUid(),
                1731600000
            );
        }

        return $reloadedFile;
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>
     */
    private function getAvailableColumns(array $columns, string $xlfPrefix): array
    {
        $availableColumns = [];
        foreach ($columns as $columnName) {
            if ($this->backendUserService->getBackendUser()?->check('non_exclude_fields', $xlfPrefix.':'.$columnName) ?? false) {
                $availableColumns[$columnName] = $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang_module.xlf:aiSuite.module.workflow.columns.'.$xlfPrefix.'.'.$columnName);
            }
        }

        return $availableColumns;
    }
}
