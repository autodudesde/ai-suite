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

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Service\ProvenanceCaptureService;
use AutoDudes\AiSuite\Service\ProvenanceService;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ProvenanceHook
{
    /**
     * @param string       $status
     * @param string       $table
     * @param int|string   $recordUid
     * @param array<mixed> $fields
     */
    public function processDatamap_afterDatabaseOperations($status, $table, $recordUid, array $fields, DataHandler $dataHandler): void
    {
        if (isset($dataHandler->substNEWwithIDs[$recordUid])) {
            $recordUid = $dataHandler->substNEWwithIDs[$recordUid];
        }

        $uid = (int) $recordUid;
        if ($uid <= 0) {
            return;
        }

        $capture = GeneralUtility::makeInstance(ProvenanceCaptureService::class);
        if ($capture->isActive()) {
            $capture->capture((string) $table, $uid, 'new' === $status ? 'create' : 'update', $fields);

            return;
        }

        $service = GeneralUtility::makeInstance(ProvenanceService::class);
        if (!$service->isEnabled()) {
            return;
        }

        $this->recordEdit($service, (string) $table, $uid, $fields, $dataHandler);
    }

    /**
     * @param int|string $id
     * @param mixed      $value
     * @param mixed      $pasteUpdate
     * @param mixed      $pasteDatamap
     */
    public function processCmdmap_postProcess(string $command, string $table, $id, $value, DataHandler $dataHandler, $pasteUpdate, $pasteDatamap): void
    {
        if ('delete' === $command || 'discard' === $command) {
            // A workspace delete is a draft; the live marking stands until publish clears it.
            if ('delete' === $command && (int) ($dataHandler->BE_USER->workspace ?? 0) > 0) {
                return;
            }

            GeneralUtility::makeInstance(ProvenanceService::class)->purgeForRecord($table, (int) $id);

            return;
        }

        $capture = GeneralUtility::makeInstance(ProvenanceCaptureService::class);
        if (!$capture->isActive()) {
            return;
        }

        switch ($command) {
            case 'move':
                $uid = (int) $id;
                $action = 'update';

                break;

            case 'copy':
            case 'localize':
                $uid = (int) ($dataHandler->copyMappingArray[$table][$id] ?? 0);
                $action = 'create';

                break;

            default:
                return;
        }

        if ($uid <= 0) {
            return;
        }

        $capture->capture($table, $uid, $action);
    }

    /**
     * @param array<mixed> $fields
     */
    protected function recordEdit(ProvenanceService $service, string $table, int $uid, array $fields, DataHandler $dataHandler): void
    {
        $written = array_keys($fields);
        if ([] === $written) {
            return;
        }

        $generated = $service->generatedFields($table, $uid);

        if ([] === array_intersect($generated, $written)) {
            return;
        }

        $service->markEdited($table, $uid, (int) ($dataHandler->BE_USER->getUserId() ?? 0));
    }
}
