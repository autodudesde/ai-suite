<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

class GlobalInstructionService implements SingletonInterface
{
    public function __construct(
        protected readonly GlobalInstructionsRepository $globalInstructionsRepository,
        protected readonly BackendUserService $backendUserService,
        protected readonly LoggerInterface $logger,
    ) {}

    public function buildGlobalInstruction(string $context, string $scope, ?int $pageId = null, ?string $directoryPath = null): string
    {
        if (null === $pageId && null === $directoryPath) {
            return '';
        }
        if ('pages' === $context && $pageId && $pageId <= 0) {
            return '';
        }

        if ('pages' === $context && null !== $pageId) {
            return $this->buildPageTreeInstructions($scope, $pageId, $context);
        }

        if ('files' === $context && null !== $directoryPath) {
            return $this->buildFileTreeInstructions($scope, $directoryPath, $context);
        }

        return '';
    }

    /**
     * @param list<int|string> $selectedTree
     */
    public function checkOverridePredefinedPrompt(string $context, string $scope, array $selectedTree): bool
    {
        $globalInstruction = $this->globalInstructionsRepository->findExistingGlobalInstruction($context, $scope, $selectedTree);
        if (!empty($globalInstruction)) {
            return (bool) ($globalInstruction['override_predefined_prompt'] ?? false);
        }

        return false;
    }

    private function buildPageTreeInstructions(string $scope, int $pageId, string $context): string
    {
        try {
            $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId, '');
            $rootline = $rootlineUtility->get();
            $pageUids = array_reverse(array_column($rootline, 'uid'));
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return '';
        }

        $additionalInstructions = '';
        $lastPageUid = $pageUids[array_key_last($pageUids)] ?? 0;

        $globalInstructions = $this->globalInstructionsRepository->findByScope($scope, $context);

        foreach ($globalInstructions as $globalInstruction) {
            if (!$this->doesPageInstructionApply($globalInstruction, $lastPageUid, $pageUids)) {
                continue;
            }

            $additionalInstructions = $this->processInstructionsText($globalInstruction, $additionalInstructions);
        }

        return trim($additionalInstructions);
    }

    private function buildFileTreeInstructions(string $scope, string $directoryPath, string $context): string
    {
        $globalInstructions = $this->globalInstructionsRepository->findByScope($scope, $context);
        $applicableInstructions = [];

        foreach ($globalInstructions as $globalInstruction) {
            try {
                if (!$this->backendUserService->hasFileAccessPermissions($globalInstruction)) {
                    continue;
                }
                if (!$this->doesFileInstructionApply($globalInstruction, $directoryPath)) {
                    continue;
                }

                $minDepth = $this->calculateMinDirectoryDepth($globalInstruction);
                $applicableInstructions[] = [
                    'instruction' => $globalInstruction,
                    'depth' => $minDepth,
                ];
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());

                continue;
            }
        }

        usort($applicableInstructions, function ($a, $b) {
            return $a['depth'] <=> $b['depth'];
        });

        $additionalInstructions = '';
        foreach ($applicableInstructions as $item) {
            $additionalInstructions = $this->processInstructionsText($item['instruction'], $additionalInstructions);
        }

        return trim($additionalInstructions);
    }

    /**
     * @param array<string, mixed> $globalInstruction
     * @param list<int>            $pageUids
     */
    private function doesPageInstructionApply(array $globalInstruction, int $targetPageUid, array $pageUids): bool
    {
        $selectedPages = GeneralUtility::trimExplode(',', $globalInstruction['selected_pages'] ?? '', true);
        $useForSubtree = (bool) ($globalInstruction['use_for_subtree'] ?? false);

        if (empty($selectedPages)) {
            return false;
        }

        foreach ($selectedPages as $selectedPageUid) {
            $selectedPageUid = (int) $selectedPageUid;

            if ($selectedPageUid === $targetPageUid) {
                return true;
            }

            if ($useForSubtree && in_array($selectedPageUid, $pageUids)) {
                $selectedPageIndex = array_search($selectedPageUid, $pageUids);
                $targetPageIndex = array_search($targetPageUid, $pageUids);

                if (false !== $selectedPageIndex && false !== $targetPageIndex && $selectedPageIndex <= $targetPageIndex) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $globalInstruction
     */
    private function doesFileInstructionApply(array $globalInstruction, string $directoryPath): bool
    {
        $selectedDirectories = GeneralUtility::trimExplode(',', $globalInstruction['selected_directories'] ?? '', true);
        $useForSubtree = (bool) ($globalInstruction['use_for_subtree'] ?? false);

        $normalizedPath = rtrim($directoryPath, '/').'/';

        foreach ($selectedDirectories as $selectedDir) {
            $selectedPath = rtrim($selectedDir, '/').'/';

            if ($normalizedPath === $selectedPath) {
                return true;
            }

            if ($useForSubtree && $this->backendUserService->isPathWithinStorageMountBoundaries($normalizedPath, $selectedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $globalInstruction
     */
    private function processInstructionsText(array $globalInstruction, string $additionalInstructions): string
    {
        $instructions = trim($globalInstruction['instructions'] ?? '');
        if ('' !== $instructions) {
            $extendPrevious = (bool) ($globalInstruction['extend_previous_instructions'] ?? false);

            if ($extendPrevious) {
                $additionalInstructions .= $instructions."\n";
            } else {
                $additionalInstructions = $instructions."\n";
            }
        }

        return $additionalInstructions;
    }

    /**
     * @param array<string, mixed> $globalInstruction
     */
    private function calculateMinDirectoryDepth(array $globalInstruction): int
    {
        $selectedDirectories = GeneralUtility::trimExplode(',', $globalInstruction['selected_directories'] ?? '', true);

        if (empty($selectedDirectories)) {
            return 0;
        }

        $minDepth = 99;

        foreach ($selectedDirectories as $selectedDir) {
            $pathForDepth = $selectedDir;
            if (str_contains($pathForDepth, ':')) {
                $pathForDepth = substr($pathForDepth, strpos($pathForDepth, ':') + 1);
            }

            $normalizedPath = trim($pathForDepth, '/');
            $depth = '' === $normalizedPath ? 0 : substr_count($normalizedPath, '/') + 1;
            $minDepth = min($minDepth, $depth);
        }

        return 99 === $minDepth ? 0 : $minDepth;
    }
}
