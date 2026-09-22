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

use AutoDudes\AiSuite\Domain\Model\Dto\ProvenanceContext;
use AutoDudes\AiSuite\Domain\Repository\ProvenanceRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class ProvenanceService
{
    public const CACHE_TAG_PREFIX = 'tx_aisuite_provenance_';

    /** @var array<string, array<int, list<array<string, mixed>>>> */
    protected array $cache = [];

    public function __construct(
        protected readonly ProvenanceRepository $provenanceRepository,
        protected readonly ProvenanceStructureService $structureService,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
        protected readonly BackendUserService $backendUserService,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly CacheManager $cacheManager,
        protected readonly LoggerInterface $logger,
    ) {}

    public static function cacheTag(string $table, int $uid): string
    {
        return self::CACHE_TAG_PREFIX.$table.'_'.$uid;
    }

    public static function dateFormat(): string
    {
        return ($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? 'd.m.Y').' '.($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] ?? 'H:i');
    }

    public static function formatDate(int $timestamp): string
    {
        return $timestamp <= 0 ? '' : date(self::dateFormat(), $timestamp);
    }

    public function isEnabled(): bool
    {
        return $this->readSetting('provenanceTracking', true);
    }

    public function showsLabels(): bool
    {
        return $this->readSetting('provenanceShowLabels', true);
    }

    public function disclosesTranslations(): bool
    {
        return $this->readSetting('provenanceDiscloseTranslations', false);
    }

    /**
     * @param list<string> $writtenFields
     */
    public function record(ProvenanceContext $context, string $table, int $uid, array $writtenFields = []): void
    {
        if ('' === $table || $uid <= 0 || !$this->isEnabled()) {
            return;
        }

        try {
            if ([] === $this->fingerprintableFields($table, $writtenFields)) {
                $listable = $this->structureService->listableRecordFor($table, $uid);
                $table = $listable['table'];
                $uid = $listable['uid'];
            }

            $writtenFields = $this->fingerprintableFields($table, $writtenFields);

            $existing = $this->findForRecord($table, $uid)[0] ?? null;
            $stored = null === $existing ? [] : self::storedFingerprints($existing);
            $applied = self::applicableFingerprints(
                $stored,
                $this->fieldFingerprints($table, $uid, $writtenFields, $context->mode),
            );

            if (null !== $existing && [] === $applied
                && self::modeStrength($context->mode) < self::modeStrength((string) ($existing['mode'] ?? ''))) {
                return;
            }

            $merged = array_merge($stored, $applied);
            $fields = array_values(array_unique(array_merge(
                null === $existing ? [] : self::fieldsOf($existing),
                $writtenFields,
            )));
            sort($fields);

            $this->provenanceRepository->store([
                'tablename' => $table,
                'record_uid' => $uid,
                'workspace' => $this->resolveWorkspaceId($table, $uid),
                'mode' => self::strongestMode($merged, $context->mode),
                'model' => $context->model,
                'client' => $context->client,
                'feature' => $context->feature,
                'be_user' => $this->backendUserService->getBackendUser()?->getUserId() ?? 0,
                'crdate' => time(),
                'source_hash' => [] === $merged ? '' : (string) json_encode($merged),
                'source_fields' => implode(',', $fields),
                'reviewed_by' => 0,
                'reviewed_at' => 0,
                'edited_by' => 0,
                'edited_at' => 0,
            ]);
            unset($this->cache[$table][$uid]);
            $this->flushCacheTag($table, $uid);
        } catch (\Throwable $e) {
            // A failed register write must never fail the editorial action that produced the content.
            $this->logger->error('Could not record provenance', [
                'table' => $table,
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findForRecord(string $table, int $uid): array
    {
        if ('' === $table || $uid <= 0) {
            return [];
        }

        try {
            return $this->provenanceRepository->findForRecord($table, $uid);
        } catch (\Throwable $e) {
            $this->logger->error('Could not read the register', [
                'table' => $table,
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param list<int> $uids
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function findForRecords(string $table, array $uids): array
    {
        if ('' === $table || [] === $uids) {
            return [];
        }

        try {
            return $this->provenanceRepository->findForRecords($table, $uids);
        } catch (\Throwable $e) {
            $this->logger->error('Could not read the register', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function primeForPage(string $table, int $pageId): void
    {
        if ('' === $table || $pageId <= 0 || !$this->isEnabled()) {
            return;
        }

        try {
            $uids = $this->provenanceRepository->fetchUidsOnPage($table, $pageId);
            if ([] === $uids) {
                return;
            }

            $rows = $this->provenanceRepository->findForRecords($table, $uids);
            foreach ($uids as $uid) {
                $this->cache[$table][$uid] = $rows[$uid] ?? [];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Could not prime the register for the page', [
                'table' => $table,
                'pageUid' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function disclosedFor(string $table, int $uid): array
    {
        if (!$this->isEnabled() || !$this->showsLabels()) {
            return [];
        }

        $rows = $this->rowsOf($table, $uid);
        $disclosesTranslations = $this->disclosesTranslations();

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => !$this->isRowEdited($table, $uid, $row)
                && ($disclosesTranslations || ProvenanceContext::MODE_TRANSLATED !== ($row['mode'] ?? '')),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function disclosedForField(string $table, int $uid, string $field): array
    {
        if ('' === $field || !$this->isEnabled() || !$this->showsLabels()) {
            return [];
        }

        $disclosesTranslations = $this->disclosesTranslations();

        return array_values(array_filter(
            $this->rowsOf($table, $uid),
            fn (array $row): bool => in_array($field, self::fieldsOf($row), true)
                && !$this->isFieldEdited($table, $uid, $field, $row)
                && ($disclosesTranslations || ProvenanceContext::MODE_TRANSLATED !== ($row['mode'] ?? '')),
        ));
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, mixed>>
     */
    public function findForOverview(array $filters, int $limit, int $offset): array
    {
        try {
            return $this->provenanceRepository->findForOverview($filters, $limit, $offset);
        } catch (\Throwable $e) {
            $this->logger->error('Could not read the register', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param list<int> $fileUids
     *
     * @return array<int, array{table: string, uid: int}>
     */
    public function fileUsages(array $fileUids): array
    {
        try {
            return $this->provenanceRepository->fetchFileUsages($fileUids);
        } catch (\Throwable $e) {
            $this->logger->error('Could not read where the files are used', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    public function tablesInRegister(): array
    {
        try {
            return $this->provenanceRepository->fetchDistinctTables();
        } catch (\Throwable $e) {
            $this->logger->error('Could not read the register', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, int>
     */
    public function countByTableForOverview(array $filters): array
    {
        try {
            return $this->provenanceRepository->countByTable($filters);
        } catch (\Throwable $e) {
            $this->logger->error('Could not count the register', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countForOverview(array $filters): int
    {
        try {
            return $this->provenanceRepository->countForOverview($filters);
        } catch (\Throwable $e) {
            $this->logger->error('Could not count the register', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    public function markReviewed(string $table, int $uid, int $beUserUid): void
    {
        if ('' === $table || $uid <= 0) {
            return;
        }

        try {
            $this->provenanceRepository->updateReview($table, $uid, $beUserUid, time());
            unset($this->cache[$table][$uid]);
            $this->flushCacheTag($table, $uid);
        } catch (\Throwable $e) {
            $this->logger->error('Could not record the review', [
                'table' => $table,
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function markEdited(string $table, int $uid, int $beUserUid): void
    {
        if ('' === $table || $uid <= 0) {
            return;
        }

        try {
            $this->provenanceRepository->markEdited($table, $uid, $beUserUid, time());
            unset($this->cache[$table][$uid]);
            $this->flushCacheTag($table, $uid);
        } catch (\Throwable $e) {
            $this->logger->error('Could not record the edit', [
                'table' => $table,
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function isEditedSinceGeneration(string $table, int $uid): bool
    {
        $rows = $this->findForRecord($table, $uid);
        $row = $rows[0] ?? null;
        if (null === $row) {
            return false;
        }

        return $this->isRowEdited($table, $uid, $row);
    }

    /**
     * @return list<string>
     */
    public function generatedFields(string $table, int $uid): array
    {
        $fields = [];
        foreach ($this->findForRecord($table, $uid) as $row) {
            $stored = self::storedFingerprints($row);
            $rowIsAssisted = ProvenanceContext::MODE_ASSISTED === ($row['mode'] ?? '');

            foreach (self::fieldsOf($row) as $field) {
                $mode = $stored[$field]['m'] ?? ($rowIsAssisted ? ProvenanceContext::MODE_ASSISTED : '');
                if (ProvenanceContext::MODE_ASSISTED === $mode) {
                    continue;
                }

                $fields[] = $field;
            }
        }

        return array_values(array_unique($fields));
    }

    public function isAiGenerated(string $table, int $uid): bool
    {
        return [] !== $this->findForRecord($table, $uid);
    }

    public function transferAfterPublish(string $table, int $liveUid): void
    {
        // Deliberately not gated on the switch: turning recording off must never destroy an existing marking.
        if ('' === $table || $liveUid <= 0) {
            return;
        }

        try {
            if (!$this->tcaCompatibilityService->isWorkspaceAware($table)) {
                return;
            }

            $versionUids = $this->provenanceRepository->findVersionUids($table, $liveUid);

            if ($this->provenanceRepository->isDeleted($table, $liveUid)) {
                $this->purgeForRecord($table, $liveUid);
                foreach ($versionUids as $versionUid) {
                    $this->purgeForRecord($table, $versionUid);
                }

                return;
            }

            foreach ($versionUids as $versionUid) {
                if ([] === $this->provenanceRepository->findForRecord($table, $versionUid)) {
                    continue;
                }

                $this->provenanceRepository->moveToRecord($table, $versionUid, $liveUid);
                unset($this->cache[$table][$versionUid], $this->cache[$table][$liveUid]);
                $this->flushCacheTag($table, $versionUid);
                $this->flushCacheTag($table, $liveUid);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Could not transfer provenance to the published record', [
                'table' => $table,
                'uid' => $liveUid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, list<int>>
     */
    public function findOrphans(): array
    {
        $orphans = [];

        try {
            foreach ($this->provenanceRepository->fetchDistinctTables() as $table) {
                $uids = $this->provenanceRepository->fetchRecordUids($table);
                if ([] === $uids) {
                    continue;
                }

                if (ProvenanceStructureService::AREA_NONE === $this->structureService->areaOf($table)) {
                    $structural = $this->provenanceRepository->fetchStructuralRecordUids($table);
                    if ([] !== $structural) {
                        $orphans[$table] = $structural;
                    }

                    continue;
                }

                $existing = array_map(intval(...), array_column(
                    $this->provenanceRepository->fetchColumns($table, $uids, ['uid']),
                    'uid',
                ));

                $missing = array_values(array_diff($uids, $existing));
                if ([] !== $missing) {
                    $orphans[$table] = $missing;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Could not look for orphaned register entries', ['error' => $e->getMessage()]);
        }

        return $orphans;
    }

    public function countOrphans(): int
    {
        return array_sum(array_map(count(...), $this->findOrphans()));
    }

    public function purgeOrphans(): int
    {
        $removed = 0;

        foreach ($this->findOrphans() as $table => $uids) {
            foreach ($uids as $uid) {
                $this->purgeForRecord($table, $uid);
                ++$removed;
            }
        }

        return $removed;
    }

    public function purgeForRecord(string $table, int $uid): void
    {
        if ('' === $table || $uid <= 0) {
            return;
        }

        try {
            $this->provenanceRepository->deleteForRecord($table, $uid);
            unset($this->cache[$table][$uid]);
            $this->flushCacheTag($table, $uid);
        } catch (\Throwable $e) {
            $this->logger->error('Could not purge provenance', [
                'table' => $table,
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function isFieldEdited(string $table, int $uid, string $field, array $row): bool
    {
        $stored = self::storedFingerprints($row);
        if ([] === $stored) {
            return ProvenanceContext::MODE_ASSISTED !== ($row['mode'] ?? '')
                && $this->isRowEdited($table, $uid, $row);
        }

        if (!isset($stored[$field]) || ProvenanceContext::MODE_ASSISTED === $stored[$field]['m']) {
            return false;
        }

        return $stored[$field]['h'] !== $this->fingerprint($table, $uid, [$field]);
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function isRowEdited(string $table, int $uid, array $row): bool
    {
        $stored = self::storedFingerprints($row);

        if ([] === $stored
            ? ProvenanceContext::MODE_ASSISTED === ($row['mode'] ?? '')
            : [] === array_filter($stored, static fn (array $e): bool => ProvenanceContext::MODE_ASSISTED !== $e['m'])) {
            return false;
        }

        if (0 < (int) ($row['edited_at'] ?? 0)) {
            return true;
        }

        if ([] === $stored) {
            $fields = self::fieldsOf($row);
            $storedHash = (string) ($row['source_hash'] ?? '');

            return [] !== $fields && '' !== $storedHash
                && $storedHash !== $this->fingerprint($table, $uid, $fields);
        }

        foreach ($stored as $field => $entry) {
            if (ProvenanceContext::MODE_ASSISTED === $entry['m']) {
                continue;
            }

            if ($entry['h'] !== $this->fingerprint($table, $uid, [$field])) {
                return true;
            }
        }

        return false;
    }

    protected function flushCacheTag(string $table, int $uid): void
    {
        try {
            $this->cacheManager->getCache('pages')->flushByTag(self::cacheTag($table, $uid));
        } catch (\Throwable $e) {
            $this->logger->warning('Could not flush the provenance cache tag', [
                'table' => $table,
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param list<string> $fields
     */
    protected function fingerprint(string $table, int $uid, array $fields): string
    {
        return self::hashOf($this->fieldValues($table, $uid, $fields));
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, mixed>
     */
    protected function fieldValues(string $table, int $uid, array $fields): array
    {
        if ([] === $fields) {
            return [];
        }

        try {
            return $this->provenanceRepository->fetchFieldValues($table, $uid, $fields);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    protected static function hashOf(array $values): string
    {
        if ([] === $values) {
            return '';
        }

        ksort($values);

        return sha1(serialize($values));
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, array{h: string, m: string}>
     */
    protected function fieldFingerprints(string $table, int $uid, array $fields, string $mode): array
    {
        $values = $this->fieldValues($table, $uid, $fields);

        $map = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $map[$field] = ['h' => self::hashOf([$field => $values[$field]]), 'm' => $mode];
        }

        return $map;
    }

    /**
     * @param array<string, array{h: string, m: string}> $stored
     * @param array<string, array{h: string, m: string}> $incoming
     *
     * @return array<string, array{h: string, m: string}>
     */
    protected static function applicableFingerprints(array $stored, array $incoming): array
    {
        $applied = [];
        foreach ($incoming as $field => $entry) {
            if (isset($stored[$field])
                && self::modeStrength($stored[$field]['m']) > self::modeStrength($entry['m'])) {
                continue;
            }

            $applied[$field] = $entry;
        }

        return $applied;
    }

    /**
     * @param array<string, array{h: string, m: string}> $map
     */
    protected static function strongestMode(array $map, string $fallback): string
    {
        $strongest = $fallback;
        foreach ($map as $entry) {
            if (self::modeStrength($entry['m']) > self::modeStrength($strongest)) {
                $strongest = $entry['m'];
            }
        }

        return $strongest;
    }

    protected static function modeStrength(string $mode): int
    {
        return match ($mode) {
            ProvenanceContext::MODE_GENERATED => 3,
            ProvenanceContext::MODE_TRANSLATED => 2,
            ProvenanceContext::MODE_ASSISTED => 1,
            default => 0,
        };
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, array{h: string, m: string}>
     */
    protected static function storedFingerprints(array $row): array
    {
        $stored = (string) ($row['source_hash'] ?? '');
        if ('' === $stored || !str_starts_with($stored, '{')) {
            return [];
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rowMode = (string) ($row['mode'] ?? ProvenanceContext::MODE_GENERATED);

        $map = [];
        foreach ($decoded as $field => $entry) {
            $map[(string) $field] = is_array($entry)
                ? ['h' => (string) ($entry['h'] ?? ''), 'm' => (string) ($entry['m'] ?? $rowMode)]
                : ['h' => (string) $entry, 'm' => $rowMode];
        }

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rowsOf(string $table, int $uid): array
    {
        return $this->cache[$table][$uid] ??= $this->findForRecord($table, $uid);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    protected static function fieldsOf(array $row): array
    {
        return array_values(array_filter(explode(',', (string) ($row['source_fields'] ?? ''))));
    }

    /**
     * @param list<string> $fields
     *
     * @return list<string>
     */
    protected function fingerprintableFields(string $table, array $fields): array
    {
        if ([] === $fields || !$this->tcaCompatibilityService->hasTable($table)) {
            return [];
        }

        try {
            $columns = $this->tcaCompatibilityService->getColumnConfigs($table);
        } catch (\Throwable) {
            return [];
        }

        $housekeeping = $this->tcaCompatibilityService->getHousekeepingFields();

        $fingerprintable = [];
        foreach ($fields as $field) {
            $type = (string) ($columns[$field]['type'] ?? '');
            if (in_array($field, $housekeeping, true) || !in_array($type, ['input', 'text'], true)) {
                continue;
            }

            $fingerprintable[] = $field;
        }

        return $fingerprintable;
    }

    protected function resolveWorkspaceId(string $table, int $uid): int
    {
        if (!$this->tcaCompatibilityService->isWorkspaceAware($table)) {
            return 0;
        }

        return $this->provenanceRepository->fetchWorkspaceId($table, $uid);
    }

    protected function readSetting(string $key, bool $default): bool
    {
        try {
            $extConf = $this->extensionConfiguration->get('ai_suite');
        } catch (\Throwable) {
            return $default;
        }

        if (!is_array($extConf) || !array_key_exists($key, $extConf)) {
            return $default;
        }

        return (bool) $extConf[$key];
    }
}
