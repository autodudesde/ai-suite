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
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

class WorkflowViewService implements SingletonInterface
{
    private const FOLDER_BROWSER_FIELD_REFERENCE = 'aiSuiteFolderSelection';

    public function __construct(
        protected readonly MetadataService $metadataService,
        protected readonly BackendUserService $backendUserService,
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
        protected readonly PromptTemplateService $promptTemplateService,
        protected readonly PromptTemplateScopeService $promptTemplateScopeService,
        protected readonly FolderSelectionService $folderSelectionService,
        protected readonly UriBuilder $uriBuilder,
        protected readonly Typo3Version $typo3Version,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function filelistFileDirectorySupport(ClientAnswer $librariesAnswer): array
    {
        $sessionData = $this->sessionService->getParametersForRoute(SessionService::ROUTE_FILELIST_METADATA);
        $seedBeforeResolution = $this->sessionService->getFilelistFolderId();
        $directories = $this->sessionService->getFilelistDirectories(SessionService::ROUTE_FILELIST_METADATA);
        $depth = $this->sessionService->getFilelistDepth(SessionService::ROUTE_FILELIST_METADATA);
        $directoryId = $directories[0] ?? '';
        $seedWasDropped = !$this->hasTouchedDirectories($sessionData) && '' !== $seedBeforeResolution && [] === $directories;

        $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
        $textGenerationLibraries = array_filter($textGenerationLibraries, function ($library) {
            return 'Vision' === $library['name'] || 'MittwaldMinistral14BVision' === $library['model_identifier'];
        });

        $availableLanguages = $this->siteService->getAvailableLanguages(true);

        $pendingFileMetadata = [];
        $fileMetadata = [];
        $unsupportedFileMetadata = [];
        $folderGroups = [];

        $collected = $this->folderSelectionService->collectFilesByFolder($directories, $depth, [2]);
        $this->warnAboutSkippedFolders($collected['skipped'], $seedWasDropped);
        $files = $this->flattenCollectedFiles($collected['groups']);
        $metadataList = [];

        if (count($files) > 0) {
            $fileUids = [0];
            foreach ($files as $file) {
                if ($this->backendUserService->canEditFileMetadata($file->getUid()) && 2 === $file->getType()) {
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
        }

        foreach ($collected['groups'] as $groupIdentifier => $group) {
            $groupFileMetadata = [];
            foreach ($group['files'] as $file) {
                if ($file->checkActionPermission('write') && str_contains($file->getMimeType(), 'image')) {
                    if (array_key_exists($file->getUid(), $metadataList)) {
                        $fileMeta = $metadataList[$file->getUid()];
                        if (in_array($file->getMimeType(), MetadataService::SUPPORTED_IMAGE_MIME_TYPES, true)) {
                            $fileMetadata[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                            $groupFileMetadata[$file->getUid()] = $fileMetadata[$file->getUid()];
                        } else {
                            $unsupportedFileMetadata[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                        }
                    }
                }
            }
            if ([] !== $groupFileMetadata || $group['isSelectedRoot']) {
                $folderGroups[$groupIdentifier] = $this->buildFolderGroup($group, $groupFileMetadata, 'metadata');
            }
        }

        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'metadata', null, $directoryId);
        $activeColumn = $sessionData['options']['column'] ?? 'all';
        $activeLanguageParts = isset($sessionData['options']['sysLanguage']) ? explode('__', (string) $sessionData['options']['sysLanguage']) : [];

        return [
            'directory' => $directoryId,
            'directories' => $directories,
            'directoriesTouched' => $this->hasTouchedDirectories($sessionData),
            'folderBrowserUrl' => $this->buildFolderBrowserUrl($directories),
            'folderGroups' => $folderGroups,
            'limitReached' => $collected['limitReached'],
            'skippedDirectories' => $collected['skipped'],
            'depth' => $depth,
            'fileMetadata' => $fileMetadata,
            'unsupportedFileMetadata' => $unsupportedFileMetadata,
            'depths' => $this->folderSelectionService->getDepthOptions(),
            'columns' => array_merge_recursive(
                ['all' => $this->localizationService->translate('module:aiSuite.module.workflowFilelist.allColumns')],
                $this->metadataService->getFileMetadataColumns()
            ),
            'activeColumn' => $activeColumn,
            'sysLanguages' => $availableLanguages,
            'alreadyPendingFiles' => $pendingFileMetadata,
            'parentUuid' => $this->uuidService->generateUuid(),
            'textGenerationLibraries' => $this->libraryService->prepareLibraries($textGenerationLibraries),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
            'preSelection' => $sessionData['options'] ?? [],
            'maxAllowedFileSize' => $this->directiveService->getEffectiveMaxUploadSize(),
            'globalInstructions' => $globalInstructions,
            'promptTemplates' => $this->promptTemplateService->getAllPromptTemplates(
                PromptTemplateScopeService::SCOPE_METADATA,
                'all' === $activeColumn ? '' : $this->promptTemplateScopeService->buildMetadataType('sys_file_metadata', (string) $activeColumn),
                isset($activeLanguageParts[1]) ? (int) $activeLanguageParts[1] : 0
            ),
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
        $sessionData = $this->sessionService->getParametersForRoute(SessionService::ROUTE_FILELIST_TRANSLATION);
        $seedBeforeResolution = $this->sessionService->getFilelistFolderId();
        $directories = $this->sessionService->getFilelistDirectories(SessionService::ROUTE_FILELIST_TRANSLATION);
        $depth = $this->sessionService->getFilelistDepth(SessionService::ROUTE_FILELIST_TRANSLATION);
        $directoryId = $directories[0] ?? '';
        $seedWasDropped = !$this->hasTouchedDirectories($sessionData) && '' !== $seedBeforeResolution && [] === $directories;
        $sourceLanguageParts = isset($sessionData['options']['sourceLanguage']) ? explode('__', $sessionData['options']['sourceLanguage']) : [];
        $targetLanguageParts = isset($sessionData['options']['targetLanguage']) ? explode('__', $sessionData['options']['targetLanguage']) : [];
        $sourceLanguageId = isset($sourceLanguageParts[1]) ? (int) $sourceLanguageParts[1] : 0;
        $targetLanguageId = isset($targetLanguageParts[1]) ? (int) $targetLanguageParts[1] : 0;
        $column = $sessionData['options']['column'] ?? 'all';

        $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];

        $pendingFileMetadata = [];
        $fileMetadata = [];
        $folderGroups = [];

        $collected = $this->folderSelectionService->collectFilesByFolder($directories, $depth, [2, 4, 5]);
        $this->warnAboutSkippedFolders($collected['skipped'], $seedWasDropped);
        $files = $this->flattenCollectedFiles($collected['groups']);
        $translationData = [];

        if (count($files) > 0) {
            $fileUids = [0];
            foreach ($files as $file) {
                if ($this->backendUserService->canEditFileMetadata($file->getUid())
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
        }

        foreach ($collected['groups'] as $groupIdentifier => $group) {
            $groupFileMetadata = [];
            foreach ($group['files'] as $file) {
                if ($file->checkActionPermission('write')
                    && (2 === $file->getType() || 4 === $file->getType() || 5 === $file->getType())
                ) {
                    if (array_key_exists($file->getUid(), $translationData)) {
                        $data = $translationData[$file->getUid()];
                        $fileMeta = $data['target'];
                        $fileMeta['sourceMetadata'] = $data['source'];
                        $fileMetadata[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                        $groupFileMetadata[$file->getUid()] = $fileMetadata[$file->getUid()];
                    }
                }
            }
            if ([] !== $groupFileMetadata || $group['isSelectedRoot']) {
                $folderGroups[$groupIdentifier] = $this->buildFolderGroup($group, $groupFileMetadata, 'translation');
            }
        }

        $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'translation', null, $directoryId);

        return [
            'directory' => $directoryId,
            'directories' => $directories,
            'directoriesTouched' => $this->hasTouchedDirectories($sessionData),
            'folderBrowserUrl' => $this->buildFolderBrowserUrl($directories),
            'folderGroups' => $folderGroups,
            'limitReached' => $collected['limitReached'],
            'skippedDirectories' => $collected['skipped'],
            'depth' => $depth,
            'depths' => $this->folderSelectionService->getDepthOptions(),
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
     * @param list<string> $skippedDirectories
     */
    private function warnAboutSkippedFolders(array $skippedDirectories, bool $seedWasDropped = false): void
    {
        if ([] === $skippedDirectories && !$seedWasDropped) {
            return;
        }
        $this->metadataService->flashMessage(
            $this->localizationService->translate('aiSuite.filelist.invalidFolder.message'),
            $this->localizationService->translate('aiSuite.filelist.invalidFolder.title'),
            ContextualFeedbackSeverity::WARNING
        );
    }

    /**
     * @param array<string, array{identifier: string, name: string, path: string, isSelectedRoot: bool, files: array<int, File>}> $groups
     *
     * @return array<int, File>
     */
    private function flattenCollectedFiles(array $groups): array
    {
        $files = [];
        foreach ($groups as $group) {
            foreach ($group['files'] as $fileUid => $file) {
                $files[$fileUid] = $file;
            }
        }

        return $files;
    }

    /**
     * @param array{identifier: string, name: string, path: string, isSelectedRoot: bool, files: array<int, File>} $group
     * @param array<int, FileMetadata>                                                                             $groupFileMetadata
     *
     * @return array<string, mixed>
     */
    private function buildFolderGroup(array $group, array $groupFileMetadata, string $scope): array
    {
        return [
            'identifier' => $group['identifier'],
            'name' => $group['name'],
            'path' => $group['path'],
            'count' => count($groupFileMetadata),
            'isSelectedRoot' => $group['isSelectedRoot'],
            'fileMetadata' => $groupFileMetadata,
            'globalInstructions' => $this->globalInstructionService->buildGlobalInstruction(
                'files',
                $scope,
                null,
                $group['identifier']
            ),
        ];
    }

    /**
     * @param array<string, mixed> $sessionData
     */
    private function hasTouchedDirectories(array $sessionData): bool
    {
        return '1' === (string) ($sessionData['options']['directoriesTouched'] ?? '0');
    }

    /**
     * @param list<string> $directories
     */
    private function buildFolderBrowserUrl(array $directories): string
    {
        $parameters = ['mode' => 'folder'];
        if ($this->typo3Version->getMajorVersion() >= 14) {
            $parameters['fieldReference'] = self::FOLDER_BROWSER_FIELD_REFERENCE;
            $parameters['useEvents'] = 1;
        } else {
            $parameters['bparams'] = self::FOLDER_BROWSER_FIELD_REFERENCE.'|||';
        }
        if ('' !== ($directories[0] ?? '')) {
            $parameters['expandFolder'] = $directories[0];
        }

        try {
            return (string) $this->uriBuilder->buildUriFromRoute('wizard_element_browser', $parameters);
        } catch (RouteNotFoundException $e) {
            $this->logger->error('Folder element browser route is not available', ['error' => $e->getMessage()]);

            return '';
        }
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
