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

namespace AutoDudes\AiSuite\FormEngine\FieldInformation;

use AutoDudes\AiSuite\Service\WorkflowCommandOptionsService;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class WorkflowCommandOptionsInformation extends AbstractNode
{
    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        $resultArray = $this->initializeResultArray();

        $taskType = $this->data['databaseRow']['tasktype'] ?? '';
        if (is_array($taskType)) {
            $taskType = $taskType[0] ?? '';
        }
        if (WorkflowCommandOptionsService::COMMAND_IDENTIFIER !== $taskType) {
            return $resultArray;
        }

        $resultArray['html'] = GeneralUtility::makeInstance(WorkflowCommandOptionsService::class)->renderOptionsElement();
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(WorkflowCommandOptionsService::JAVASCRIPT_MODULE);

        return $resultArray;
    }
}
