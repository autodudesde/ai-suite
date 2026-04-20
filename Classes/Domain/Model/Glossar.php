<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Glossar extends AbstractEntity
{
    protected string $input = '';

    public function getInput(): string
    {
        return $this->input;
    }

    public function setInput(string $input): void
    {
        $this->input = $input;
    }
}
