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

use AutoDudes\AiSuite\Domain\Repository\SchedulerTaskRepository;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class CliCommandAvailabilityService implements SingletonInterface
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_WORKFLOW_TYPES = [
        'page',
        'pageTranslate',
        'fileReferences',
        'fileMetadata',
        'fileMetadataTranslation',
    ];

    private const REQUIRED_COMMAND_NAME = 'ai-suite:process-tasks';

    private ?bool $processTasksCommandScheduledCache = null;

    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly CommandRegistry $commandRegistry,
        protected readonly SchedulerTaskRepository $schedulerTaskRepository,
    ) {}

    public function isCliExecutionAvailable(string $workflowType): bool
    {
        if (!$this->backendUserService->checkPermissions('tx_aisuite_features:enable_cli_workflow_execution')) {
            return false;
        }
        if (!ExtensionManagementUtility::isLoaded('scheduler')) {
            return false;
        }
        if (!in_array($workflowType, self::SUPPORTED_WORKFLOW_TYPES, true)) {
            return false;
        }
        if (!$this->commandRegistry->has(self::REQUIRED_COMMAND_NAME)) {
            return false;
        }

        return $this->isProcessTasksCommandScheduled();
    }

    public function isProcessTasksCommandScheduled(): bool
    {
        if (null !== $this->processTasksCommandScheduledCache) {
            return $this->processTasksCommandScheduledCache;
        }

        $this->processTasksCommandScheduledCache = $this->schedulerTaskRepository
            ->countActiveSchedulableCommandTasks(self::REQUIRED_COMMAND_NAME) > 0
        ;

        return $this->processTasksCommandScheduledCache;
    }
}
