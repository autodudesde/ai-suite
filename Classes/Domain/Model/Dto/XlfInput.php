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

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class XlfInput extends AbstractEntity
{
    protected string $extensionKey;
    protected string $filename;
    protected string $destinationLanguage;
    protected string $translationMode;
    protected array $translations = [];

    public function __construct(
        string $extensionKey,
        string $filename,
        string $destinationLanguage,
        string $translationMode
    ) {
        $this->extensionKey = $extensionKey;
        $this->filename = $filename;
        $this->destinationLanguage = $destinationLanguage;
        $this->translationMode = $translationMode;
    }

    public static function createEmpty(): self
    {
        return new self(
            '',
            '',
            '',
            ''
        );
    }

    public function getExtensionKey(): string
    {
        return $this->extensionKey;
    }

    public function setExtensionKey(string $extensionKey): self
    {
        $this->extensionKey = $extensionKey;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getDestinationLanguage(): string
    {
        return $this->destinationLanguage;
    }

    public function setDestinationLanguage(string $destinationLanguage): self
    {
        $this->destinationLanguage = $destinationLanguage;
        return $this;
    }

    public function getTranslationMode(): string
    {
        return $this->translationMode;
    }

    public function setTranslationMode($translationMode): self
    {
        $this->translationMode = $translationMode;
        return $this;
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function setTranslations(array $translations): self
    {
        $this->translations = $translations;
        return $this;
    }
}
