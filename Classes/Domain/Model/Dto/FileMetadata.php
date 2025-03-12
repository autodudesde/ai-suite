<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Domain\Model\Dto;

use TYPO3\CMS\Core\Resource\File;

class FileMetadata
{
    // uid, identifier, title, description, copyright, alternative, publicUrl, userCanRead, userCanWrite, userCanDelete
    protected int $uid;
    protected string $identifier;
    protected string $title;
    protected string $name;
    protected string $description;
    protected string $copyright;
    protected string $alternative;
    protected string $publicUrl;
    protected bool $userCanRead;
    protected bool $userCanWrite;
    protected bool $userCanDelete;

    public function __construct(
        int $uid = 0,
        string $identifier = '',
        string $title = '',
        string $name = '',
        string $description = '',
        string $alternative = '',
        string $copyright = '',
        string $publicUrl = '',
        bool $userCanRead = false,
        bool $userCanWrite = false,
        bool $userCanDelete = false
    )
    {
        $this->uid = $uid;
        $this->identifier = $identifier;
        $this->title = $title;
        $this->name = $name;
        $this->description = $description;
        $this->alternative = $alternative;
        $this->copyright = $copyright;
        $this->publicUrl = $publicUrl;
        $this->userCanRead = $userCanRead;
        $this->userCanWrite = $userCanWrite;
        $this->userCanDelete = $userCanDelete;
    }

    public static function createFromFileObject(File $file, array $metadata = []): self
    {
        $fileMeta = new self();
        $meta = count($metadata) > 0 ? $metadata : $file->getMetaData();
        $fileMeta->uid = $meta['uid'];
        $fileMeta->identifier = $file->getIdentifier();
        $fileMeta->title = ($meta['title'] ?? '');
        $fileMeta->name = $file->getName();
        $fileMeta->description = ($meta['description'] ?? '');
        $fileMeta->alternative = ($meta['alternative'] ?? '');
        $fileMeta->copyright = ($meta['copyright'] ?? '');
        $fileMeta->publicUrl = $file->getPublicUrl();
        $fileMeta->userCanRead = $file->checkActionPermission('read');
        $fileMeta->userCanWrite = $file->checkActionPermission('write');
        $fileMeta->userCanDelete = $file->checkActionPermission('delete');
        return $fileMeta;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;
        return $this;
    }
    public function getUid(): int
    {
        return $this->uid;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    public function getTitle(): string
    {
        return $this->title;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    public function getName(): string
    {
        return $this->name;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }
    public function getDescription(): string
    {
        return $this->description;
    }

    public function setAlternative(string $alternative): self
    {
        $this->alternative = $alternative;
        return $this;
    }
    public function getAlternative(): string
    {
        return $this->alternative;
    }

    public function setCopyright(string $copyright): self
    {
        $this->copyright = $copyright;
        return $this;
    }
    public function getCopyright(): string
    {
        return $this->copyright;
    }

    public function setPublicUrl(string $publicUrl): self
    {
        $this->publicUrl = $publicUrl;
        return $this;
    }
    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    public function setUserCanRead(bool $userCanRead): self
    {
        $this->userCanRead = $userCanRead;
        return $this;
    }
    public function getUserCanRead(): bool
    {
        return $this->userCanRead;
    }

    public function setUserCanWrite(bool $userCanWrite): self
    {
        $this->userCanWrite = $userCanWrite;
        return $this;
    }
    public function getUserCanWrite(): bool
    {
        return $this->userCanWrite;
    }

    public function setUserCanDelete(bool $userCanDelete): self
    {
        $this->userCanDelete = $userCanDelete;
        return $this;
    }
    public function getUserCanDelete(): bool
    {
        return $this->userCanDelete;
    }

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
        ];
    }
}
