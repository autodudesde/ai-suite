<?php

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

class PageContent
{
    protected array $contentElementData;
    protected array $availableTcaColumns = [];
    protected array $selectedTcaColumns = [];
    protected string $returnUrl;
    protected string $regenerateReturnUrl;
    protected int $pid;
    protected int $uidPid;
    protected string $cType;
    protected int $colPos;
    protected int $sysLanguageUid;
    protected string $initialPrompt;
    protected string $uid;
    protected string $additionalImageSettings;

    public function __construct(
        array $contentElementData,
        array $availableTcaColumns,
        array $selectedTcaColumns,
        string $returnUrl,
        string $regenerateReturnUrl,
        int $pid,
        int $uidPid,
        string $cType,
        int $colPos,
        int $sysLanguageUid,
        string $initialPrompt,
        string $uid,
        string $additionalImageSettings
    ) {
        $this->contentElementData = $contentElementData;
        $this->availableTcaColumns = $availableTcaColumns;
        $this->selectedTcaColumns = $selectedTcaColumns;
        $this->returnUrl = $returnUrl;
        $this->regenerateReturnUrl = $regenerateReturnUrl;
        $this->uidPid = $uidPid;
        $this->pid = $pid;
        $this->cType = $cType;
        $this->colPos = $colPos;
        $this->sysLanguageUid = $sysLanguageUid;
        $this->initialPrompt = $initialPrompt;
        $this->uid = $uid;
        $this->additionalImageSettings = $additionalImageSettings;
    }

    public static function createEmpty(): self
    {
        return new self(
            [],
            [],
            [],
            '',
            '',
            0,
            0,
            'text',
            0,
            0,
            '',
            0,
            ''
        );
    }

    public function getContentElementData(): array
    {
        return $this->contentElementData;
    }

    public function setContentElementData(array $contentElementData): self
    {
        $this->contentElementData = $contentElementData;
        return $this;
    }

    public function getAvailableTcaColumns(): array
    {
        return $this->availableTcaColumns;
    }

    public function setAvailableTcaColumns(array $availableTcaColumns): self
    {
        $this->availableTcaColumns = $availableTcaColumns;
        return $this;
    }

    public function getSelectedTcaColumns(): array
    {
        return $this->selectedTcaColumns;
    }

    public function setSelectedTcaColumns(array $selectedTcaColumns): self
    {
        $this->selectedTcaColumns = $selectedTcaColumns;
        return $this;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function setReturnUrl(string $returnUrl): self
    {
        $this->returnUrl = $returnUrl;
        return $this;
    }

    public function getRegenerateReturnUrl(): string
    {
        return $this->regenerateReturnUrl;
    }

    public function setRegenerateReturnUrl(string $regenerateReturnUrl): self
    {
        $this->regenerateReturnUrl = $regenerateReturnUrl;
        return $this;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): self
    {
        $this->pid = $pid;
        return $this;
    }

    public function getUidPid(): int
    {
        return $this->uidPid;
    }

    public function setUidPid(int $uidPid): self
    {
        $this->uidPid = $uidPid;
        return $this;
    }

    public function getCType(): string
    {
        return $this->cType;
    }

    public function setCType(string $cType): self
    {
        $this->cType = $cType;
        return $this;
    }

    public function getColPos(): int
    {
        return $this->colPos;
    }

    public function setColPos(int $colPos): self
    {
        $this->colPos = $colPos;
        return $this;
    }

    public function getSysLanguageUid(): int
    {
        return $this->sysLanguageUid;
    }

    public function setSysLanguageUid(int $sysLanguageUid): self
    {
        $this->sysLanguageUid = $sysLanguageUid;
        return $this;
    }

    public function getInitialPrompt(): string
    {
        return $this->initialPrompt;
    }

    public function setInitialPrompt(string $initialPrompt): self
    {
        $this->initialPrompt = $initialPrompt;
        return $this;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    public function getAdditionalImageSettings(): string
    {
        return $this->additionalImageSettings;
    }

    public function setAdditionalImageSettings(string $additionalImageSettings): self
    {
        $this->additionalImageSettings = $additionalImageSettings;
        return $this;
    }
}
