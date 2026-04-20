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

namespace AutoDudes\AiSuite\Events;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;

final class AfterButtonBarGeneratedEvent
{
    public function __construct(
        private readonly ButtonBar $buttonBar,
        private readonly ServerRequestInterface $request,
    ) {}

    public function getButtonBar(): ButtonBar
    {
        return $this->buttonBar;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
