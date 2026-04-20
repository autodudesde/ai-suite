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
        $parsedBody = $request->getParsedBody();
        if ('tx_news_domain_model_news' === $parsedBody['table']) {
            return $this->fetchContentOfNewsArticle(
                (int) $parsedBody['id'],
                (int) $parsedBody['newsDetailPlugin']
            );
        }
        if ('sys_file_metadata' === $parsedBody['table'] || 'sys_file_reference' === $parsedBody['table']) {
            return $this->getFileContent((int) $parsedBody['sysFileId']);
        }
        $previewUrl = $this->getPreviewUrl((int) $parsedBody['pageId']);

        return $this->fetchContentFromUrl($previewUrl);
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
                $file = $file->getStorage()->getFile($decodedIdentifier);
                $data = $file->getContents();
            }
        } catch (\Throwable $e) {
            $decodedIdentifier = urldecode($file->getIdentifier());
            $file = $file->getStorage()->getFile($decodedIdentifier);
            $data = $file->getContents();
        }

        return 'data:'.$file->getMimeType().';base64,'.base64_encode($data);
    }

    public function getFilename(int $sysFileId): string
    {
        $file = $this->resourceFactory->getFileObject($sysFileId);

        try {
            return $file->getName();
        } catch (\Throwable $e) {
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

        $response = $this->requestFactory->request($previewUrl, 'GET', $options);
        $fetchedContent = $response->getBody()->getContents();

        if (empty($fetchedContent)) {
            throw new FetchedContentFailedException($this->localizationService->translate('aiSuite.fetchContentFailed'));
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
            'seo_title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description',
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

    public function hasFilePermissions(int $fileUid, string $table = 'sys_file_metadata'): bool
    {
        if ($this->backendUserService->getBackendUser()?->isAdmin() ?? false) {
            return true;
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);

            return $file->isIndexed()
                && $file->checkActionPermission('editMeta')
                && ($this->backendUserService->getBackendUser()?->check('tables_modify', $table) ?? false);
        } catch (\Exception $e) {
            return false;
        }
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
