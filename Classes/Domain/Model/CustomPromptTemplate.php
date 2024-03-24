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

namespace AutoDudes\AiSuite\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class CustomPromptTemplate extends AbstractEntity
{
    protected string $name = '';
    protected string $prompt = '';
    protected string $scope = '';
    protected string $type = '';

    public static function fromArray(array $data): self
    {
        $promptTemplate = new self();
        $promptTemplate->setName($data['name']);
        $promptTemplate->setPrompt($data['prompt']);
        $promptTemplate->setScope($data['scope']);
        $promptTemplate->setType($data['type']);
        return $promptTemplate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): self
    {
        $this->prompt = $prompt;
        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
}
