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

namespace AutoDudes\AiSuite\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PagePickerService implements SingletonInterface
{
    public const FIELD_REFERENCE = 'aiSuitePagePicker';

    public function __construct(
        private readonly UriBuilder $uriBuilder,
        private readonly LoggerInterface $logger,
    ) {}

    public function buildBrowserUrl(int $selectedPageId = 0, string $fieldReference = self::FIELD_REFERENCE): string
    {
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 14) {
            // kein bparams: es würde gewinnen und useEvents=false erzwingen
            $parameters = [
                'mode' => 'db',
                'fieldReference' => $fieldReference,
                'allowedTypes' => 'pages',
                'useEvents' => 1,
            ];
        } else {
            $parameters = ['mode' => 'db', 'bparams' => $fieldReference.'|||pages'];
        }
        if ($selectedPageId > 0) {
            $parameters['expandPage'] = $selectedPageId;
        }

        try {
            return (string) $this->uriBuilder->buildUriFromRoute('wizard_element_browser', $parameters);
        } catch (\Throwable $e) {
            $this->logger->error('Page element browser route is not available', ['error' => $e->getMessage()]);

            return '';
        }
    }
}
