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

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\BackgroundTask;
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileReferenceRepository;
use AutoDudes\AiSuite\Exception\FetchedContentFailedException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class WorkflowProcessingService implements SingletonInterface
{
    /**
     * @var array<string, string>
     */
    public const WORKFLOW_TYPES = [
        'page' => 'Page Metadata Generation',
        'pageTranslate' => 'Page Translation',
        'fileReferences' => 'File References Metadata',
        'fileMetadata' => 'File Metadata Generation',
        'fileMetadataTranslation' => 'File Metadata Translation',
    ];

    public function __construct(
        protected readonly MetadataService $metadataService,
        protected readonly BackendUserService $backendUserService,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
        protected readonly UuidService $uuidService,
        protected readonly SiteService $siteService,
        protected readonly TranslationService $translationService,
        protected readonly LocalizationService $localizationService,
        protected readonly SysFileMetadataRepository $sysFileMetadataRepository,
        protected readonly SysFileReferenceRepository $sysFileReferenceRepository,
        protected readonly DirectiveService $directiveService,
        protected readonly GlobalInstructionService $globalInstructionService,
        protected readonly WorkflowViewService $workflowViewService,
        protected readonly SendRequestService $sendRequestService,
        protected readonly LoggerInterface $logger,
        protected readonly PagesRepository $pagesRepository,
        protected readonly PageRepository $pageRepository,
        protected readonly DomainResolverService $domainResolverService,
        protected readonly GlossarService $glossarService,
        protected readonly FolderSelectionService $folderSelectionService,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
    ) {}

    /**
     * @return array<int|string, string>
     */
    public function getAvailablePageTypes(): array
    {
        $ignorePageTypes = [3, 4, 6, 7, 199, 254, 255];
        $pageTypes = $this->tcaCompatibilityService->getFieldItems('pages', 'doktype');
        $availablePageTypes = [
            -1 => $this->localizationService->translate('module:aiSuite.module.preparePages.allPageTypes'),
        ];
        foreach ($pageTypes as $pageType) {
            if (
                is_array($pageType)
                && isset($pageType['value'])
                && '--div--' !== $pageType['value']
                && !in_array($pageType['value'], $ignorePageTypes, true)
            ) {
                $availablePageTypes[$pageType['value']] = $this->localizationService->translate($pageType['label']);
            }
        }

        return $availablePageTypes;
    }

    /**
     * @param array<string, mixed> $workflowData
     * @param array<int, string>   $pages
     * @param list<string>         $languageParts
     *
     * @return array<string, mixed>
     */
    public function processPageMetadataGeneration(
        array $workflowData,
        array $pages,
        array $languageParts,
        callable $contentFetcher,
        bool $handledByCli = false,
    ): array {
        $payload = [];
        $bulkPayload = [];
        $failedPages = [];

        foreach ($pages as $pageUid => $pageSlug) {
            try {
                $entry = $this->buildPageMetadataEntry($workflowData, (int) $pageUid, $languageParts, $contentFetcher, $handledByCli);
            } catch (FetchedContentFailedException $e) {
                $this->logger->warning('Skipping page '.$pageUid.', its content could not be fetched: '.$e->getMessage());
                $failedPages[] = (int) $pageUid;

                continue;
            } catch (\Throwable $e) {
                $this->logger->error('Error while fetching page content for page '.$pageUid.': '.$e->getMessage());
                $failedPages[] = (int) $pageUid;

                continue;
            }

            $bulkPayload[] = $entry['task'];
            $payload[] = $entry['item'];
        }

        return [
            'payload' => $payload,
            'bulkPayload' => $bulkPayload,
            'failedPages' => $failedPages,
        ];
    }

    /**
     * @param array<int, mixed> $pages
     *
     * @return array<string, mixed>
     */
    public function processPageTranslation(
        array $pages,
        string $parentUuid,
        string $translationScope,
        string $sourceLanguage,
        string $targetLanguage,
        int $sourceLanguageUid,
        int $targetLanguageUid,
        ?ServerRequestInterface $request = null,
        bool $handledByCli = false,
        ?string $model = null,
    ): array {
        $payload = [];
        $bulkPayload = [];
        $failedPages = [];

        foreach ($pages as $pageUid => $pageData) {
            try {
                $entry = $this->buildPageTranslationEntry(
                    (int) $pageUid,
                    $parentUuid,
                    $translationScope,
                    $sourceLanguage,
                    $targetLanguage,
                    $sourceLanguageUid,
                    $targetLanguageUid,
                    $request,
                    $handledByCli,
                    $model,
                );
            } catch (\Throwable $e) {
                $this->logger->error('Error while collecting translatable content for page '.$pageUid.': '.$e->getMessage());
                $failedPages[] = (int) $pageUid;

                continue;
            }

            if (null === $entry) {
                $failedPages[] = (int) $pageUid;

                continue;
            }

            $bulkPayload[] = $entry['task'];
            $payload[] = $entry['item'];
        }

        return [
            'payload' => $payload,
            'bulkPayload' => $bulkPayload,
            'failedPages' => $failedPages,
        ];
    }

    /**
     * @param array<int|string, array<string, mixed>> $files
     * @param array<int|string, array<string, mixed>> $metadataListFromRepo
     *
     * @return array<string, mixed>
     */
    public function processFileMetadataTranslation(
        array $files,
        array $metadataListFromRepo,
        string $parentUuid,
        string $sourceLanguage,
        string $targetLanguage,
        int $targetLanguageUid,
        bool $handledByCli = false,
        ?string $model = null,
    ): array {
        $payload = [];
        $bulkPayload = [];
        $failedFilesMetadata = [];
        $translatableContentForGlossary = [];

        foreach ($files as $sysFileMetaUid => $columns) {
            foreach ($columns as $column => $value) {
                try {
                    if ('mode' === $column) {
                        continue;
                    }
                    $fileUid = (int) $metadataListFromRepo[$sysFileMetaUid]['file'];
                    $defaultSysFileMetaUid = (int) $sysFileMetaUid;

                    $uuid = $this->uuidService->generateUuid();

                    $bulkPayload[] = new BackgroundTask(
                        'metadata',
                        'translation',
                        $parentUuid,
                        $uuid,
                        $column,
                        'sys_file_metadata',
                        'uid',
                        $defaultSysFileMetaUid,
                        $targetLanguageUid,
                        $columns['mode'],
                        handledByCli: $handledByCli,
                        model: $model ?? '',
                    );

                    $folderCombinedIdentifier = $this->workflowViewService->getFolderCombinedIdentifier($fileUid);
                    $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'metadata', null, $folderCombinedIdentifier);
                    $globalInstructionsOverride = $this->globalInstructionService->checkOverridePredefinedPrompt('files', 'metadata', array_filter([$folderCombinedIdentifier], static fn ($v) => null !== $v));
                    $translatableContent = [
                        'sys_file_metadata' => [
                            $defaultSysFileMetaUid => [
                                $column => $value,
                            ],
                        ],
                    ];
                    $translatableContentForGlossary[] = $value;
                    $payload[] = [
                        'translatable_content' => $translatableContent,
                        'source_language' => $sourceLanguage,
                        'target_language' => $targetLanguage,
                        'uuid' => $uuid,
                        'global_instructions' => $globalInstructions,
                        'override_predefined_prompt' => $globalInstructionsOverride,
                    ];
                } catch (\Throwable $e) {
                    $this->logger->error('Error while processing file '.$fileUid.' with sys file metadata uid '.$sysFileMetaUid.': '.$e->getMessage());
                    $failedFilesMetadata[] = $fileUid;
                }
            }
        }

        return [
            'payload' => $payload,
            'bulkPayload' => $bulkPayload,
            'failedFilesMetadata' => $failedFilesMetadata,
            'translatableContentForGlossary' => $translatableContentForGlossary,
        ];
    }

    /**
     * @param array<string, mixed>     $workflowData
     * @param array<int|string, mixed> $workflowDataFiles
     * @param list<string>             $languageParts
     *
     * @return array<string, mixed>
     */
    public function processFilelistFilesForMetadataGeneration(
        array $workflowData,
        array $workflowDataFiles,
        array $languageParts,
        string $scope,
        SendRequestService $requestService,
        bool $handledByCli = false,
        ?string $requestSystemDomain = null,
    ): array {
        $customPrompt = trim((string) ($workflowData['customPrompt'] ?? ''));
        $filesMetadataUidList = [];
        $files = [];
        foreach ($workflowDataFiles as $sysFileMetaUid => $data) {
            $filesMetadataUidList[] = $sysFileMetaUid;
            $fileMetaData = $workflowDataFiles[$sysFileMetaUid];
            foreach ($fileMetaData as $column => $value) {
                if ($workflowData['column'] === $column || 'all' === $workflowData['column']) {
                    $files[$sysFileMetaUid][$column] = $value;
                }
            }
            $files[$sysFileMetaUid]['mode'] = $fileMetaData['mode'];
        }

        $metadataListFromRepo = [];
        if (count($filesMetadataUidList) > 0) {
            $metadataListFromRepo = $this->sysFileMetadataRepository->findByUidList($filesMetadataUidList);
        }

        $payload = [];
        $bulkPayload = [];
        $failedFilesMetadata = [];
        $allowedFileSize = $this->directiveService->getEffectiveMaxUploadSize();
        $fileSizeSumInBytes = 0;

        foreach ($files as $sysFileMetaUid => $columns) {
            foreach ($columns as $column => $value) {
                try {
                    if ('mode' === $column) {
                        continue;
                    }
                    $fileUid = (int) $metadataListFromRepo[$sysFileMetaUid]['file'];
                    $defaultSysFileMetaUid = (int) $sysFileMetaUid;
                    $targetLanguageId = (int) $languageParts[1];

                    $fileContent = $this->metadataService->getFileContent($fileUid);
                    $fileSize = strlen($fileContent);
                    $filename = $this->metadataService->getFilename($fileUid);

                    if (($fileSizeSumInBytes + $fileSize) >= $allowedFileSize && count($payload) > 0) {
                        $errorMessage = $this->flushChunk(
                            $payload,
                            $bulkPayload,
                            $fileSizeSumInBytes,
                            (string) $workflowData['parentUuid'],
                            $scope,
                            'metadata',
                            $languageParts[0],
                            'text',
                            (string) $workflowData['textAiModel'],
                            $requestService,
                            $this->backgroundTaskRepository,
                            [],
                            $requestSystemDomain,
                        );

                        if (null !== $errorMessage) {
                            // Dropping the rejected chunk keeps it from being resent on every following file.
                            $payload = [];
                            $bulkPayload = [];
                            $fileSizeSumInBytes = 0;

                            throw new \Exception($errorMessage);
                        }
                    }

                    $uuid = $this->uuidService->generateUuid();

                    $bulkPayload[] = new BackgroundTask(
                        $scope,
                        'metadata',
                        $workflowData['parentUuid'],
                        $uuid,
                        $column,
                        'sys_file_metadata',
                        'uid',
                        $defaultSysFileMetaUid,
                        $targetLanguageId,
                        $columns['mode'],
                        handledByCli: $handledByCli,
                        model: (string) ($workflowData['textAiModel'] ?? ''),
                    );
                    $folderCombinedIdentifier = $this->workflowViewService->getFolderCombinedIdentifier($fileUid);
                    $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'metadata', null, $folderCombinedIdentifier);
                    $globalInstructionsOverride = $this->globalInstructionService->checkOverridePredefinedPrompt('files', 'metadata', array_filter([$folderCombinedIdentifier], static fn ($v) => null !== $v));
                    $payload[] = [
                        'field_label' => $column,
                        'request_content' => $fileContent,
                        'uuid' => $uuid,
                        'global_instructions' => $globalInstructions,
                        'override_predefined_prompt' => $globalInstructionsOverride,
                        'custom_prompt' => $customPrompt,
                        'filename' => $filename,
                    ];
                    $fileSizeSumInBytes += $fileSize;
                } catch (\Throwable $e) {
                    $this->logger->error('Error while fetching file content for file '.$fileUid.' with sys file metadata uid '.$sysFileMetaUid.': '.$e->getMessage());
                    $failedFilesMetadata[] = $fileUid;
                }
            }
        }

        return [
            'payload' => $payload,
            'bulkPayload' => $bulkPayload,
            'failedFilesMetadata' => $failedFilesMetadata,
        ];
    }

    /**
     * @param list<array<string, mixed>> $payload
     * @param list<BackgroundTask>       $bulkPayload
     * @param array<string, mixed>       $extraParams
     */
    public function sendWorkflowRequest(
        array $payload,
        array $bulkPayload,
        string $parentUuid,
        string $scope,
        string $type,
        string $languageCode,
        string $modelKey,
        string $model,
        SendRequestService $requestService,
        BackgroundTaskRepository $backgroundTaskRepository,
        array $extraParams = [],
        ?string $requestSystemDomain = null,
    ): ?string {
        if (0 === count($payload)) {
            return null;
        }

        $answer = $requestService->sendDataRequest(
            'createMassAction',
            array_merge([
                'uuid' => $parentUuid,
                'payload' => $payload,
                'scope' => $scope,
                'type' => $type,
            ], $extraParams),
            '',
            $languageCode,
            [$modelKey => $model],
            $requestSystemDomain,
        );

        if ('Error' === $answer->getType()) {
            return $answer->getResponseData()['message'] ?? 'Unknown error while sending workflow request.';
        }

        $backgroundTaskRepository->insertBackgroundTasks($bulkPayload);

        return null;
    }

    /**
     * @param array<string, mixed> $workflowData
     * @param array<int, string>   $pages
     * @param list<string>         $languageParts
     *
     * @return array{success: bool, failedPages: list<int>, taskCount: int, chunks: int, message: string}
     */
    public function dispatchPageMetadataInChunks(
        array $workflowData,
        array $pages,
        array $languageParts,
        callable $contentFetcher,
        SendRequestService $requestService,
        BackgroundTaskRepository $backgroundTaskRepository,
        ?string $requestSystemDomain = null,
        bool $handledByCli = true,
        ?callable $progress = null,
    ): array {
        $payload = [];
        $bulkPayload = [];
        $failedPages = [];
        $byteBudget = 0;
        $dispatched = 0;
        $chunks = 0;
        $total = count($pages);
        $maxBytes = $this->directiveService->getEffectiveMaxUploadSize();
        $maxItems = $this->directiveService->getEffectiveMaxItemsPerRequest();

        foreach ($pages as $pageUid => $pageSlug) {
            try {
                $entry = $this->buildPageMetadataEntry($workflowData, (int) $pageUid, $languageParts, $contentFetcher, $handledByCli);
            } catch (FetchedContentFailedException $e) {
                $this->logger->warning('Skipping page '.$pageUid.', its content could not be fetched: '.$e->getMessage());
                $failedPages[] = (int) $pageUid;

                continue;
            } catch (\Throwable $e) {
                $this->logger->error('Error while fetching page content for page '.$pageUid.': '.$e->getMessage());
                $failedPages[] = (int) $pageUid;

                continue;
            }

            $entrySize = $this->measureChunkItem($entry['item']);

            if (count($payload) > 0 && (($byteBudget + $entrySize) >= $maxBytes || count($payload) >= $maxItems)) {
                $itemsInChunk = count($payload);
                $errorMessage = $this->flushChunk(
                    $payload,
                    $bulkPayload,
                    $byteBudget,
                    (string) $workflowData['parentUuid'],
                    'page',
                    'metadata',
                    $languageParts[0],
                    'text',
                    (string) ($workflowData['textAiModel'] ?? ''),
                    $requestService,
                    $backgroundTaskRepository,
                    [],
                    $requestSystemDomain,
                );

                if (null !== $errorMessage) {
                    return [
                        'success' => false,
                        'failedPages' => $failedPages,
                        'taskCount' => $dispatched,
                        'chunks' => $chunks,
                        'message' => $errorMessage,
                    ];
                }

                ++$chunks;
                $dispatched += $itemsInChunk;
                if (null !== $progress) {
                    $progress($chunks, $dispatched, $total);
                }
            }

            $payload[] = $entry['item'];
            $bulkPayload[] = $entry['task'];
            $byteBudget += $entrySize;
        }

        $itemsInChunk = count($payload);
        $errorMessage = $this->flushChunk(
            $payload,
            $bulkPayload,
            $byteBudget,
            (string) $workflowData['parentUuid'],
            'page',
            'metadata',
            $languageParts[0],
            'text',
            (string) ($workflowData['textAiModel'] ?? ''),
            $requestService,
            $backgroundTaskRepository,
            [],
            $requestSystemDomain,
        );

        if (null !== $errorMessage) {
            return [
                'success' => false,
                'failedPages' => $failedPages,
                'taskCount' => $dispatched,
                'chunks' => $chunks,
                'message' => $errorMessage,
            ];
        }

        if ($itemsInChunk > 0) {
            ++$chunks;
            $dispatched += $itemsInChunk;
            if (null !== $progress) {
                $progress($chunks, $dispatched, $total);
            }
        }

        return [
            'success' => true,
            'failedPages' => $failedPages,
            'taskCount' => $dispatched,
            'chunks' => $chunks,
            'message' => sprintf('Successfully added %d new task(s).', $dispatched),
        ];
    }

    /**
     * @param array<int, mixed> $pages
     *
     * @return array{success: bool, failedPages: list<int>, taskCount: int, chunks: int, message: string}
     */
    public function dispatchPageTranslationInChunks(
        array $pages,
        string $parentUuid,
        string $translationScope,
        string $sourceLanguage,
        string $targetLanguage,
        int $sourceLanguageUid,
        int $targetLanguageUid,
        string $model,
        SendRequestService $requestService,
        BackgroundTaskRepository $backgroundTaskRepository,
        ?string $requestSystemDomain = null,
        bool $handledByCli = true,
        ?callable $progress = null,
    ): array {
        $payload = [];
        $bulkPayload = [];
        $failedPages = [];
        $byteBudget = 0;
        $dispatched = 0;
        $chunks = 0;
        $total = count($pages);
        $maxBytes = $this->directiveService->getEffectiveMaxUploadSize();
        $maxItems = $this->directiveService->getEffectiveMaxItemsPerRequest();

        foreach ($pages as $pageUid => $pageData) {
            try {
                $entry = $this->buildPageTranslationEntry(
                    (int) $pageUid,
                    $parentUuid,
                    $translationScope,
                    $sourceLanguage,
                    $targetLanguage,
                    $sourceLanguageUid,
                    $targetLanguageUid,
                    null,
                    $handledByCli,
                    $model,
                );
            } catch (\Throwable $e) {
                $this->logger->error('Error while collecting translatable content for page '.$pageUid.': '.$e->getMessage());
                $failedPages[] = (int) $pageUid;

                continue;
            }

            if (null === $entry) {
                $failedPages[] = (int) $pageUid;

                continue;
            }

            $entrySize = $this->measureChunkItem($entry['item']);

            if (count($payload) > 0 && (($byteBudget + $entrySize) >= $maxBytes || count($payload) >= $maxItems)) {
                $itemsInChunk = count($payload);
                $errorMessage = $this->flushChunk(
                    $payload,
                    $bulkPayload,
                    $byteBudget,
                    $parentUuid,
                    'page-translation',
                    'translation',
                    '',
                    'translate',
                    $model,
                    $requestService,
                    $backgroundTaskRepository,
                    [],
                    $requestSystemDomain,
                );

                if (null !== $errorMessage) {
                    return [
                        'success' => false,
                        'failedPages' => $failedPages,
                        'taskCount' => $dispatched,
                        'chunks' => $chunks,
                        'message' => $errorMessage,
                    ];
                }

                ++$chunks;
                $dispatched += $itemsInChunk;
                if (null !== $progress) {
                    $progress($chunks, $dispatched, $total);
                }
            }

            $payload[] = $entry['item'];
            $bulkPayload[] = $entry['task'];
            $byteBudget += $entrySize;
        }

        $itemsInChunk = count($payload);
        $errorMessage = $this->flushChunk(
            $payload,
            $bulkPayload,
            $byteBudget,
            $parentUuid,
            'page-translation',
            'translation',
            '',
            'translate',
            $model,
            $requestService,
            $backgroundTaskRepository,
            [],
            $requestSystemDomain,
        );

        if (null !== $errorMessage) {
            return [
                'success' => false,
                'failedPages' => $failedPages,
                'taskCount' => $dispatched,
                'chunks' => $chunks,
                'message' => $errorMessage,
            ];
        }

        if ($itemsInChunk > 0) {
            ++$chunks;
            $dispatched += $itemsInChunk;
            if (null !== $progress) {
                $progress($chunks, $dispatched, $total);
            }
        }

        return [
            'success' => true,
            'failedPages' => $failedPages,
            'taskCount' => $dispatched,
            'chunks' => $chunks,
            'message' => sprintf('Successfully added %d new task(s).', $dispatched),
        ];
    }

    /**
     * @param array<string, mixed>     $workflowData
     * @param array<int|string, mixed> $fileReferences
     * @param list<string>             $languageParts
     *
     * @return array<string, mixed>
     */
    public function processFileReferencesMetadataGeneration(
        array $workflowData,
        array $fileReferences,
        array $languageParts,
        SendRequestService $requestService,
        bool $handledByCli = false,
        ?string $requestSystemDomain = null,
    ): array {
        $payload = [];
        $bulkPayload = [];
        $failedFileReferences = [];
        $customPrompt = trim((string) ($workflowData['customPrompt'] ?? ''));
        $allowedFileSize = $this->directiveService->getEffectiveMaxUploadSize();
        $fileSizeSumInBytes = 0;

        foreach ($fileReferences as $sysFileReferenceUid => $sysFileUid) {
            try {
                if (0 === (int) $sysFileUid) {
                    $fileReferenceRow = $this->sysFileReferenceRepository->findByUid((int) $sysFileReferenceUid);
                    if (0 === count($fileReferenceRow) || !array_key_exists('uid_local', $fileReferenceRow[0])) {
                        throw new \Exception($this->localizationService->translate('aiSuite.error.fileReference.notFound', [$sysFileReferenceUid]));
                    }
                    $sysFileUid = (int) $fileReferenceRow[0]['uid_local'];
                }
                $fileContent = $this->metadataService->getFileContent((int) $sysFileUid);
                $filename = $this->metadataService->getFilename((int) $sysFileUid);
                $fileSize = strlen($fileContent);

                if (($fileSizeSumInBytes + $fileSize) >= $allowedFileSize && count($payload) > 0) {
                    $errorMessage = $this->flushChunk(
                        $payload,
                        $bulkPayload,
                        $fileSizeSumInBytes,
                        (string) $workflowData['parentUuid'],
                        'fileReference',
                        'metadata',
                        $languageParts[0],
                        'text',
                        (string) $workflowData['textAiModel'],
                        $requestService,
                        $this->backgroundTaskRepository,
                        [],
                        $requestSystemDomain,
                    );

                    if (null !== $errorMessage) {
                        // Dropping the rejected chunk keeps it from being resent on every following file.
                        $payload = [];
                        $bulkPayload = [];
                        $fileSizeSumInBytes = 0;

                        throw new \Exception($errorMessage);
                    }
                }

                $uuid = $this->uuidService->generateUuid();
                $bulkPayload[] = new BackgroundTask(
                    'fileReference',
                    'metadata',
                    $workflowData['parentUuid'],
                    $uuid,
                    $workflowData['column'],
                    'sys_file_reference',
                    'uid',
                    (int) $sysFileReferenceUid,
                    (int) $languageParts[1],
                    '',
                    handledByCli: $handledByCli,
                    model: (string) ($workflowData['textAiModel'] ?? ''),
                );
                $pageId = (int) $workflowData['startFromPid'];
                $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageId);
                $globalInstructionsOverride = $this->globalInstructionService->checkOverridePredefinedPrompt('pages', 'metadata', [$pageId]);
                $payload[] = [
                    'field_label' => $workflowData['column'],
                    'request_content' => $fileContent,
                    'uuid' => $uuid,
                    'global_instructions' => $globalInstructions,
                    'override_predefined_prompt' => $globalInstructionsOverride,
                    'custom_prompt' => $customPrompt,
                    'filename' => $filename,
                ];
                $fileSizeSumInBytes += $fileSize;
            } catch (\Throwable $e) {
                $this->logger->error('Error while fetching file content for file with sys file reference uid '.$sysFileReferenceUid.': '.$e->getMessage());
                $failedFileReferences[] = $sysFileReferenceUid;
            }
        }

        return [
            'payload' => $payload,
            'bulkPayload' => $bulkPayload,
            'failedFileReferences' => $failedFileReferences,
        ];
    }

    /**
     * @param array<string, mixed> $extConf
     */
    public function handleMetadaGenerationAfterFileAdded(
        FileInterface $file,
        array $extConf
    ): void {
        try {
            $fileMetadata = $file->getMetaData();
            $fileMetadataUid = (int) $fileMetadata->offsetGet('uid');
            $workflowDataFiles = [
                $fileMetadataUid => [
                    'mode' => '',
                ],
            ];
            if ((bool) $extConf['metadataAutogenerateAlternative'] && (bool) $extConf['metadataAutogenerateTitle']) {
                $column = 'all';
                $workflowDataFiles[$fileMetadataUid]['title'] = '';
                $workflowDataFiles[$fileMetadataUid]['alternative'] = '';
            } elseif ((bool) $extConf['metadataAutogenerateTitle']) {
                $column = 'title';
                $workflowDataFiles[$fileMetadataUid]['title'] = '';
            } else {
                $column = 'alternative';
                $workflowDataFiles[$fileMetadataUid]['alternative'] = '';
            }

            $availableSourceLanguages = $this->siteService->getAvailableLanguages(true, 0, true);
            $firstLanguageKey = array_key_first($availableSourceLanguages);
            $workflowData = [
                'parentUuid' => $this->uuidService->generateUuid(),
                'column' => $column,
                'sysLanguage' => $firstLanguageKey,
                'textAiModel' => $extConf['metadataAutogenerateModel'],
                'customPrompt' => $extConf['metadataAutogeneratePrompt'] ?? '',
            ];
            $scope = 'fileMetadata';
            $languageParts = explode('__', (string) $workflowData['sysLanguage']);

            $requestService = $this->sendRequestService;
            $result = $this->processFilelistFilesForMetadataGeneration(
                $workflowData,
                $workflowDataFiles,
                $languageParts,
                $scope,
                $requestService
            );

            $payload = $result['payload'];
            $bulkPayload = $result['bulkPayload'];
            if (count($payload) > 0) {
                $requestService = $this->sendRequestService;
                $answer = $requestService->sendDataRequest(
                    'createMassAction',
                    [
                        'uuid' => $workflowData['parentUuid'],
                        'payload' => $payload,
                        'scope' => $scope,
                        'type' => 'metadata',
                    ],
                    '',
                    $languageParts[0],
                    [
                        'text' => $extConf['metadataAutogenerateModel'],
                    ]
                );

                if ('Error' === $answer->getType()) {
                    $this->logger->error('Error generating metadata for file UID '.$file->getUid().': '.$answer->getResponseData()['message']);
                    $this->metadataService->flashMessage(
                        $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.errorGeneratingAutoUpload.message', [$file->getName()]),
                        $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.errorSavingFile.title'),
                        ContextualFeedbackSeverity::ERROR
                    );

                    return;
                }

                $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
                $this->metadataService->flashMessage(
                    $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.autoGenerationSuccess.message'),
                    $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.generatedField.title'),
                    ContextualFeedbackSeverity::INFO
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Exception while generating metadata for uploaded file '.$file->getName().': '.$e->getMessage());
            $this->metadataService->flashMessage(
                $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.unexpectedErrorAutoGenerateUpload.message'),
                $this->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.flashMessage.metadata.errorSavingFile.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function prepareAndExecutePagesMetadataWorkflow(array $config, ?callable $progress = null): array
    {
        $pageId = (int) $config['startFromPid'];
        $this->reinforceLanguageFilter($config, $pageId);

        $foundPageUids = $this->pageRepository->getPageIdsRecursive([$pageId], (int) $config['depth']);
        $pagesData = $this->pagesRepository->fetchNecessaryPageData($config, $foundPageUids);
        $pages = [];
        foreach ($pagesData as $pageData) {
            $pages[$pageData['uid']] = $pageData['slug'];
        }

        $languageParts = explode('__', (string) $config['sysLanguage']);

        $pagesUids = array_keys($pages);
        $alreadyPending = $this->backgroundTaskRepository->fetchAlreadyPendingEntries(
            $pagesUids,
            'pages',
            $config['column'],
            '',
            'metadata',
            (int) $languageParts[1],
        );
        foreach ($alreadyPending as $pendingData) {
            unset($pages[$pendingData['table_uid']]);
        }

        if (empty($pages)) {
            return [
                'success' => true,
                'failedPages' => [],
                'message' => 'All entered tasks are pending or already done!',
            ];
        }

        $parentUuid = $this->uuidService->generateUuid();
        $workflowData = [
            'parentUuid' => $parentUuid,
            'column' => $config['column'],
            'textAiModel' => $config['model'],
            'customPrompt' => $config['customPrompt'] ?? '',
        ];

        $requestSystemDomain = $this->domainResolverService->getDomainByPageId($pageId);

        return $this->dispatchPageMetadataInChunks(
            $workflowData,
            $pages,
            $languageParts,
            $this->buildPageContentFetcher(),
            $this->sendRequestService,
            $this->backgroundTaskRepository,
            $requestSystemDomain,
            true,
            $progress,
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function prepareAndExecutePageTranslationWorkflow(array $config, ?callable $progress = null): array
    {
        $pageId = (int) $config['startFromPid'];
        $this->reinforceTranslationLanguageFilters($config, $pageId);

        $sourceLanguageParts = explode('__', (string) $config['sourceLanguage']);
        $targetLanguageParts = explode('__', (string) $config['targetLanguage']);

        $foundPageUids = $this->pageRepository->getPageIdsRecursive([$pageId], (int) $config['depth']);
        $pagesData = $this->pagesRepository->fetchPagesForTranslation(
            $foundPageUids,
            (int) $sourceLanguageParts[1],
            (int) $targetLanguageParts[1],
            $config,
        );

        $pages = [];
        foreach ($pagesData as $pageData) {
            if (!empty($pageData['isAlreadyTranslated'])) {
                continue;
            }
            $pages[$pageData['uid']] = $pageData;
        }

        if (empty($pages)) {
            return [
                'success' => true,
                'failedPages' => [],
                'message' => 'No pages found for translation!',
            ];
        }

        $alreadyPending = $this->backgroundTaskRepository->fetchAlreadyPendingEntriesForTranslation(
            array_keys($pages),
            'pages',
            (int) $targetLanguageParts[1],
        );
        foreach ($alreadyPending as $pendingData) {
            unset($pages[$pendingData['table_uid']]);
        }

        if (empty($pages)) {
            return [
                'success' => true,
                'failedPages' => [],
                'message' => 'All entered tasks are pending or already done!',
            ];
        }

        $parentUuid = $this->uuidService->generateUuid();
        $requestSystemDomain = $this->domainResolverService->getDomainByPageId($pageId);

        return $this->dispatchPageTranslationInChunks(
            $pages,
            $parentUuid,
            (string) $config['translationScope'],
            $sourceLanguageParts[0],
            $targetLanguageParts[0],
            (int) $sourceLanguageParts[1],
            (int) $targetLanguageParts[1],
            (string) ($config['model'] ?? ''),
            $this->sendRequestService,
            $this->backgroundTaskRepository,
            $requestSystemDomain,
            true,
            $progress,
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function prepareAndExecuteFileReferencesMetadataWorkflow(array $config): array
    {
        $pageId = (int) $config['startFromPid'];
        $this->reinforceLanguageFilter($config, $pageId);
        $config['showOnlyEmpty'] ??= false;

        $languageParts = explode('__', (string) $config['sysLanguage']);

        $foundPageUids = $this->pageRepository->getPageIdsRecursive([$pageId], (int) $config['depth']);
        $foundFileReferences = $this->sysFileReferenceRepository->fetchSysFileReferences(
            $foundPageUids,
            (string) $config['column'],
            (int) $languageParts[1],
            (bool) $config['showOnlyEmpty'],
        );

        $fileReferences = [];
        foreach ($foundFileReferences as $fileRefData) {
            if (!$this->backendUserService->canEditFileReferenceMetadata($fileRefData['uid_local'])) {
                continue;
            }
            if (!in_array($fileRefData['fileMimeType'] ?? '', MetadataService::SUPPORTED_IMAGE_MIME_TYPES, true)) {
                continue;
            }
            $fileReferences[$fileRefData['uid']] = $fileRefData['uid_local'];
        }

        if (empty($fileReferences)) {
            return [
                'success' => true,
                'failedFiles' => [],
                'message' => 'No eligible file references found.',
            ];
        }

        $parentUuid = $this->uuidService->generateUuid();
        $workflowData = [
            'parentUuid' => $parentUuid,
            'column' => $config['column'],
            'textAiModel' => $config['model'],
            'startFromPid' => $config['startFromPid'],
            'customPrompt' => $config['customPrompt'] ?? '',
        ];
        $requestSystemDomain = $this->domainResolverService->getDomainByPageId($pageId);

        $result = $this->processFileReferencesMetadataGeneration(
            $workflowData,
            $fileReferences,
            $languageParts,
            $this->sendRequestService,
            handledByCli: true,
            requestSystemDomain: $requestSystemDomain,
        );

        $errorMessage = $this->sendWorkflowRequest(
            $result['payload'],
            $result['bulkPayload'],
            $parentUuid,
            'fileReference',
            'metadata',
            $languageParts[0],
            'text',
            $config['model'],
            $this->sendRequestService,
            $this->backgroundTaskRepository,
            [],
            $requestSystemDomain,
        );

        if (null !== $errorMessage) {
            return [
                'success' => false,
                'message' => $errorMessage,
                'failedFiles' => $result['failedFileReferences'],
            ];
        }

        return [
            'success' => true,
            'failedFiles' => $result['failedFileReferences'],
            'message' => sprintf('Successfully added %d new task(s).', count($result['bulkPayload'])),
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function prepareAndExecuteFileMetadataWorkflow(array $config): array
    {
        $directory = (string) ($config['directory'] ?? '');
        $languageParts = explode('__', (string) $config['sysLanguage']);
        $languageId = (int) $languageParts[1];
        $column = (string) $config['column'];
        $showOnlyEmpty = (bool) ($config['showOnlyEmpty'] ?? false);
        $showOnlyUsed = (bool) ($config['showOnlyUsed'] ?? false);

        $collected = $this->collectWorkflowFiles($directory, $config, [2]);
        if (null === $collected) {
            return [
                'success' => false,
                'message' => 'Specified directory could not be found.',
            ];
        }
        $files = $collected;

        if (empty($files)) {
            return [
                'success' => false,
                'message' => 'No files found in the specified directory.',
            ];
        }

        $fileUids = array_keys($files);

        $metadataList = $this->sysFileMetadataRepository->findByLangUidAndFileIdList(
            $fileUids,
            $column,
            'file',
            $languageId,
            $showOnlyEmpty,
            $showOnlyUsed,
        );

        if ($languageId > 0) {
            $translatedFileUids = array_keys($metadataList);
            $nonTranslatedFileUids = array_filter(
                array_values(array_diff($fileUids, $translatedFileUids)),
                static fn ($uid) => 0 !== $uid,
            );
            if (!empty($nonTranslatedFileUids)) {
                $defaultLanguageMetadataUids = $this->sysFileMetadataRepository->findDefaultLanguageMetadataUidsByFileUids(
                    array_values($nonTranslatedFileUids)
                );
                foreach ($nonTranslatedFileUids as $fileUid) {
                    $defaultMetadataUid = $defaultLanguageMetadataUids[$fileUid] ?? 0;
                    if (0 === $defaultMetadataUid) {
                        $this->logger->error('Missing default file metadata for file uid '.$fileUid);

                        continue;
                    }
                    $metadataList[$fileUid] = [
                        'uid' => $defaultMetadataUid,
                        'file' => $fileUid,
                        'title' => '',
                        'alternative' => '',
                        'description' => '',
                        'mode' => 'NEW',
                    ];
                }
            }
        }

        $workflowDataFiles = [];
        foreach ($files as $file) {
            if (!in_array($file->getMimeType(), MetadataService::SUPPORTED_IMAGE_MIME_TYPES, true)) {
                continue;
            }
            $fileUid = $file->getUid();
            if (!array_key_exists($fileUid, $metadataList)) {
                continue;
            }
            $fileMeta = $metadataList[$fileUid];
            $workflowDataFiles[$fileMeta['uid']] = [
                'title' => $fileMeta['title'] ?? '',
                'alternative' => $fileMeta['alternative'] ?? '',
                'description' => $fileMeta['description'] ?? '',
                'mode' => isset($fileMeta['mode']) && 'NEW' === $fileMeta['mode'] ? 'NEW' : '',
            ];
        }

        if (empty($workflowDataFiles)) {
            return [
                'success' => false,
                'message' => 'No eligible image files found for metadata generation.',
            ];
        }

        $scope = 'fileMetadata';
        $parentUuid = $this->uuidService->generateUuid();
        $workflowData = [
            'parentUuid' => $parentUuid,
            'column' => $column,
            'textAiModel' => $config['model'],
            'customPrompt' => $config['customPrompt'] ?? '',
        ];
        $requestSystemDomain = $this->domainResolverService->getDomainBySiteIdentifier(end($languageParts));

        $result = $this->processFilelistFilesForMetadataGeneration(
            $workflowData,
            $workflowDataFiles,
            $languageParts,
            $scope,
            $this->sendRequestService,
            handledByCli: true,
            requestSystemDomain: $requestSystemDomain,
        );

        $errorMessage = $this->sendWorkflowRequest(
            $result['payload'],
            $result['bulkPayload'],
            $parentUuid,
            $scope,
            'metadata',
            $languageParts[0],
            'text',
            $config['model'],
            $this->sendRequestService,
            $this->backgroundTaskRepository,
            [],
            $requestSystemDomain,
        );

        if (null !== $errorMessage) {
            return [
                'success' => false,
                'message' => $errorMessage,
                'failedFiles' => $result['failedFilesMetadata'],
            ];
        }

        return [
            'success' => true,
            'failedFiles' => $result['failedFilesMetadata'],
            'message' => sprintf(
                'Successfully added %d new task(s).',
                count($workflowDataFiles) - count($result['failedFilesMetadata']),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function prepareAndExecuteFileMetadataTranslationWorkflow(array $config): array
    {
        $directory = (string) ($config['directory'] ?? '');
        $sourceLanguageParts = explode('__', (string) $config['sourceLanguage']);
        $targetLanguageParts = explode('__', (string) $config['targetLanguage']);
        $targetLanguageId = (int) $targetLanguageParts[1];
        $column = (string) $config['column'];
        $showOnlyUsed = (bool) ($config['showOnlyUsed'] ?? false);

        $collected = $this->collectWorkflowFiles($directory, $config, [2]);
        if (null === $collected) {
            return [
                'success' => false,
                'message' => 'Specified directory could not be found.',
            ];
        }
        $fileUids = array_keys($collected);

        $defaultMetadataList = $this->sysFileMetadataRepository->findByLangUidAndFileIdList(
            $fileUids,
            'all',
            'file',
            0,
            false,
            $showOnlyUsed,
        );

        $workflowDataFiles = [];
        foreach ($defaultMetadataList as $fileUid => $defaultMeta) {
            $row = [];
            foreach (['title', 'alternative', 'description'] as $col) {
                if ('all' === $column || $col === $column) {
                    $row[$col] = $defaultMeta[$col] ?? '';
                }
            }
            $row['mode'] = '';
            $workflowDataFiles[$defaultMeta['uid']] = $row;
        }

        if (empty($workflowDataFiles)) {
            return [
                'success' => false,
                'message' => 'No eligible files found for metadata translation.',
            ];
        }

        $filesMetadataUidList = array_keys($workflowDataFiles);
        $alreadyPending = $this->backgroundTaskRepository->fetchAlreadyPendingEntriesForTranslation(
            $filesMetadataUidList,
            'sys_file_metadata',
            $targetLanguageId,
        );
        foreach ($alreadyPending as $pendingData) {
            $uid = $pendingData['table_uid'];
            $col = $pendingData['answer_field'] ?? '';
            if (isset($workflowDataFiles[$uid][$col])) {
                unset($workflowDataFiles[$uid][$col]);
                if (0 === count(array_filter(array_keys($workflowDataFiles[$uid]), static fn ($k) => 'mode' !== $k))) {
                    unset($workflowDataFiles[$uid]);
                }
            }
        }

        if (empty($workflowDataFiles)) {
            return [
                'success' => true,
                'message' => 'All entered tasks are pending or already done!',
            ];
        }

        $metadataListFromRepo = $this->sysFileMetadataRepository->findByUidList(array_keys($workflowDataFiles));
        $parentUuid = $this->uuidService->generateUuid();

        $result = $this->processFileMetadataTranslation(
            $workflowDataFiles,
            $metadataListFromRepo,
            $parentUuid,
            $sourceLanguageParts[0],
            $targetLanguageParts[0],
            $targetLanguageId,
            handledByCli: true,
            model: (string) ($config['model'] ?? ''),
        );

        if (0 === count($result['payload'])) {
            return [
                'success' => false,
                'message' => 'No valid tasks could be created. Check logs for details.',
                'failedFiles' => $result['failedFilesMetadata'],
            ];
        }

        $extraParams = [];
        if (!empty($config['glossary'])) {
            $glossaryParts = explode('__', (string) $config['glossary']);
            if (3 === count($glossaryParts)) {
                $rootPageId = (int) $glossaryParts[0];
                $sourceLanguageId = (int) $glossaryParts[1];
                $glossaryTargetLanguageId = (int) $glossaryParts[2];
                $translatableContent = (string) json_encode(
                    $result['translatableContentForGlossary'],
                    JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
                );
                $glossarEntries = $this->glossarService->findGlossarEntries(
                    $translatableContent,
                    $glossaryTargetLanguageId,
                    $sourceLanguageId,
                );
                $deeplGlossary = $this->glossarService->findDeeplGlossary(
                    $rootPageId,
                    $sourceLanguageId,
                    $glossaryTargetLanguageId,
                );
                $extraParams = [
                    'glossary' => json_encode($glossarEntries, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
                    'deepl_glossary_id' => is_array($deeplGlossary) ? ($deeplGlossary['glossar_uuid'] ?? '') : '',
                ];
            }
        }

        $requestSystemDomain = $this->domainResolverService->getDomainBySiteIdentifier(end($sourceLanguageParts));
        $errorMessage = $this->sendWorkflowRequest(
            $result['payload'],
            $result['bulkPayload'],
            $parentUuid,
            'metadata',
            'translation',
            '',
            'translate',
            $config['model'],
            $this->sendRequestService,
            $this->backgroundTaskRepository,
            $extraParams,
            $requestSystemDomain,
        );

        if (null !== $errorMessage) {
            return [
                'success' => false,
                'message' => $errorMessage,
                'failedFiles' => $result['failedFilesMetadata'],
            ];
        }

        return [
            'success' => true,
            'failedFiles' => $result['failedFilesMetadata'],
            'message' => sprintf('Successfully added %d new task(s).', count($result['bulkPayload'])),
        ];
    }

    /**
     * @param array<string, mixed> $workflowData
     * @param list<string>         $languageParts
     *
     * @return array{task: BackgroundTask, item: array<string, mixed>}
     *
     * @throws FetchedContentFailedException
     */
    private function buildPageMetadataEntry(
        array $workflowData,
        int $pageUid,
        array $languageParts,
        callable $contentFetcher,
        bool $handledByCli,
    ): array {
        $pageContent = $contentFetcher($pageUid, (int) $languageParts[1]);
        $uuid = $this->uuidService->generateUuid();

        $task = new BackgroundTask(
            'page',
            'metadata',
            $workflowData['parentUuid'],
            $uuid,
            $workflowData['column'],
            'pages',
            'uid',
            $pageUid,
            (int) $languageParts[1],
            '',
            handledByCli: $handledByCli,
            model: (string) ($workflowData['textAiModel'] ?? ''),
        );

        return [
            'task' => $task,
            'item' => [
                'field_label' => $workflowData['column'],
                'request_content' => $pageContent,
                'uuid' => $uuid,
                'global_instructions' => $this->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageUid),
                'override_predefined_prompt' => $this->globalInstructionService->checkOverridePredefinedPrompt('pages', 'metadata', [$pageUid]),
                'custom_prompt' => trim((string) ($workflowData['customPrompt'] ?? '')),
            ],
        ];
    }

    /**
     * @return null|array{task: BackgroundTask, item: array<string, mixed>}
     */
    private function buildPageTranslationEntry(
        int $pageUid,
        string $parentUuid,
        string $translationScope,
        string $sourceLanguage,
        string $targetLanguage,
        int $sourceLanguageUid,
        int $targetLanguageUid,
        ?ServerRequestInterface $request,
        bool $handledByCli,
        ?string $model,
    ): ?array {
        $translatableContent = $this->translationService->collectPageTranslatableContent(
            $pageUid,
            $sourceLanguageUid,
            $translationScope,
            $targetLanguageUid,
            $request,
        );

        if (empty($translatableContent)) {
            return null;
        }

        $uuid = $this->uuidService->generateUuid();

        $task = new BackgroundTask(
            'page-translation',
            'translation',
            $parentUuid,
            $uuid,
            $translationScope,
            'pages',
            'uid',
            $pageUid,
            $targetLanguageUid,
            '',
            handledByCli: $handledByCli,
            model: $model ?? '',
        );

        return [
            'task' => $task,
            'item' => [
                'source_page_uid' => $pageUid,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'translation_scope' => $translationScope,
                'translatable_content' => $translatableContent,
                'uuid' => $uuid,
                'global_instructions' => $this->globalInstructionService->buildGlobalInstruction('pages', 'translation', $pageUid),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $payload
     * @param list<BackgroundTask>       $bulkPayload
     * @param array<string, mixed>       $extraParams
     */
    private function flushChunk(
        array &$payload,
        array &$bulkPayload,
        int &$byteBudget,
        string $parentUuid,
        string $scope,
        string $type,
        string $languageCode,
        string $modelKey,
        string $model,
        SendRequestService $requestService,
        BackgroundTaskRepository $backgroundTaskRepository,
        array $extraParams = [],
        ?string $requestSystemDomain = null,
    ): ?string {
        if (0 === count($payload)) {
            return null;
        }

        $errorMessage = $this->sendWorkflowRequest(
            $payload,
            $bulkPayload,
            $parentUuid,
            $scope,
            $type,
            $languageCode,
            $modelKey,
            $model,
            $requestService,
            $backgroundTaskRepository,
            $extraParams,
            $requestSystemDomain,
        );

        if (null !== $errorMessage) {
            return $errorMessage;
        }

        $payload = [];
        $bulkPayload = [];
        $byteBudget = 0;

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function measureChunkItem(array $item): int
    {
        return strlen((string) json_encode($item));
    }

    /**
     * @param array<string, mixed> $config
     * @param list<int>            $allowedFileTypes
     *
     * @return null|array<int, FileInterface>
     */
    private function collectWorkflowFiles(string $directory, array $config, array $allowedFileTypes): ?array
    {
        $directories = GeneralUtility::trimExplode(',', $directory, true);
        if ([] === $directories) {
            $directories = [''];
        }

        $collected = $this->folderSelectionService->collectFilesByFolder(
            $directories,
            (int) ($config['depth'] ?? 0),
            $allowedFileTypes
        );

        if ([] === $collected['groups'] && [] !== $collected['skipped']) {
            return null;
        }

        $files = [];
        foreach ($collected['groups'] as $group) {
            foreach ($group['files'] as $fileUid => $file) {
                $files[$fileUid] = $file;
            }
        }

        return $files;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function reinforceLanguageFilter(array &$config, int $pageId): void
    {
        $availableLanguages = $this->siteService->getAvailableLanguages(true, $pageId);
        $currentSysLanguage = (string) $config['sysLanguage'];
        $sysLanguageToUse = $currentSysLanguage;
        $notification = '';

        $this->siteService->updateSelectedSysLanguage(
            $availableLanguages,
            $sysLanguageToUse,
            $notification,
            $currentSysLanguage,
        );

        $config['sysLanguage'] = $sysLanguageToUse;
        if ('' !== $notification) {
            $this->logger->warning('Language filter adjusted: '.$notification);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function reinforceTranslationLanguageFilters(array &$config, int $pageId): void
    {
        $availableSourceLanguages = $this->siteService->getAvailableLanguages(true, $pageId, true);
        $sourceLanguageToUse = (string) $config['sourceLanguage'];
        $notificationSource = '';
        $this->siteService->updateSelectedSysLanguage(
            $availableSourceLanguages,
            $sourceLanguageToUse,
            $notificationSource,
            (string) $config['sourceLanguage'],
            'sourceLanguage',
        );
        $config['sourceLanguage'] = $sourceLanguageToUse;

        $availableTargetLanguages = $this->siteService->getAvailableLanguages(true, $pageId);
        $targetLanguageToUse = (string) $config['targetLanguage'];
        $notificationTarget = '';
        $this->siteService->updateSelectedSysLanguage(
            $availableTargetLanguages,
            $targetLanguageToUse,
            $notificationTarget,
            (string) $config['targetLanguage'],
            'targetLanguage',
        );
        $config['targetLanguage'] = $targetLanguageToUse;

        if ('' !== $notificationSource) {
            $this->logger->warning('Source language filter adjusted: '.$notificationSource);
        }
        if ('' !== $notificationTarget) {
            $this->logger->warning('Target language filter adjusted: '.$notificationTarget);
        }
    }

    private function buildPageContentFetcher(): callable
    {
        return function (int $pageUid, int $languageId): string {
            $page = $this->pageRepository->getPage($pageUid);
            $previewUriPageId = $pageUid;
            if (1 === ($page['is_siteroot'] ?? 0) && ($page['l10n_parent'] ?? 0) > 0) {
                $previewUriPageId = $page['l10n_parent'];
            }
            $previewUri = PreviewUriBuilder::create($previewUriPageId)
                ->withLanguage($languageId)
                ->buildUri()
            ;
            if (null === $previewUri) {
                return '';
            }
            $url = $this->siteService->buildAbsoluteUri($previewUri);

            return $this->metadataService->fetchContentFromUrl($url);
        };
    }
}
