<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\FileMetadata;
use AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer\ClientAnswer;
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SingletonInterface;

class MassActionService implements SingletonInterface
{
    protected MetadataService $metadataService;
    protected ResourceFactory $resourceFactory;
    protected BackgroundTaskRepository $backgroundTaskRepository;
    protected LoggerInterface $logger;
    protected FileListService $fileListService;
    protected UuidService $uuidService;

    protected SiteService $siteService;
    protected LibraryService $libraryService;
    protected TranslationService $translationService;
    protected SessionService $sessionService;
    protected SysFileMetadataRepository $sysFileMetadataRepository;
    protected DirectiveService $directiveService;
    protected array $supportedMimeTypes;

    public function __construct(
        MetadataService $metadataService,
        ResourceFactory $resourceFactory,
        BackgroundTaskRepository $backgroundTaskRepository,
        FileListService $fileListService,
        UuidService $uuidService,
        SiteService $siteService,
        LibraryService $libraryService,
        TranslationService $translationService,
        SessionService $sessionService,
        SysFileMetadataRepository $sysFileMetadataRepository,
        DirectiveService $directiveService,
    ) {
        $this->metadataService = $metadataService;
        $this->resourceFactory = $resourceFactory;
        $this->backgroundTaskRepository = $backgroundTaskRepository;
        $this->fileListService = $fileListService;
        $this->uuidService = $uuidService;
        $this->siteService = $siteService;
        $this->libraryService = $libraryService;
        $this->translationService = $translationService;
        $this->sessionService = $sessionService;
        $this->sysFileMetadataRepository = $sysFileMetadataRepository;
        $this->directiveService = $directiveService;

        $this->supportedMimeTypes = [
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/webp",
        ];
    }

    public function filelistFileDirectorySupport(ClientAnswer $librariesAnswer): array
    {
        $directoryId = $this->sessionService->getFilelistFolderId();
        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_massaction_filelist_files_prepare');

        $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
        $textGenerationLibraries = array_filter($textGenerationLibraries, function($library) {
            return $library['name'] === 'Vision';
        });

        $availableLanguages = $this->siteService->getAvailableLanguages(true);
        ksort($availableLanguages);

        $pendingFileMetadata = [];
        $parsedFiles = [];
        $unsupportedFiles = [];
        $folderName = '';
        if ($directoryId !== '') {
            $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($directoryId);
            $files = $folder->getFiles();
            $folderName = $folder->getName();

            if (count($files) > 0) {
                $fileUids = [0];
                foreach ($files as $file) {
                    if ($file->checkActionPermission('write') && $file->getType() === 2) {
                        $fileUids[] = $file->getUid();
                    }
                }

                $languageParts = isset($sessionData['options']['sysLanguage']) ? explode('__', $sessionData['options']['sysLanguage']) : [];
                $column = $sessionData['options']['column'] ?? 'all';
                $languageId = isset($languageParts[1]) ? (int)$languageParts[1] : 0;
                $metadataList = $this->sysFileMetadataRepository->findByLangUidAndFileIdList(
                    $fileUids,
                    $column,
                    'file',
                    $languageId,
                    isset($sessionData['options']['showOnlyEmpty']),
                    isset($sessionData['options']['showOnlyUsed'])
                );
                $metadataUids = array_column($metadataList, 'uid');
                $pendingFileMetadata = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($metadataUids, 'sys_file_metadata', $column);
                $pendingFileMetadata = array_reduce($pendingFileMetadata, function ($carry, $item) {
                    $carry[$item['table_uid']] = $item['status'];
                    return $carry;
                }, []);
                foreach ($files as $file) {
                    if ($file->checkActionPermission('write') && strpos('image', $file->getMimeType()) !== -1) {
                        if (array_key_exists($file->getUid(), $metadataList)) {
                            $fileMeta = $metadataList[$file->getUid()];
                            if(in_array($file->getMimeType(), $this->supportedMimeTypes)) {
                                $parsedFiles[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                            } else {
                                $unsupportedFiles[$file->getUid()] = FileMetadata::createFromFileObject($file, $fileMeta);
                            }
                        }
                    }
                }
            }
        }

        return [
            'directory' => $directoryId,
            'directoryName' => $folderName,
            'files' => $parsedFiles,
            'unsupportedFiles' => $unsupportedFiles,
            'depths' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5],
            'columns' => array_merge_recursive(
                ['all' => $this->translationService->translate('tx_aisuite.module.massActionFilelist.allColumns')],
                $this->metadataService->getFileMetadataColumns()),
            'activeColumn' => $sessionData['options']['column'] ?? 'all',
            'sysLanguages' => $availableLanguages,
            'alreadyPendingFiles' => $pendingFileMetadata,
            'parentUuid' => $this->uuidService->generateUuid(),
            'textGenerationLibraries' => $this->libraryService->prepareLibraries($textGenerationLibraries),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
            'preSelection' => $sessionData['options'] ?? [],
            'maxAllowedFileSize' => $this->directiveService->getEffectiveMaxUploadSize(),
        ];
    }
}
