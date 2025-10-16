<?php

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

class GlobalInstructionService
{
    protected GlobalInstructionsRepository $globalInstructionsRepository;
    protected BackendUserService $backendUserService;
    protected LoggerInterface $logger;

    public function __construct(
        GlobalInstructionsRepository $globalInstructionsRepository,
        BackendUserService $backendUserService,
        LoggerInterface $logger
    ) {
        $this->globalInstructionsRepository = $globalInstructionsRepository;
        $this->backendUserService = $backendUserService;
        $this->logger = $logger;
    }
    public function buildGlobalInstruction(string $context, string $scope, ?int $pageId = null, ?string $directoryPath = null): string
    {
        if ($pageId === null && $directoryPath === null) {
            return '';
        }
        if ($context === 'pages' && $pageId && $pageId <= 0) {
            return '';
        }

        if ($context === 'pages' && $pageId !== null) {
            return $this->buildPageTreeInstructions($scope, $pageId, $context);
        }

        if ($context === 'files' && $directoryPath !== null) {
            return $this->buildFileTreeInstructions($scope, $directoryPath, $context);
        }

        return '';
    }

    private function buildPageTreeInstructions(string $scope, int $pageId, $context): string
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

    private function buildFileTreeInstructions(string $scope, string $directoryPath, $context): string
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
                    'depth' => $minDepth
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

    private function doesPageInstructionApply(array $globalInstruction, int $targetPageUid, array $pageUids): bool
    {
        $selectedPages = GeneralUtility::trimExplode(',', $globalInstruction['selected_pages'] ?? '', true);
        $useForSubtree = (bool) ($globalInstruction['use_for_subtree'] ?? false);

        if (empty($selectedPages)) {
            return false;
        }

        foreach ($selectedPages as $selectedPageUid) {
            $selectedPageUid = (int)$selectedPageUid;

            if ($selectedPageUid === $targetPageUid) {
                return true;
            }

            if ($useForSubtree && in_array($selectedPageUid, $pageUids)) {
                $selectedPageIndex = array_search($selectedPageUid, $pageUids);
                $targetPageIndex = array_search($targetPageUid, $pageUids);

                if ($selectedPageIndex !== false && $targetPageIndex !== false && $selectedPageIndex <= $targetPageIndex) {
                    return true;
                }
            }
        }

        return false;
    }

    private function doesFileInstructionApply(array $globalInstruction, string $directoryPath): bool
    {
        $selectedDirectories = GeneralUtility::trimExplode(',', $globalInstruction['selected_directories'] ?? '', true);
        $useForSubtree = (bool) ($globalInstruction['use_for_subtree'] ?? false);

        $normalizedPath = rtrim($directoryPath, '/') . '/';

        foreach ($selectedDirectories as $selectedDir) {
            $selectedPath = rtrim($selectedDir, '/') . '/';

            if ($normalizedPath === $selectedPath) {
                return true;
            }

            if ($useForSubtree && $this->backendUserService->isPathWithinStorageMountBoundaries($normalizedPath, $selectedPath)) {
                return true;
            }
        }

        return false;
    }

    private function processInstructionsText(array $globalInstruction, string $additionalInstructions): string
    {
        $instructions = trim($globalInstruction['instructions'] ?? '');
        if ($instructions !== '') {
            $extendPrevious = (bool) ($globalInstruction['extend_previous_instructions'] ?? false);

            if ($extendPrevious) {
                $additionalInstructions .= $instructions . "\n";
            } else {
                $additionalInstructions = $instructions . "\n";
            }
        }

        return $additionalInstructions;
    }

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
            $depth = $normalizedPath === '' ? 0 : substr_count($normalizedPath, '/') + 1;
            $minDepth = min($minDepth, $depth);
        }

        return $minDepth === 99 ? 0 : $minDepth;
    }

    public function checkOverridePredefinedPrompt(string $context, string $scope, array $selectedTree): bool
    {
        $globalInstruction = $this->globalInstructionsRepository->findExistingGlobalInstruction($context, $scope, $selectedTree);
        if (!empty($globalInstruction)) {
            return $globalInstruction['override_predefined_prompt'] ?? false;
        }
        return false;
    }
}
