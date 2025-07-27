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
use TYPO3\CMS\Core\Page\PageRenderer;

final class AfterAiSuiteModuleInitalizeEvent
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly PageRenderer $pageRenderer
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getPageRenderer(): PageRenderer
    {
        return $this->pageRenderer;
    }
}