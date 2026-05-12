<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\SingletonInterface;

class BackendRouteService implements SingletonInterface
{
    public function __construct(
        private readonly Typo3Version $typo3Version,
    ) {}

    public function getRecordListPath(): string
    {
        return $this->typo3Version->getMajorVersion() >= 14
            ? '/module/content/records'
            : '/module/web/list';
    }

    public function getRecordListModuleIdentifier(): string
    {
        return $this->typo3Version->getMajorVersion() >= 14 ? 'records' : 'web_list';
    }
}
