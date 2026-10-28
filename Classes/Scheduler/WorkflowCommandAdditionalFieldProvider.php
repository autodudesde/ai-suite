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

namespace AutoDudes\AiSuite\Scheduler;

use AutoDudes\AiSuite\Service\WorkflowCommandOptionsService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\ExecuteSchedulableCommandTask;

class WorkflowCommandAdditionalFieldProvider implements AdditionalFieldProviderInterface
{
    public const DECORATED_PROVIDER = 'TYPO3\CMS\Scheduler\Task\ExecuteSchedulableCommandAdditionalFieldProvider';

    private AdditionalFieldProviderInterface $decorated;

    public function __construct()
    {
        $decorated = GeneralUtility::makeInstance(self::DECORATED_PROVIDER); // @phpstan-ignore argument.type, argument.templateType (class only exists up to TYPO3 v13)
        if (!$decorated instanceof AdditionalFieldProviderInterface) {
            throw new \LogicException(self::DECORATED_PROVIDER.' is not available', 1761134401);
        }
        $this->decorated = $decorated;
    }

    /**
     * @param array<string, mixed> $taskInfo
     * @param null|AbstractTask    $task
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $fields = $this->decorated->getAdditionalFields($taskInfo, $task, $schedulerModule);

        if (!$task instanceof ExecuteSchedulableCommandTask
            || WorkflowCommandOptionsService::COMMAND_IDENTIFIER !== $task->getCommandIdentifier() // @phpstan-ignore method.notFound (removed in TYPO3 v14, only reached on v12/v13)
            || !isset($fields['schedulableCommands']['code'])) {
            return $fields;
        }

        $fields['schedulableCommands']['code'] .= GeneralUtility::makeInstance(WorkflowCommandOptionsService::class)->renderOptionsElement();
        GeneralUtility::makeInstance(PageRenderer::class)->loadJavaScriptModule(WorkflowCommandOptionsService::JAVASCRIPT_MODULE);

        return $fields;
    }

    /**
     * @param array<string, mixed> $submittedData
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        return $this->decorated->validateAdditionalFields($submittedData, $schedulerModule);
    }

    /**
     * @param array<string, mixed> $submittedData
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task): bool
    {
        return (bool) $this->decorated->saveAdditionalFields($submittedData, $task);
    }
}
