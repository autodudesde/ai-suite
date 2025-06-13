<?php

namespace AutoDudes\AiSuite\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Glossar extends AbstractEntity
{
    protected string $input = '';

    /**
     * @return string
     */
    public function getInput(): string
    {
        return $this->input;
    }

    public function setInput(string $input): void
    {
        $this->input = $input;
    }
}
