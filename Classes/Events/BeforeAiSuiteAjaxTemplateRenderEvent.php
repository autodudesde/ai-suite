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

namespace AutoDudes\AiSuite\Events;

use Psr\Http\Message\ServerRequestInterface;

final class BeforeAiSuiteAjaxTemplateRenderEvent
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private array $params
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function addParam(string $key, mixed $value): void
    {
        $this->params[$key] = $value;
    }
}
