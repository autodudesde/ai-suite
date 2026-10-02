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
            // getSize() is nullable up to TYPO3 v12
            size: (int) $file->getSize(),
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
}
