<?php

namespace AutoDudes\AiSuite\Domain\Model\Dto;

use TYPO3\CMS\Core\Database\Connection;

class BackgroundTask
{
    public function __construct(
        protected string $scope,
        protected string $type,
        protected string $parentUuid,
        protected string $uuid,
        protected string $column,
        protected string $tableName,
        protected string $idColumn,
        protected int $tableUid,
        protected int $sysLanguageUid,
        protected string $status = 'pending',
        protected string $slug = '',
        protected int $crdate = 0 // time() is not allowed here
    ) {
        if ($this->crdate === 0) {
            $this->crdate = time();
        }
    }

    public static function getDbColumnsForBulkInsert(): array
    {
        return [
            'scope',
            'type',
            'parent_uuid',
            'uuid',
            'column',
            'slug',
            'status',
            'crdate',
            'table_name',
            'id_column',
            'table_uid',
            'sys_language_uid',
        ];
    }

    public static function getTypesForBulkInsert(): array
    {
        return [
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_STR,
            Connection::PARAM_INT,
        ];
    }

    public function getBulkInsertPayload(): array
    {
        return [
            $this->scope,
            $this->type,
            $this->parentUuid,
            $this->uuid,
            $this->column,
            $this->slug,
            $this->status,
            $this->crdate,
            $this->tableName,
            $this->idColumn,
            $this->tableUid,
            $this->sysLanguageUid,
        ];
    }
}
