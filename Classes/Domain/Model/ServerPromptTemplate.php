<?php

/***
 *
 * This file is part of the "ai_suite_server" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class ServerPromptTemplate extends AbstractEntity
{
    protected string $name = '';
    protected string $prompt = '';
    protected string $endpoint = '';
    protected string $type = '';

    public static function fromArray(array $data): self
    {
        $prompttemplate = new self();
        $prompttemplate->setName($data['name']);
        $prompttemplate->setPrompt($data['prompt']);
        $prompttemplate->setEndpoint($data['endpoint']);
        $prompttemplate->setType($data['type']);
        return $prompttemplate;
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

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;
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
