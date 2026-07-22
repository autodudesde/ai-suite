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

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\MultiLanguageTranslationService;
use AutoDudes\AiSuite\Service\TcaCompatibilityService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\SingletonInterface;

class AutoTranslationHook implements SingletonInterface
{
    private static int $suspensionLevel = 0;

    private bool $hookSuspended = false;

    /**
     * @var array<int, array{pageId: int, changedFields: list<string>}>
     */
    private array $autoTranslateQueue = [];

    public function __construct(
        protected readonly MultiLanguageTranslationService $multiLanguageTranslationService,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly BackendUserService $backendUserService,
        protected readonly LoggerInterface $logger,
    ) {}

    public static function runWithSuspendedHook(callable $callback): mixed
    {
        ++self::$suspensionLevel;

        try {
            return $callback();
        } finally {
            --self::$suspensionLevel;
        }
    }

    /**
     * @param string               $status
     * @param string               $table
     * @param int|string           $recordUid
     * @param array<string, mixed> $fields
     */
    public function processDatamap_afterDatabaseOperations($status, $table, $recordUid, array $fields, DataHandler $parentObject): void
    {
        if ($this->isSuspended() || 'tt_content' !== $table || !$this->isBackendContext()) {
            return;
        }

        $changedFields = array_values(array_map('strval', array_keys($fields)));
        if (
            !$this->tcaCompatibilityService->hasTranslatableFieldChange($table, $changedFields, $this->isFlexFormTranslationEnabled())
            && !$this->tcaCompatibilityService->hasStructuralRelationChange($table, $changedFields)
        ) {
            return;
        }

        if (isset($parentObject->substNEWwithIDs[$recordUid])) {
            $recordUid = $parentObject->substNEWwithIDs[$recordUid];
        }
        $recordUid = (int) $recordUid;
        if ($recordUid <= 0) {
            return;
        }

        $record = BackendUtility::getRecord('tt_content', $recordUid, 'uid, pid, sys_language_uid');
        if (!is_array($record)) {
            return;
        }

        if (0 !== (int) ($record['sys_language_uid'] ?? -1)) {
            return;
        }

        $pageId = (int) ($record['pid'] ?? 0);
        if ($pageId <= 0) {
            return;
        }

        $existingChangedFields = $this->autoTranslateQueue[$recordUid]['changedFields'] ?? [];
        $this->autoTranslateQueue[$recordUid] = [
            'pageId' => $pageId,
            'changedFields' => array_values(array_unique([...$existingChangedFields, ...$changedFields])),
        ];
    }

    public function processDatamap_afterAllOperations(DataHandler $parentObject): void
    {
        if ($this->isSuspended() || [] === $this->autoTranslateQueue) {
            return;
        }

        $queue = $this->autoTranslateQueue;
        $this->autoTranslateQueue = [];

        // Never auto-translate inside a workspace
        if (($this->backendUserService->getBackendUser()?->workspace ?? 0) > 0) {
            return;
        }

        $maxElements = $this->getMaxElementsPerSave();
        if ($maxElements > 0 && count($queue) > $maxElements) {
            $this->logger->warning('Auto translation skipped: bulk save exceeds the configured element cap', [
                'changedElements' => count($queue),
                'cap' => $maxElements,
            ]);
            $this->multiLanguageTranslationService->notifyBulkSaveLimitExceeded(count($queue), $maxElements);

            return;
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        foreach ($queue as $sourceUid => $queueEntry) {
            $pageId = $queueEntry['pageId'];
            $changedFields = $queueEntry['changedFields'];

            try {
                self::runWithSuspendedHook(function () use ($sourceUid, $pageId, $changedFields, $request): void {
                    $this->multiLanguageTranslationService->translateContentElementToAllLanguages($sourceUid, $pageId, $request, $changedFields);
                });
            } catch (\Throwable $e) {
                $this->logger->error('Auto translation hook failed', [
                    'sourceUid' => $sourceUid,
                    'pageId' => $pageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param int|string $id
     * @param mixed      $value
     * @param mixed      $commandIsProcessed
     * @param mixed      $pasteUpdate
     */
    public function processCmdmap(string $command, string $table, $id, $value, $commandIsProcessed, DataHandler $dataHandler, $pasteUpdate): void
    {
        if ('copy' === $command || 'localize' === $command) {
            $this->hookSuspended = true;
        }
    }

    /**
     * @param int|string $id
     * @param mixed      $value
     * @param mixed      $pasteUpdate
     * @param mixed      $pasteDatamap
     */
    public function processCmdmap_postProcess(string $command, string $table, $id, $value, DataHandler $dataHandler, $pasteUpdate, $pasteDatamap): void
    {
        if ('copy' === $command || 'localize' === $command) {
            $this->hookSuspended = false;
        }
    }

    private function isFlexFormTranslationEnabled(): bool
    {
        try {
            $extConf = $this->extensionConfiguration->get('ai_suite');

            return is_array($extConf)
                && array_key_exists('translateFlexFormFields', $extConf)
                && (bool) $extConf['translateFlexFormFields'];
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getMaxElementsPerSave(): int
    {
        try {
            $extConf = $this->extensionConfiguration->get('ai_suite');
            if (is_array($extConf) && array_key_exists('autoTranslateMaxElementsPerSave', $extConf)) {
                return max(0, (int) $extConf['autoTranslateMaxElementsPerSave']);
            }
        } catch (\Throwable $e) {
            // fall through to default
        }

        return 15;
    }

    private function isSuspended(): bool
    {
        return $this->hookSuspended || self::$suspensionLevel > 0;
    }

    private function isBackendContext(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (null === $request) {
            return !Environment::isCli();
        }

        try {
            return ApplicationType::fromRequest($request)->isBackend();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
