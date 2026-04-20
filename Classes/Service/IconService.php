<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\SingletonInterface;

class IconService implements SingletonInterface
{
    public function __construct(
        protected readonly IconFactory $iconFactory,
        private readonly Typo3Version $typo3Version,
    ) {}

    public function getIcon(string $identifier, string $size = 'small', ?string $overlayIdentifier = null): Icon
    {
        if ($this->typo3Version->getMajorVersion() >= 13) {
            return $this->iconFactory->getIcon($identifier, IconSize::from($size), $overlayIdentifier);
        }

        return $this->iconFactory->getIcon($identifier, $size, $overlayIdentifier);
    }
}
