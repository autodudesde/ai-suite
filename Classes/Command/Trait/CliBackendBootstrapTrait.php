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
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;

trait CliBackendBootstrapTrait
{
    public function initializeFakeRequest(?string $baseUrl = null): void
    {
        if (isset($GLOBALS['TYPO3_REQUEST'])) {
            return;
        }

        $parts = null === $baseUrl || '' === $baseUrl ? false : parse_url($baseUrl);
        if (!is_array($parts) || !isset($parts['host']) || '' === $parts['host']) {
            $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ;

            return;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        $serverParams = array_replace($_SERVER, [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $parts['host'],
            'HTTPS' => 'https' === $scheme ? 'on' : '',
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/',
        ]);

        $request = (new ServerRequest($scheme.'://'.$host.'/', 'GET', 'php://input', [], $serverParams))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
        ;

        $GLOBALS['TYPO3_REQUEST'] = $request->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest($request)
        );
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
