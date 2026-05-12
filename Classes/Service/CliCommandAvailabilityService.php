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

use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class CliCommandAvailabilityService implements SingletonInterface
{
    /**
     * Workflow types currently processable end-to-end via CLI / scheduler.
     *
     * @var list<string>
     */
    private const SUPPORTED_WORKFLOW_TYPES = [
        'page',
        'pageTranslate',
        'fileReferences',
        'fileMetadata',
        'fileMetadataTranslation',
    ];

    /**
     * Command names that must be registered for the CLI-trigger UI to make sense.
     * The result-saving command is the bare minimum: without it, CLI-handled tasks
     * would never be persisted back to TYPO3 records.
     */
    private const REQUIRED_COMMAND_NAME = 'ai-suite:process-tasks';

    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly CommandRegistry $commandRegistry,
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

        return $this->commandRegistry->has(self::REQUIRED_COMMAND_NAME);
    }
}
