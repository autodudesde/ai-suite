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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

#[AsController]
class CliOverviewController
{
    /**
     * @var array<string, string>
     */
    protected const SCOPE_TO_TYPE_MAP = [
        'page' => 'page',
        'page-translation' => 'pageTranslate',
        'fileReference' => 'fileReferences',
        'fileMetadata' => 'fileMetadata',
        'metadata' => 'fileMetadataTranslation',
    ];

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly PageRenderer $pageRenderer,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->setModuleId('aiSuite');

        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/cli-overview-dashboard.js');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');

        $typeFilter = $request->getQueryParams()['type'] ?? null;

        $config = ['status' => 'failed'];
        if (null !== $typeFilter && 'all' !== $typeFilter) {
            $config['type'] = $typeFilter;
        }

        $failedTasks = $this->backgroundTaskRepository->findBackgroundTasksHandledByCli(500, $config);
        $groupedTasks = $this->groupTasksByScope($failedTasks);
        $failedStatistics = $this->buildStatistics($failedTasks);

        $pendingTasks = $this->backgroundTaskRepository->findBackgroundTasksHandledByCli(500, ['status' => 'pending']);
        $pendingStatistics = $this->buildStatistics($pendingTasks);

        $view->assignMultiple([
            'schedulerAvailable' => ExtensionManagementUtility::isLoaded('scheduler'),
            'groupedTasks' => $groupedTasks,
            'failedStatistics' => $failedStatistics,
            'pendingStatistics' => $pendingStatistics,
            'currentTypeFilter' => $typeFilter ?? 'all',
            'scopeToTypeMap' => self::SCOPE_TO_TYPE_MAP,
            'availableTypes' => [
                'all' => 'All Types',
                'page' => 'Page Metadata',
                'pageTranslate' => 'Page Translation',
                'fileReferences' => 'File References',
                'fileMetadata' => 'File Metadata',
                'fileMetadataTranslation' => 'File Metadata Translation',
            ],
        ]);

        return $view->renderResponse('CliOverview/Overview');
    }

    /**
     * @param list<array<string, mixed>> $tasks
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupTasksByScope(array $tasks): array
    {
        $grouped = [];
        foreach ($tasks as $task) {
            $scope = $task['scope'];
            $grouped[$scope] ??= [];
            $grouped[$scope][] = $task;
        }

        return $grouped;
    }

    /**
     * @param list<array<string, mixed>> $tasks
     *
     * @return array{total: int, byScope: array<string, int>}
     */
    private function buildStatistics(array $tasks): array
    {
        $byScope = [];
        foreach ($tasks as $task) {
            $scope = $task['scope'];
            $byScope[$scope] ??= 0;
            ++$byScope[$scope];
        }

        return [
            'total' => count($tasks),
            'byScope' => $byScope,
        ];
    }
}
