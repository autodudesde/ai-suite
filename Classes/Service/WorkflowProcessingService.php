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
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

class WorkflowProcessingService implements SingletonInterface
{
    public function __construct(
        protected readonly MetadataService $metadataService,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
        protected readonly UuidService $uuidService,
        protected readonly SiteService $siteService,
        protected readonly TranslationService $translationService,
        protected readonly LocalizationService $localizationService,
        protected readonly SysFileMetadataRepository $sysFileMetadataRepository,
        protected readonly DirectiveService $directiveService,
        protected readonly GlobalInstructionService $globalInstructionService,
        protected readonly WorkflowViewService $workflowViewService,
        protected readonly SendRequestService $sendRequestService,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $workflowData   Must contain: parentUuid, column, textAiModel
     * @param array<int, string>   $pages          Map of pageUid => pageSlug
     * @param list<string>         $languageParts  [isoCode, languageId]
     * @param callable             $contentFetcher fn(int $pageUid, int $languageId): string — fetches page content
     *
     * @return array<string, mixed>
     */
    public function processPageMetadataGeneration(
        array $workflowData,
        array $pages,
        array $languageParts,
        callable $contentFetcher,
        SendRequestService $requestService,
    ): array {
        $payload = [];
        $bulkPayload = [];
        $failedPages = [];

        foreach ($pages as $pageUid => $pageSlug) {
            try {
                $pageContent = $contentFetcher($pageUid, (int) $languageParts[1]);
                $uuid = $this->uuidService->generateUuid();

                $bulkPayload[] = new BackgroundTask(
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
                );

                $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageUid);
                $globalInstructionsOverride = $this->globalInstructionService->checkOverridePredefinedPrompt('pages', 'metadata', [$pageUid]);

                $payload[] = [
                    'field_label' => $workflowData['column'],
                    'request_content' => $pageContent,
                    'uuid' => $uuid,
                    'global_instructions' => $globalInstructions,
                    'override_predefined_prompt' => $globalInstructionsOverride,
                ];
            } catch (\Exception $e) {
                $this->logger->error('Error while fetching page content for page '.$pageUid.': '.$e->getMessage());
                $failedPages[] = $pageUid;
            }
        }

        return [
            'payload' => $payload,
            'bulkPayload' => $bulkPayload,
            'failedPages' => $failedPages,
        ];
    }

    /**
     * @param array<int, mixed> $pages             Map of pageUid => pageData (value is unused, only keys matter)
     * @param string            $parentUuid        Parent UUID for grouping background tasks
     * @param string            $translationScope  What to translate: 'all', 'metadata', or 'content'
     * @param string            $sourceLanguage    ISO source language code (uppercase)
     * @param string            $targetLanguage    ISO target language code (uppercase)
     * @param int               $sourceLanguageUid TYPO3 sys_language_uid of source
     * @param int               $targetLanguageUid TYPO3 sys_language_uid of target
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
    ): array {
        $payload = [];
        $bulkPayload = [];
        $failedPages = [];

        foreach ($pages as $pageUid => $pageData) {
            try {
                $translatableContent = $this->translationService->collectPageTranslatableContent(
                    $pageUid,
                    $sourceLanguageUid,
                    $translationScope,
                    $targetLanguageUid,
                    $request,
                );

                if (empty($translatableContent)) {
                    $failedPages[] = $pageUid;

                    continue;
                }

                $uuid = $this->uuidService->generateUuid();

                $bulkPayload[] = new BackgroundTask(
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
                );

                $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'translation', $pageUid);

                $payload[] = [
                    'source_page_uid' => $pageUid,
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLanguage,
                    'translation_scope' => $translationScope,
                    'translatable_content' => $translatableContent,
                    'uuid' => $uuid,
                    'global_instructions' => $globalInstructions,
                ];
            } catch (\Exception $e) {
                $this->logger->error('Error while collecting translatable content for page '.$pageUid.': '.$e->getMessage());
                $failedPages[] = $pageUid;
            }
        }

        return [
            'payload' => $payload,
            'bulkPayload' => $bulkPayload,
            'failedPages' => $failedPages,
        ];
    }

    /**
     * @param array<int|string, array<string, mixed>> $files                Map of sysFileMetaUid => ['column' => 'value', 'mode' => '...' ]
     * @param array<int|string, array<string, mixed>> $metadataListFromRepo Metadata rows keyed by sysFileMetaUid (from SysFileMetadataRepository::findByUidList)
     * @param string                                  $parentUuid           Parent UUID for grouping background tasks
     * @param string                                  $sourceLanguage       ISO source language code (uppercase)
     * @param string                                  $targetLanguage       ISO target language code (uppercase)
     * @param int                                     $targetLanguageUid    TYPO3 sys_language_uid of target
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
                } catch (\Exception $e) {
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
     * @param array<string, mixed>     $workflowData      Workflow data from request
     * @param array<int|string, mixed> $workflowDataFiles Decoded files data
     * @param list<string>             $languageParts     Language parts [locale, id]
     * @param string                   $scope             Processing scope (e.g., 'fileMetadata')
     * @param SendRequestService       $requestService    Request service for API calls
     *
     * @return array<string, mixed>
     */
    public function processFilelistFilesForMetadataGeneration(
        array $workflowData,
        array $workflowDataFiles,
        array $languageParts,
        string $scope,
        SendRequestService $requestService
    ): array {
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
                                'text' => $workflowData['textAiModel'],
                            ]
                        );

                        if ('Error' === $answer->getType()) {
                            throw new \Exception($answer->getResponseData()['message']);
                        }
                        $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
                        $payload = [];
                        $bulkPayload = [];
                        $fileSizeSumInBytes = 0;
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
                        $columns['mode']
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
                        'filename' => $filename,
                    ];
                    $fileSizeSumInBytes += $fileSize;
                } catch (\Exception $e) {
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
}
