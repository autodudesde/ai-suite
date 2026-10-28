<?php

declare(strict_types=1);

use AutoDudes\AiSuite\Service\WorkflowCommandOptionsService;

// TYPO3 v14+: every schedulable command is its own task type; v12/v13 use WorkflowCommandAdditionalFieldProvider
if (isset($GLOBALS['TCA']['tx_scheduler_task']['types'][WorkflowCommandOptionsService::COMMAND_IDENTIFIER])) {
    $GLOBALS['TCA']['tx_scheduler_task']['types'][WorkflowCommandOptionsService::COMMAND_IDENTIFIER]['columnsOverrides']['parameters']['config']['fieldInformation']['aiSuiteWorkflowCommandOptions']['renderType'] = 'aiSuiteWorkflowCommandOptions';
}
