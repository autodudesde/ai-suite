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

namespace AutoDudes\AiSuite\Domain\Model\Dto;

use TYPO3\CMS\Core\Resource\File;

class FileMetadata
{
    /**
     * @param array<string, mixed> $sourceMetadata
     */
    public function __construct(
        protected string $uid = '0',
        protected string $identifier = '',
        protected string $title = '',
        protected string $name = '',
        protected string $description = '',
        protected string $alternative = '',
        protected string $copyright = '',
        protected string $publicUrl = '',
        protected bool $userCanRead = false,
        protected bool $userCanWrite = false,
        protected bool $userCanDelete = false,
        protected int $size = 0,
        protected int $fileUid = 0,
        protected string $mode = '',
        protected array $sourceMetadata = [],
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public static function createFromFileObject(File $file, array $metadata = []): self
    {
        $meta = count($metadata) > 0 ? $metadata : $file->getMetaData();

        return new self(
            uid: (string) $meta['uid'],
            identifier: $file->getIdentifier(),
            title: $meta['title'] ?? '',
            name: $file->getName(),
            description: $meta['description'] ?? '',
            alternative: $meta['alternative'] ?? '',
            copyright: $meta['copyright'] ?? '',
            publicUrl: $file->getPublicUrl() ?? '',
            userCanRead: $file->checkActionPermission('read'),
            userCanWrite: $file->checkActionPermission('write'),
            userCanDelete: $file->checkActionPermission('delete'),
            size: $file->getSize(),
            fileUid: $meta['file'] ?? 0,
            mode: $meta['mode'] ?? '',
            sourceMetadata: $meta['sourceMetadata'] ?? [],
        );
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAlternative(): string
    {
        return $this->alternative;
    }

    public function getCopyright(): string
    {
        return $this->copyright;
    }

    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    public function getUserCanRead(): bool
    {
        return $this->userCanRead;
    }

    public function getUserCanWrite(): bool
    {
        return $this->userCanWrite;
    }

    public function getUserCanDelete(): bool
    {
        return $this->userCanDelete;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getFileUid(): int
    {
        return $this->fileUid;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSourceMetadata(): array
    {
        return $this->sourceMetadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'identifier' => $this->identifier,
            'title' => $this->title,
            'name' => $this->name,
            'description' => $this->description,
            'alternative' => $this->alternative,
            'publicUrl' => $this->publicUrl,
            'userCanRead' => $this->userCanRead,
            'userCanWrite' => $this->userCanWrite,
            'userCanDelete' => $this->userCanDelete,
            'size' => $this->size,
            'fileUid' => $this->fileUid,
            'mode' => $this->mode,
            'sourceMetadata' => $this->sourceMetadata,
        ];
    }
}
