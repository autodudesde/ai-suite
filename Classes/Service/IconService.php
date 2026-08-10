<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\SystemResource\Publishing\SystemResourcePublisherInterface;
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class IconService implements SingletonInterface
{
    public function __construct(
        protected readonly IconFactory $iconFactory,
        private readonly Typo3Version $typo3Version,
        private readonly IconRegistry $iconRegistry,
        private readonly LoggerInterface $logger,
    ) {}

    public function getIcon(string $identifier, string $size = 'small', ?string $overlayIdentifier = null): Icon
    {
        if ($this->typo3Version->getMajorVersion() >= 13) {
            return $this->iconFactory->getIcon($identifier, IconSize::from($size), $overlayIdentifier);
        }

        // @phpstan-ignore-next-line argument.type — v12 fallback, IconFactory::getIcon() accepted string $size before v13.
        return $this->iconFactory->getIcon($identifier, $size, $overlayIdentifier);
    }

    public function getPublicIconUrl(string $identifier, ?ServerRequestInterface $request = null): string
    {
        try {
            $configuration = $this->iconRegistry->getIconConfigurationByIdentifier($identifier);
        } catch (\Throwable $e) {
            $this->logger->warning('AI Suite: unknown icon identifier', ['identifier' => $identifier, 'exception' => $e]);

            return '';
        }

        $source = (string) ($configuration['options']['source'] ?? '');
        if (!str_starts_with($source, 'EXT:')) {
            return '';
        }

        try {
            $url = $this->typo3Version->getMajorVersion() >= 14
                ? $this->resolvePublicUrl($source, $request)
                : $this->resolvePublicUrlLegacy($source);

            return $this->makeRootRelative($url);
        } catch (\Throwable $e) {
            $this->logger->warning('AI Suite: could not resolve icon URL', [
                'identifier' => $identifier,
                'source' => $source,
                'exception' => $e,
            ]);

            return '';
        }
    }

    private function resolvePublicUrl(string $source, ?ServerRequestInterface $request): string
    {
        $factory = GeneralUtility::makeInstance(SystemResourceFactory::class);
        $publisher = GeneralUtility::makeInstance(SystemResourcePublisherInterface::class);

        return (string) $publisher->generateUri($factory->createPublicResource($source), $request);
    }

    private function resolvePublicUrlLegacy(string $source): string
    {
        return PathUtility::getPublicResourceWebPath($source);
    }

    private function makeRootRelative(string $url): string
    {
        if ('' === $url || str_starts_with($url, '/') || str_contains($url, '://')) {
            return $url;
        }

        return '/'.$url;
    }
}
