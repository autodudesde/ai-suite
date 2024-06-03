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

class PageStructureInput extends AbstractEntity
{
    protected array $aiResult = [];
    protected int $startStructureFromPid;
    protected string $plainPrompt;

    protected string $textGenerationLibraries;

    public function __construct(
        int $startStructureFromPid,
        string $plainPrompt,
        string $textGenerationLibraries
    ) {
        $this->startStructureFromPid = $startStructureFromPid;
        $this->plainPrompt = $plainPrompt;
        $this->textGenerationLibraries = $textGenerationLibraries;
    }

    public static function createEmpty(): self
    {
        return new self(
            0,
            '',
            '',
        );
    }

    public function getStartStructureFromPid(): int
    {
        return $this->startStructureFromPid;
    }

    public function getAiResult(): array
    {
        return $this->aiResult;
    }

    public function setAiResult(array $aiResult): self
    {
        $this->aiResult = $aiResult;
        return $this;
    }

    public function getPlainPrompt(): string
    {
        return $this->plainPrompt;
    }

    public function setPlainPrompt(string $plainPrompt): self
    {
        $this->plainPrompt = $plainPrompt;
        return $this;
    }

    public function getTextGenerationLibraries(): string
    {
        return $this->textGenerationLibraries;
    }

    public function setTextGenerationLibraries(string $textGenerationLibraries): void
    {
        $this->textGenerationLibraries = $textGenerationLibraries;
    }
}
