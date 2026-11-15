<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

class GlobalInstructionService implements SingletonInterface
{
    protected const SCOPE_LABEL_PREFIX = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.scope.';
    protected const CONTEXT_LABEL_PREFIX = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:aiSuite.tca.globalInstructions.context.';

    /** @var array<string, list<string>> */
    protected const SCOPE_VALUES = [
        'files' => ['general', 'imageWizard', 'metadata', 'translation'],
        'pages' => ['general', 'pageTree', 'imageWizard', 'contentElement', 'newsRecord', 'editContent', 'metadata', 'translation'],
        'global' => ['general', 'pageTree', 'imageWizard', 'contentElement', 'newsRecord', 'editContent', 'metadata', 'translation'],
    ];

    /** @var array<int, ?int> */
    private array $siteRootByPageId = [];

    public function __construct(
        protected readonly GlobalInstructionsRepository $globalInstructionsRepository,
        protected readonly BackendUserService $backendUserService,
        protected readonly LoggerInterface $logger,
        protected readonly SiteFinder $siteFinder,
    ) {}

    /**
     * @return list<array{label: string, value: string}>
     */
    public function getScopeItems(string $context): array
    {
        $context = self::normalizeContext($context);
        $items = [];
        foreach (self::SCOPE_VALUES[$context] as $value) {
            $items[] = [
                'label' => self::scopeLabel($context, $value),
                'value' => $value,
            ];
        }

        return $items;
    }

    public static function scopeLabel(string $context, string $scope): string
    {
        return self::SCOPE_LABEL_PREFIX.self::normalizeContext($context).'.'.$scope;
    }

    public static function contextLabel(string $context): string
    {
        return self::CONTEXT_LABEL_PREFIX.self::normalizeContext($context);
    }

    public function buildGlobalInstruction(string $context, string $scope, ?int $pageId = null, ?string $directoryPath = null): string
    {
        if ('pages' === $context && null !== $pageId && $pageId > 0) {
            return $this->buildPageTreeInstructions($scope, $pageId, $context, $this->buildSiteWideInstructions($scope, $pageId));
        }

        if ('files' === $context) {
            $siteWide = $this->buildSiteWideInstructions($scope, null);

            return null === $directoryPath
                ? trim($siteWide)
                : $this->buildFileTreeInstructions($scope, $directoryPath, $context, $siteWide);
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

        $pageId = 'pages' === $context ? (int) ($selectedTree[0] ?? 0) : null;
        if ('pages' === $context && $pageId <= 0) {
            return false;
        }
        foreach ($this->findSiteWideInstructions($scope, $pageId) as $siteWideInstruction) {
            if ($scope === ($siteWideInstruction['scope'] ?? '') && (bool) ($siteWideInstruction['override_predefined_prompt'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeContext(string $context): string
    {
        return in_array($context, ['files', 'global'], true) ? $context : 'pages';
    }

    private function buildSiteWideInstructions(string $scope, ?int $pageId): string
    {
        $instructions = '';
        foreach ($this->findSiteWideInstructions($scope, $pageId) as $siteWideInstruction) {
            $instructions = $this->processInstructionsText($siteWideInstruction, $instructions);
        }

        return $instructions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findSiteWideInstructions(string $scope, ?int $pageId): array
    {
        $siteRoot = null === $pageId ? null : $this->siteRootPageId($pageId);
        if (null !== $pageId && null === $siteRoot) {
            return [];
        }

        $applicable = [];
        foreach ($this->globalInstructionsRepository->findGlobalByScope($scope) as $siteWideInstruction) {
            $applies = null === $pageId
                ? (bool) ($siteWideInstruction['apply_to_files'] ?? false)
                : $siteRoot === $this->siteRootPageId((int) ($siteWideInstruction['pid'] ?? 0));
            if ($applies) {
                $applicable[] = $siteWideInstruction;
            }
        }

        return $applicable;
    }

    private function siteRootPageId(int $pageId): ?int
    {
        if ($pageId <= 0) {
            return null;
        }
        if (!array_key_exists($pageId, $this->siteRootByPageId)) {
            try {
                $this->siteRootByPageId[$pageId] = $this->siteFinder->getSiteByPageId($pageId)->getRootPageId();
            } catch (SiteNotFoundException) {
                $this->siteRootByPageId[$pageId] = null;
            }
        }

        return $this->siteRootByPageId[$pageId];
    }

    private function buildPageTreeInstructions(string $scope, int $pageId, string $context, string $siteWideInstructions): string
    {
        try {
            $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId, '');
            $rootline = $rootlineUtility->get();
            $pageUids = array_reverse(array_column($rootline, 'uid'));
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return '';
        }

        $lastPageUid = $pageUids[array_key_last($pageUids)] ?? 0;
        $applicableInstructions = [];

        foreach ($this->globalInstructionsRepository->findByScope($scope, $context) as $globalInstruction) {
            $depth = $this->pageInstructionDepth($globalInstruction, $lastPageUid, $pageUids);
            if (null !== $depth) {
                $applicableInstructions[] = ['instruction' => $globalInstruction, 'depth' => $depth];
            }
        }

        usort($applicableInstructions, static fn (array $a, array $b): int => $a['depth'] <=> $b['depth']);

        $additionalInstructions = $siteWideInstructions;
        foreach ($applicableInstructions as $item) {
            $additionalInstructions = $this->processInstructionsText($item['instruction'], $additionalInstructions);
        }

        return trim($additionalInstructions);
    }

    private function buildFileTreeInstructions(string $scope, string $directoryPath, string $context, string $siteWideInstructions): string
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

        $additionalInstructions = $siteWideInstructions;
        foreach ($applicableInstructions as $item) {
            $additionalInstructions = $this->processInstructionsText($item['instruction'], $additionalInstructions);
        }

        return trim($additionalInstructions);
    }

    /**
     * @param array<string, mixed> $globalInstruction
     * @param list<int>            $pageUids
     */
    private function pageInstructionDepth(array $globalInstruction, int $targetPageUid, array $pageUids): ?int
    {
        $useForSubtree = (bool) ($globalInstruction['use_for_subtree'] ?? false);
        $targetPageIndex = array_search($targetPageUid, $pageUids, true);
        $depth = null;

        foreach (GeneralUtility::intExplode(',', (string) ($globalInstruction['selected_pages'] ?? ''), true) as $selectedPageUid) {
            $selectedPageIndex = array_search($selectedPageUid, $pageUids, true);
            if (false === $selectedPageIndex || false === $targetPageIndex) {
                continue;
            }
            if ($selectedPageUid === $targetPageUid || ($useForSubtree && $selectedPageIndex <= $targetPageIndex)) {
                $depth = max($depth ?? 0, $selectedPageIndex);
            }
        }

        return $depth;
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
