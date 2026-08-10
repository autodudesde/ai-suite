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

namespace AutoDudes\AiSuite\Command\Trait;

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

trait CliBackendBootstrapTrait
{
    public function initializeFakeRequest(): void
    {
        if (!isset($GLOBALS['TYPO3_REQUEST'])) {
            $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ;
        }
    }

    public function initializeBackendAuthentication(): void
    {
        if (isset($GLOBALS['BE_USER']) && !empty($GLOBALS['BE_USER']->user['uid'] ?? null)) {
            return;
        }
        Bootstrap::initializeBackendAuthentication();
        if (isset($GLOBALS['BE_USER'])) {
            $GLOBALS['BE_USER']->initializeUserSessionManager();
        }
    }
}
