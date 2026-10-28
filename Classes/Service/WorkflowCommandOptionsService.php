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
use TYPO3\CMS\Core\SingletonInterface;

class WorkflowCommandOptionsService implements SingletonInterface
{
    public const COMMAND_IDENTIFIER = 'ai-suite:execute-workflow';

    public const JAVASCRIPT_MODULE = '@autodudes/ai-suite/scheduler/workflow-command-options.js';

    /**
     * @var array<string, array{table: string, columns: list<string>}>
     */
    private const COLUMNS = [
        'page' => [
            'table' => 'pages',
            'columns' => ['seo_title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'],
        ],
        'fileReferences' => [
            'table' => 'sys_file_reference',
            'columns' => ['title', 'alternative', 'description'],
        ],
        'fileMetadata' => [
            'table' => 'sys_file_metadata',
            'columns' => ['title', 'alternative', 'description'],
        ],
        'fileMetadataTranslation' => [
            'table' => 'sys_file_metadata',
            'columns' => ['title', 'alternative', 'description', 'all'],
        ],
    ];

    public function __construct(
        protected readonly LibraryService $libraryService,
        protected readonly LocalizationService $localizationService,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, string>
     */
    public function getTypes(): array
    {
        return WorkflowProcessingService::WORKFLOW_TYPES;
    }

    /**
     * @return array<string, string>
     */
    public function getColumns(string $type): array
    {
        if (!isset(self::COLUMNS[$type])) {
            return [];
        }
        $table = self::COLUMNS[$type]['table'];
        $columns = [];
        foreach (self::COLUMNS[$type]['columns'] as $column) {
            $columns[$column] = $this->localizationService->translate('module:aiSuite.module.workflow.columns.'.$table.'.'.$column);
        }

        return $columns;
    }

    /**
     * @return null|array<string, string>
     */
    public function getModels(string $type): ?array
    {
        try {
            return $this->libraryService->findModelsForWorkflowType($type);
        } catch (\RuntimeException $e) {
            $this->logger->warning('Models for the workflow command could not be fetched', [
                'type' => $type,
                'exception' => $e,
            ]);

            return null;
        }
    }

    public function renderOptionsElement(): string
    {
        return sprintf(
            '<div hidden data-ai-suite-workflow-command-options="%s"></div>',
            htmlspecialchars(json_encode($this->getOptions(), JSON_THROW_ON_ERROR))
        );
    }

    /**
     * @return array{types: array<string, string>, columns: array<string, array<string, string>>, models: array<string, null|array<string, string>>}
     */
    public function getOptions(): array
    {
        $columns = [];
        $models = [];
        foreach (array_keys($this->getTypes()) as $type) {
            $columns[$type] = $this->getColumns($type);
            $models[$type] = $this->getModels($type);
        }

        return [
            'types' => $this->getTypes(),
            'columns' => $columns,
            'models' => $models,
        ];
    }
}
