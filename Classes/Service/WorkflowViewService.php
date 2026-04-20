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

use AutoDudes\AiSuite\Domain\Model\Dto\FileMetadata;
use AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer\ClientAnswer;
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SingletonInterface;

class WorkflowViewService implements SingletonInterface
{
    public function __construct(
        protected readonly MetadataService $metadataService,
        protected readonly ResourceFactory $resourceFactory,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
        protected readonly UuidService $uuidService,
        protected readonly SiteService $siteService,
        protected readonly LibraryService $libraryService,
        protected readonly LocalizationService $localizationService,
        protected readonly SessionService $sessionService,
        protected readonly SysFileMetadataRepository $sysFileMetadataRepository,
        protected readonly DirectiveService $directiveService,
        protected readonly GlobalInstructionService $globalInstructionService,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function filelistFileDirectorySupport(ClientAnswer $librariesAnswer): array
    {
        $directoryId = $this->sessionService->getFilelistFolderId();
        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_workflow_filelist_files_prepare');

        $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
        $textGenerationLibraries = array_filter($textGenerationLibraries, function ($library) {
            return 'Vision' === $library['name'] || 'MittwaldMinistral14BVision' === $library['model_identifier'];
        });

        $availableLanguages = $this->siteService->getAvailableLanguages(true);

        $pendingFileMetadata = [];
        $fileMetadata = [];
        $unsupportedFileMetadata = [];
        $folderName = '';
        if ('' !== $directoryId) {
            $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($directoryId);
            $files = $folder->getFiles();
            $folderName = $folder->getName();

            if (count($files) > 0) {
                $fileUids = [0];
                foreach ($files as $file) {
                    if ($this->metadataService->hasFilePermissions($file->getUid()) && 2 === $file->getType()) {
                        $fileUids[] = $file->getUid();
                    }
                }

                $languageParts = isset($sessionData['options']['sysLanguage']) ? explode('__', $sessionData['options']['sysLanguage']) : [];
                $column = $sessionData['options']['column'] ?? 'all';

                $languageId = isset($languageParts[1]) ? (int) $languageParts[1] : 0;
                $metadataList = $this->sysFileMetadataRepository->findByLangUidAndFileIdList(
                    $fileUids,
                    $column,
                    'file',
                    $languageId
                );

                if ($languageId > 0) {
                    $translatedFileUids = array_keys($metadataList);

                    $nonTranslatedFileUids = array_diff($fileUids, $translatedFileUids);

                    $defaultLanguageMetadataUids = $this->sysFileMetadataRepository->findDefaultLanguageMetadataUidsByFileUids(array_values($nonTranslatedFileUids));

                    foreach ($nonTranslatedFileUids as $fileUid) {
                        if (0 === $fileUid) {
                            continue;
                        }
                        $defaultMetadataUid = $defaultLanguageMetadataUids[$fileUid] ?? 0;
                        if (0 === $defaultMetadataUid) {
                            $this->logger->error('Missing default file metadata for file '.$fileUid);

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

                $showOnlyEmpty = isset($sessionData['options']['showOnlyEmpty']);
                $showOnlyUsed = isset($sessionData['options']['showOnlyUsed']);
                if ($showOnlyEmpty || $showOnlyUsed) {
                    $metadataList = $this->filterMetadataList($metadataList, $column, $showOnlyEmpty, $showOnlyUsed);
                }

                $translatedMetadata = [];
                $nonTranslatedMetadata = [];
                foreach ($metadataList as $fileUid => $metadata) {
                    if (isset($metadata['mode']) && 'NEW' === $metadata['mode']) {
                        $nonTranslatedMetadata[$fileUid] = $metadata;
                    } else {
                        $translatedMetadata[$fileUid] = $metadata;
                    }
                }
                $translatedMetadataUids = array_column($translatedMetadata, 'uid');
                $nonTranslatedMetadataUids = array_column($nonTranslatedMetadata, 'uid');
                $pendingTranslatedFileMetadata = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($translatedMetadataUids, 'sys_file_metadata', $column, '', 'metadata');
                $pendingTranslatedFileMetadata = array_column($pendingTranslatedFileMetadata, 'status', 'table_uid');
                $pendingNonTranslatedFileMetadata = [];
                if ($languageId > 0) {
                    $pendingNonTranslatedFileMetadata = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($nonTranslatedMetadataUids, 'sys_file_metadata', $column, 'NEW', 'metadata');
                    $pendingNonTranslatedFileMetadata = array_column($pendingNonTranslatedFileMetadata, 'status', 'table_uid');
                }
                $pendingFileMetadata = $pendingTranslatedFileMetadata + $pendingNonTranslatedFileMetadata;

                foreach ($files as $file) {
                    if ($file->checkActionPermission('write') && str_contains($file->getMimeType(), 'image')) {
                        if (array_key_exists($file->getUid(), $metadataList)) {
                            $fileMeta = $metadataList[$file->getUid()];
                            if (in_array($file->getMimeType(), MetadataService::SUPPORTED_IMAGE_MIME_TYPES, true)) {
                                $fileMetadata[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                            } else {
                                $unsupportedFileMetadata[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                            }
                        }
                    }
                }
            }
        }

        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'metadata', null, $directoryId);

        return [
            'directory' => $directoryId,
            'directoryName' => $folderName,
            'fileMetadata' => $fileMetadata,
            'unsupportedFileMetadata' => $unsupportedFileMetadata,
            'depths' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5],
            'columns' => array_merge_recursive(
                ['all' => $this->localizationService->translate('module:aiSuite.module.workflowFilelist.allColumns')],
                $this->metadataService->getFileMetadataColumns()
            ),
            'activeColumn' => $sessionData['options']['column'] ?? 'all',
            'sysLanguages' => $availableLanguages,
            'alreadyPendingFiles' => $pendingFileMetadata,
            'parentUuid' => $this->uuidService->generateUuid(),
            'textGenerationLibraries' => $this->libraryService->prepareLibraries($textGenerationLibraries),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
            'preSelection' => $sessionData['options'] ?? [],
            'maxAllowedFileSize' => $this->directiveService->getEffectiveMaxUploadSize(),
            'globalInstructions' => $globalInstructions,
        ];
    }

    public function getFolderCombinedIdentifier(int $fileUid): ?string
    {
        try {
            $file = $this->resourceFactory->getFileObject($fileUid);

            return $file->getParentFolder()->getCombinedIdentifier();
        } catch (\Exception $e) {
            $this->logger->error('Could not get folder identifier for file '.$fileUid.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function filelistFileTranslationDirectorySupport(ClientAnswer $librariesAnswer): array
    {
        $directoryId = $this->sessionService->getFilelistFolderId();
        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_workflow_filelist_files_translate_prepare');
        $sourceLanguageParts = isset($sessionData['options']['sourceLanguage']) ? explode('__', $sessionData['options']['sourceLanguage']) : [];
        $targetLanguageParts = isset($sessionData['options']['targetLanguage']) ? explode('__', $sessionData['options']['targetLanguage']) : [];
        $sourceLanguageId = isset($sourceLanguageParts[1]) ? (int) $sourceLanguageParts[1] : 0;
        $targetLanguageId = isset($targetLanguageParts[1]) ? (int) $targetLanguageParts[1] : 0;
        $column = $sessionData['options']['column'] ?? 'all';

        $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];

        $pendingFileMetadata = [];
        $fileMetadata = [];
        $folderName = '';

        if ('' !== $directoryId) {
            $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($directoryId);
            $files = $folder->getFiles();
            $folderName = $folder->getName();

            if (count($files) > 0) {
                $fileUids = [0];
                foreach ($files as $file) {
                    if ($this->metadataService->hasFilePermissions($file->getUid())
                        && (2 === $file->getType() || 4 === $file->getType() || 5 === $file->getType())
                    ) {
                        $fileUids[] = $file->getUid();
                    }
                }

                $sourceMetadataList = $this->sysFileMetadataRepository->findByLangUidAndFileIdList(
                    $fileUids,
                    $column,
                    'file',
                    $sourceLanguageId
                );

                $targetMetadataList = $this->sysFileMetadataRepository->findByLangUidAndFileIdList(
                    $fileUids,
                    $column,
                    'file',
                    $targetLanguageId
                );

                $translationData = [];
                foreach ($sourceMetadataList as $fileUid => $sourceMetadata) {
                    $targetMetadata = $targetMetadataList[$fileUid] ?? null;

                    if (null === $targetMetadata && $targetLanguageId > 0) {
                        $defaultLanguageMetadataUids = $this->sysFileMetadataRepository->findDefaultLanguageMetadataUidsByFileUids([$fileUid]);
                        $defaultMetadataUid = $defaultLanguageMetadataUids[$fileUid] ?? 0;

                        if ($defaultMetadataUid > 0) {
                            $targetMetadata = [
                                'uid' => $defaultMetadataUid,
                                'file' => $fileUid,
                                'title' => '',
                                'alternative' => '',
                                'description' => '',
                                'mode' => 'NEW',
                            ];
                        }
                    }

                    if (null !== $targetMetadata) {
                        $translationData[$fileUid] = [
                            'source' => $sourceMetadata,
                            'target' => $targetMetadata,
                        ];
                    }
                }

                $showOnlyUsed = isset($sessionData['options']['showOnlyUsed']);
                if ($showOnlyUsed) {
                    $filteredTranslationData = [];
                    foreach ($translationData as $fileUid => $data) {
                        $metadataList = [$fileUid => $data['target']];
                        $filtered = $this->filterMetadataList($metadataList, $column, false, $showOnlyUsed);
                        if (!empty($filtered)) {
                            $filteredTranslationData[$fileUid] = $data;
                        }
                    }
                    $translationData = $filteredTranslationData;
                }

                $translatedMetadataUids = [];
                $nonTranslatedMetadataUids = [];
                foreach ($translationData as $fileUid => $data) {
                    if (isset($data['target']['mode']) && 'NEW' === $data['target']['mode']) {
                        $nonTranslatedMetadataUids[] = $data['target']['uid'];
                    } else {
                        $translatedMetadataUids[] = $data['target']['uid'];
                    }
                }
                $pendingTranslatedFileMetadata = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($translatedMetadataUids, 'sys_file_metadata', $column, '', 'translation', $targetLanguageId);
                $pendingTranslatedFileMetadata = array_column($pendingTranslatedFileMetadata, 'status', 'table_uid');
                $pendingNonTranslatedFileMetadata = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($nonTranslatedMetadataUids, 'sys_file_metadata', $column, 'NEW', 'translation', $targetLanguageId);
                $pendingNonTranslatedFileMetadata = array_column($pendingNonTranslatedFileMetadata, 'status', 'table_uid');

                $pendingFileMetadata = $pendingTranslatedFileMetadata + $pendingNonTranslatedFileMetadata;

                foreach ($files as $file) {
                    if ($file->checkActionPermission('write')
                        && (2 === $file->getType() || 4 === $file->getType() || 5 === $file->getType())
                    ) {
                        if (array_key_exists($file->getUid(), $translationData)) {
                            $data = $translationData[$file->getUid()];
                            $fileMeta = $data['target'];
                            $fileMeta['sourceMetadata'] = $data['source'];
                            $fileMetadata[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                        }
                    }
                }
            }
        }

        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'translation', null, $directoryId);

        return [
            'directory' => $directoryId,
            'directoryName' => $folderName,
            'fileMetadata' => $fileMetadata,
            'columns' => array_merge_recursive(
                ['all' => $this->localizationService->translate('module:aiSuite.module.workflowFilelist.allColumns')],
                $this->metadataService->getFileMetadataColumns()
            ),
            'activeColumn' => $sessionData['options']['column'] ?? 'all',
            'alreadyPendingFiles' => $pendingFileMetadata,
            'parentUuid' => $this->uuidService->generateUuid(),
            'textGenerationLibraries' => $this->libraryService->prepareLibraries($textGenerationLibraries),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
            'preSelection' => $sessionData['options'] ?? [],
            'maxAllowedFileSize' => $this->directiveService->getEffectiveMaxUploadSize(),
            'globalInstructions' => $globalInstructions,
            'equalLanguages' => $sourceLanguageId === $targetLanguageId,
        ];
    }

    /**
     * @param array<int|string, mixed> $metadataList
     *
     * @return array<int|string, mixed>
     */
    private function filterMetadataList(array $metadataList, string $column, bool $showOnlyEmpty, bool $showOnlyUsed): array
    {
        $filteredList = $metadataList;

        if ($showOnlyEmpty) {
            $filteredList = array_filter($filteredList, function ($metadata) use ($column) {
                return $this->isMetadataEmpty($metadata, $column);
            });
        }

        if ($showOnlyUsed) {
            $filteredList = array_filter($filteredList, function ($metadata) {
                return $this->sysFileMetadataRepository->isFileUsed($metadata['file']);
            });
        }

        return $filteredList;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function isMetadataEmpty(array $metadata, string $column): bool
    {
        if ('title' === $column) {
            return empty($metadata['title']);
        }

        if ('alternative' === $column) {
            return empty($metadata['alternative']);
        }

        if ('description' === $column) {
            return empty($metadata['description']);
        }

        return empty($metadata['title']) && empty($metadata['alternative']) && empty($metadata['description']);
    }
}
