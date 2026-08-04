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

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\SingletonInterface;

class FolderSelectionService implements SingletonInterface
{
    public const MAX_DEPTH = 5;
    public const MAX_FOLDERS = 50;
    public const MAX_FILES = 1000;

    public function __construct(
        protected readonly StorageRepository $storageRepository,
        protected readonly BackendUserService $backendUserService,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<int, int>
     */
    public function getDepthOptions(): array
    {
        $options = [];
        for ($depth = 0; $depth <= self::MAX_DEPTH; ++$depth) {
            $options[$depth] = $depth;
        }

        return $options;
    }

    public function clampDepth(int $depth): int
    {
        return max(0, min(self::MAX_DEPTH, $depth));
    }

    /**
     * @param array<int|string, mixed> $identifiers
     *
     * @return list<string>
     */
    public function normalizeIdentifiers(array $identifiers): array
    {
        $normalized = [];
        foreach ($identifiers as $identifier) {
            if (!is_string($identifier)) {
                continue;
            }
            $identifier = trim($identifier);
            if ('' === $identifier) {
                continue;
            }
            $normalized[$identifier] = $identifier;
        }

        return array_values($normalized);
    }

    public function resolveFolder(string $identifier): Folder
    {
        if ('' !== $identifier && 1 === preg_match('/^\d+:/', $identifier)) {
            $storage = $this->storageRepository->findByCombinedIdentifier($identifier);
            if (null === $storage) {
                throw new \RuntimeException(sprintf('Storage for combined identifier "%s" not found.', $identifier));
            }
            [, $folderPath] = explode(':', $identifier, 2);

            return $storage->getFolder('/'.ltrim($folderPath, '/'));
        }

        $defaultStorage = $this->storageRepository->getDefaultStorage();
        if (null === $defaultStorage) {
            throw new \RuntimeException('No default storage available.');
        }

        return $defaultStorage->getFolder($identifier);
    }

    /**
     * @param array<int|string, mixed> $directoryIdentifiers
     * @param list<int>                $allowedFileTypes
     *
     * @return array{
     *     groups: array<string, array{identifier: string, name: string, path: string, isSelectedRoot: bool, files: array<int, File>}>,
     *     limitReached: bool,
     *     skipped: list<string>
     * }
     */
    public function collectFilesByFolder(
        array $directoryIdentifiers,
        int $depth,
        array $allowedFileTypes = [],
        bool $requireEditMetadata = true,
    ): array {
        $depth = $this->clampDepth($depth);

        $groups = [];
        $skipped = [];
        $visitedFolders = [];
        $seenFileUids = [];
        $limitReached = false;
        $folderCount = 0;
        $fileCount = 0;

        $rootFolders = [];
        $selectedRootIdentifiers = [];
        foreach ($this->normalizeIdentifiers($directoryIdentifiers) as $identifier) {
            try {
                $rootFolder = $this->resolveFolder($identifier);
            } catch (\Throwable $e) {
                $this->logger->warning('Selected folder could not be resolved', [
                    'directory' => $identifier,
                    'error' => $e->getMessage(),
                ]);
                $skipped[] = $identifier;

                continue;
            }
            $rootFolders[] = $rootFolder;
            $selectedRootIdentifiers[$rootFolder->getCombinedIdentifier()] = true;
        }

        foreach ($rootFolders as $rootFolder) {
            $queue = [[$rootFolder, 0]];
            while ([] !== $queue) {
                /** @var array{0: Folder, 1: int} $current */
                $current = array_shift($queue);
                [$folder, $level] = $current;

                $combinedIdentifier = $folder->getCombinedIdentifier();
                if (array_key_exists($combinedIdentifier, $visitedFolders)) {
                    continue;
                }
                $visitedFolders[$combinedIdentifier] = true;

                if ($folderCount >= self::MAX_FOLDERS || $fileCount >= self::MAX_FILES) {
                    $limitReached = true;

                    break 2;
                }
                ++$folderCount;

                if (!$this->backendUserService->canReadFolder($folder)) {
                    $skipped[] = $combinedIdentifier;

                    continue;
                }

                $files = $this->collectEligibleFiles(
                    $folder,
                    $allowedFileTypes,
                    $requireEditMetadata,
                    $seenFileUids,
                    $fileCount,
                    $limitReached
                );

                $isSelectedRoot = array_key_exists($combinedIdentifier, $selectedRootIdentifiers);
                // An explicitly selected folder stays in the result even without matches,
                // otherwise it silently disappears and reads like a traversal bug.
                if ([] !== $files || $isSelectedRoot) {
                    $groups[$combinedIdentifier] = [
                        'identifier' => $combinedIdentifier,
                        'name' => $folder->getName(),
                        'path' => $folder->getReadablePath(),
                        'isSelectedRoot' => $isSelectedRoot,
                        'files' => $files,
                    ];
                }

                if ($limitReached) {
                    break 2;
                }

                if ($level < $depth) {
                    foreach ($this->getSubfolders($folder) as $subFolder) {
                        $queue[] = [$subFolder, $level + 1];
                    }
                }
            }
        }

        return [
            'groups' => $groups,
            'limitReached' => $limitReached,
            'skipped' => array_values(array_unique($skipped)),
        ];
    }

    /**
     * @param list<int>        $allowedFileTypes
     * @param array<int, true> $seenFileUids
     *
     * @return array<int, File>
     */
    private function collectEligibleFiles(
        Folder $folder,
        array $allowedFileTypes,
        bool $requireEditMetadata,
        array &$seenFileUids,
        int &$fileCount,
        bool &$limitReached,
    ): array {
        $files = [];

        try {
            $folderFiles = $folder->getFiles();
        } catch (\Throwable $e) {
            $this->logger->warning('Files of the selected folder could not be read', [
                'directory' => $folder->getCombinedIdentifier(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        foreach ($folderFiles as $file) {
            $fileUid = $file->getUid();
            if (array_key_exists($fileUid, $seenFileUids)) {
                continue;
            }
            if ([] !== $allowedFileTypes && !in_array($file->getType(), $allowedFileTypes, true)) {
                continue;
            }
            if (!$file->checkActionPermission('read')) {
                continue;
            }
            if ($requireEditMetadata && !$this->backendUserService->canEditFileMetadata($fileUid)) {
                continue;
            }
            if ($fileCount >= self::MAX_FILES) {
                $limitReached = true;

                break;
            }

            $files[$fileUid] = $file;
            $seenFileUids[$fileUid] = true;
            ++$fileCount;
        }

        return $files;
    }

    /**
     * @return array<int|string, Folder>
     */
    private function getSubfolders(Folder $folder): array
    {
        try {
            return $folder->getSubfolders();
        } catch (\Throwable $e) {
            $this->logger->warning('Subfolders of the selected folder could not be read', [
                'directory' => $folder->getCombinedIdentifier(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
