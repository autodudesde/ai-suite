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

use AutoDudes\AiSuite\Domain\Repository\ProvenanceRepository;

/**
 * Answers two questions for a register row: who owns the record, and under what it groups.
 */
class ProvenanceStructureService
{
    public const TYPE_PAGE = 'page';
    public const TYPE_FOLDER = 'folder';
    public const TYPE_RECORD = 'record';
    public const TYPE_NONE = 'none';

    public const AREA_PAGES = 'pages';
    public const AREA_FILES = 'files';
    public const AREA_RECORDS = 'records';
    public const AREA_NONE = '';

    /** @var list<string> */
    public const AREAS = [self::AREA_PAGES, self::AREA_FILES, self::AREA_RECORDS];

    /** @var list<string> */
    public const PAGE_TABLES = ['pages', 'tt_content'];

    private const SHAPE_RELATION = 'relation';
    private const SHAPE_FILE = 'file';
    private const SHAPE_FILE_METADATA = 'fileMetadata';
    private const SHAPE_CHILD = 'child';
    private const SHAPE_ON_PAGE = 'onPage';
    private const SHAPE_STANDALONE = 'standalone';

    /** @var array<string, string> */
    private array $shapes = [];

    /** @var array<string, null|array{table: string, field: string}> */
    private array $irreParents = [];

    public function __construct(
        protected readonly ProvenanceRepository $provenanceRepository,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
    ) {}

    public function areaOf(string $table): string
    {
        if (in_array($table, self::PAGE_TABLES, true)) {
            return self::AREA_PAGES;
        }

        return match ($this->shapeOf($table)) {
            self::SHAPE_RELATION, self::SHAPE_CHILD => self::AREA_NONE,
            self::SHAPE_FILE, self::SHAPE_FILE_METADATA => self::AREA_FILES,
            default => self::AREA_RECORDS,
        };
    }

    /**
     * @param list<string> $tables
     *
     * @return list<string>
     */
    public function tablesForArea(string $area, array $tables): array
    {
        return array_values(array_filter(
            $tables,
            fn (string $table): bool => $area === $this->areaOf($table),
        ));
    }

    /**
     * @param list<string> $tables
     *
     * @return list<string>
     */
    public function unlistedTables(array $tables): array
    {
        return $this->tablesForArea(self::AREA_NONE, $tables);
    }

    /**
     * @return array{table: string, uid: int}
     */
    public function listableRecordFor(string $table, int $uid): array
    {
        for ($hop = 0; $hop < 3 && self::AREA_NONE === $this->areaOf($table); ++$hop) {
            $owner = $this->resolveOwners([['table' => $table, 'uid' => $uid]])[$this->keyOf($table, $uid)] ?? null;
            if (null === $owner) {
                break;
            }

            $table = $owner['table'];
            $uid = $owner['uid'];
        }

        return ['table' => $table, 'uid' => $uid];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{group: array{type: string, table: string, uid: int, identifier: string}, entries: list<array{index: int, children: list<int>}>}>
     */
    public function analyse(array $rows, string $area = self::AREA_RECORDS): array
    {
        $owners = $this->resolveOwners($this->recordsOf($rows));

        $byRecord = [];
        foreach ($rows as $index => $row) {
            $byRecord[$this->keyOf((string) $row['tablename'], (int) $row['record_uid'])] = $index;
        }

        $nestedUnder = [];
        foreach ($rows as $index => $row) {
            $owner = $owners[$this->keyOf((string) $row['tablename'], (int) $row['record_uid'])] ?? null;
            $parent = null !== $owner ? ($byRecord[$this->keyOf($owner['table'], $owner['uid'])] ?? null) : null;

            if (self::AREA_PAGES === $area && null !== $owner && 'pages' === $owner['table']) {
                $parent = null;
            }

            $nestedUnder[$index] = null !== $parent && $parent !== $index ? $parent : null;
        }

        $groups = [];
        foreach ($rows as $index => $row) {
            if (null !== $nestedUnder[$index]) {
                continue;
            }

            $group = $this->groupOf((string) $row['tablename'], (int) $row['record_uid'], $owners, $area);
            $key = implode(':', [$group['type'], $group['table'], $group['uid'], $group['identifier']]);
            $groups[$key] ??= ['group' => $group, 'entries' => []];
            $groups[$key]['entries'][] = ['index' => $index, 'children' => []];
        }

        foreach ($nestedUnder as $index => $parent) {
            if (null === $parent) {
                continue;
            }

            $top = $this->topMost($parent, $nestedUnder);
            foreach ($groups as $key => $group) {
                foreach ($group['entries'] as $position => $entry) {
                    if ($entry['index'] === $top) {
                        $groups[$key]['entries'][$position]['children'][] = $index;
                    }
                }
            }
        }

        return array_values($groups);
    }

    /**
     * @param list<array{table: string, uid: int}> $records
     *
     * @return array<string, null|array{table: string, uid: int}>
     */
    public function resolveOwners(array $records): array
    {
        $byTable = [];
        foreach ($records as $record) {
            $byTable[$record['table']][] = $record['uid'];
        }

        $owners = [];
        foreach ($byTable as $table => $uids) {
            $uids = array_values(array_unique($uids));
            foreach ($this->ownersForTable((string) $table, $uids) as $uid => $owner) {
                $owners[$this->keyOf((string) $table, (int) $uid)] = $owner;
            }
        }

        return $owners;
    }

    /**
     * @param array<int, null|int> $nestedUnder
     */
    protected function topMost(int $index, array $nestedUnder): int
    {
        for ($hop = 0; $hop < 5 && null !== ($nestedUnder[$index] ?? null); ++$hop) {
            $index = (int) $nestedUnder[$index];
        }

        return $index;
    }

    /**
     * @param list<int> $uids
     *
     * @return array<int, null|array{table: string, uid: int}>
     */
    protected function ownersForTable(string $table, array $uids): array
    {
        return match ($this->shapeOf($table)) {
            self::SHAPE_RELATION => $this->ownersFromRelation($table, $uids),
            self::SHAPE_FILE => $this->ownersFromFileUsage($uids),
            self::SHAPE_FILE_METADATA => $this->ownersFromColumn($table, $uids, (string) $this->fileFieldOf($table), 'sys_file'),
            self::SHAPE_CHILD => $this->ownersFromChild($table, $uids),
            self::SHAPE_ON_PAGE => $this->ownersFromColumn($table, $uids, 'pid', 'pages'),
            default => array_fill_keys($uids, null),
        };
    }

    /**
     * @param list<int> $uids
     *
     * @return array<int, null|array{table: string, uid: int}>
     */
    protected function ownersFromRelation(string $table, array $uids): array
    {
        $owners = array_fill_keys($uids, null);
        foreach ($this->provenanceRepository->fetchColumns($table, $uids, ['uid', 'tablenames', 'uid_foreign']) as $row) {
            $foreignTable = (string) ($row['tablenames'] ?? '');
            $foreignUid = (int) ($row['uid_foreign'] ?? 0);
            if ('' !== $foreignTable && $foreignUid > 0) {
                $owners[(int) $row['uid']] = ['table' => $foreignTable, 'uid' => $foreignUid];
            }
        }

        return $owners;
    }

    /**
     * @param list<int> $uids
     *
     * @return array<int, null|array{table: string, uid: int}>
     */
    protected function ownersFromFileUsage(array $uids): array
    {
        $owners = array_fill_keys($uids, null);
        foreach ($this->provenanceRepository->fetchFileUsages($uids) as $fileUid => $usage) {
            $owners[$fileUid] = $usage;
        }

        return $owners;
    }

    /**
     * @param list<int> $uids
     *
     * @return array<int, null|array{table: string, uid: int}>
     */
    protected function ownersFromColumn(string $table, array $uids, string $column, string $foreignTable): array
    {
        $owners = array_fill_keys($uids, null);
        if ('' === $column) {
            return $owners;
        }

        foreach ($this->provenanceRepository->fetchColumns($table, $uids, ['uid', $column]) as $row) {
            $foreignUid = (int) ($row[$column] ?? 0);
            if ($foreignUid > 0) {
                $owners[(int) $row['uid']] = ['table' => $foreignTable, 'uid' => $foreignUid];
            }
        }

        return $owners;
    }

    /**
     * @param list<int> $uids
     *
     * @return array<int, null|array{table: string, uid: int}>
     */
    protected function ownersFromChild(string $table, array $uids): array
    {
        $parent = $this->irreParentOf($table);
        if (null === $parent) {
            return array_fill_keys($uids, null);
        }

        $owners = $this->ownersFromColumn($table, $uids, $parent['field'], $parent['table']);

        $orphans = array_keys(array_filter($owners, static fn (?array $owner): bool => null === $owner));
        if ([] === $orphans) {
            return $owners;
        }

        foreach ($this->ownersFromColumn($table, array_values($orphans), 'pid', 'pages') as $uid => $owner) {
            $owners[$uid] = $owner;
        }

        return $owners;
    }

    /**
     * @param array<string, null|array{table: string, uid: int}> $owners
     *
     * @return array{type: string, table: string, uid: int, identifier: string}
     */
    protected function groupOf(string $table, int $uid, array $owners, string $area): array
    {
        return match ($area) {
            self::AREA_FILES => $this->folderGroupOf($table, $uid, $owners),
            self::AREA_PAGES => $this->pageGroupOf($table, $uid, $owners),
            default => $this->rootOf($table, $uid, $owners),
        };
    }

    /**
     * @param array<string, null|array{table: string, uid: int}> $owners
     *
     * @return array{type: string, table: string, uid: int, identifier: string}
     */
    protected function folderGroupOf(string $table, int $uid, array $owners): array
    {
        if (self::SHAPE_FILE_METADATA === $this->shapeOf($table)) {
            $owner = $owners[$this->keyOf($table, $uid)] ?? null;
            if (null === $owner) {
                return $this->noGroup();
            }

            $table = $owner['table'];
            $uid = $owner['uid'];
        }

        $folder = self::SHAPE_FILE === $this->shapeOf($table)
            ? $this->provenanceRepository->fetchFileFolder($uid)
            : null;

        return null !== $folder
            ? ['type' => self::TYPE_FOLDER, 'table' => '', 'uid' => 0, 'identifier' => $folder]
            : $this->noGroup();
    }

    /**
     * @param array<string, null|array{table: string, uid: int}> $owners
     *
     * @return array{type: string, table: string, uid: int, identifier: string}
     */
    protected function pageGroupOf(string $table, int $uid, array $owners): array
    {
        return 'pages' === $table
            ? ['type' => self::TYPE_PAGE, 'table' => 'pages', 'uid' => $uid, 'identifier' => '']
            : $this->rootOf($table, $uid, $owners);
    }

    /**
     * @return array{type: string, table: string, uid: int, identifier: string}
     */
    protected function noGroup(): array
    {
        return ['type' => self::TYPE_NONE, 'table' => '', 'uid' => 0, 'identifier' => ''];
    }

    /**
     * @param array<string, null|array{table: string, uid: int}> $owners
     *
     * @return array{type: string, table: string, uid: int, identifier: string}
     */
    protected function rootOf(string $table, int $uid, array $owners): array
    {
        $currentTable = $table;
        $currentUid = $uid;

        for ($hop = 0; $hop < 5; ++$hop) {
            $owner = $owners[$this->keyOf($currentTable, $currentUid)] ?? null;
            if (null === $owner) {
                $owner = $this->resolveOwners([['table' => $currentTable, 'uid' => $currentUid]])[$this->keyOf($currentTable, $currentUid)] ?? null;
            }

            if (null === $owner) {
                break;
            }

            $currentTable = $owner['table'];
            $currentUid = $owner['uid'];

            if ('pages' === $currentTable) {
                return ['type' => self::TYPE_PAGE, 'table' => 'pages', 'uid' => $currentUid, 'identifier' => ''];
            }
        }

        if ('pages' === $currentTable) {
            return ['type' => self::TYPE_PAGE, 'table' => 'pages', 'uid' => $currentUid, 'identifier' => ''];
        }

        if (self::SHAPE_FILE === $this->shapeOf($currentTable)) {
            $folder = $this->provenanceRepository->fetchFileFolder($currentUid);
            if (null !== $folder) {
                return ['type' => self::TYPE_FOLDER, 'table' => '', 'uid' => 0, 'identifier' => $folder];
            }
        }

        if ($currentTable === $table && $currentUid === $uid) {
            return ['type' => self::TYPE_NONE, 'table' => '', 'uid' => 0, 'identifier' => ''];
        }

        return ['type' => self::TYPE_RECORD, 'table' => $currentTable, 'uid' => $currentUid, 'identifier' => ''];
    }

    protected function shapeOf(string $table): string
    {
        return $this->shapes[$table] ??= $this->detectShape($table);
    }

    protected function detectShape(string $table): string
    {
        if (!$this->tcaCompatibilityService->hasTable($table)) {
            return self::SHAPE_STANDALONE;
        }

        // Order matters: reference and file both carry a pid too, so they must be matched before SHAPE_ON_PAGE.
        if ($this->hasFields($table, ['tablenames', 'uid_foreign'])) {
            return self::SHAPE_RELATION;
        }

        if ($this->hasFields($table, ['storage', 'identifier'])) {
            return self::SHAPE_FILE;
        }

        if (null !== $this->fileFieldOf($table)) {
            return self::SHAPE_FILE_METADATA;
        }

        if (null !== $this->irreParentOf($table)) {
            return self::SHAPE_CHILD;
        }

        return self::SHAPE_ON_PAGE;
    }

    protected function fileFieldOf(string $table): ?string
    {
        foreach ($this->tcaCompatibilityService->getColumnConfigs($table) as $field => $config) {
            if ('sys_file' === ($config['foreign_table'] ?? '') && empty($config['foreign_field'])) {
                return (string) $field;
            }
        }

        return null;
    }

    /**
     * @return null|array{table: string, field: string}
     */
    protected function irreParentOf(string $table): ?array
    {
        if (array_key_exists($table, $this->irreParents)) {
            return $this->irreParents[$table];
        }

        $this->irreParents[$table] = null;

        foreach ($this->tcaCompatibilityService->getAllTableNames() as $candidate) {
            foreach ($this->tcaCompatibilityService->getColumnConfigs($candidate) as $config) {
                if ($table === ($config['foreign_table'] ?? '') && '' !== (string) ($config['foreign_field'] ?? '')) {
                    $this->irreParents[$table] = ['table' => $candidate, 'field' => (string) $config['foreign_field']];

                    return $this->irreParents[$table];
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $fields
     */
    protected function hasFields(string $table, array $fields): bool
    {
        foreach ($fields as $field) {
            if (!$this->tcaCompatibilityService->hasField($table, $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{table: string, uid: int}>
     */
    protected function recordsOf(array $rows): array
    {
        $records = [];
        foreach ($rows as $row) {
            $records[] = ['table' => (string) $row['tablename'], 'uid' => (int) $row['record_uid']];
        }

        return $records;
    }

    protected function keyOf(string $table, int $uid): string
    {
        return $table.':'.$uid;
    }
}
