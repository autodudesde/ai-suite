<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\View\AuthenticationStyleInformation;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\SystemResource\Publishing\SystemResourcePublisherInterface;
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\CMS\Core\SystemResource\Type\PublicResourceInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class LoginStyleService
{
    public function __construct(
        private readonly AuthenticationStyleInformation $authStyleInfo,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly Typo3Version $typo3Version,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{logoUrl: string, faviconUrl: string, backgroundImageStyles: string}
     */
    public function resolve(?ServerRequestInterface $request): array
    {
        $backendConf = (array) $this->extensionConfiguration->get('backend');
        $faviconPath = (string) ($backendConf['backendFavicon'] ?? '');

        if ($this->typo3Version->getMajorVersion() >= 14) {
            return $this->resolveViaSystemResources($faviconPath, $request);
        }

        return [
            'logoUrl' => $this->resolveFileUri((string) ($backendConf['loginLogo'] ?? '')),
            'faviconUrl' => $this->resolveFileUri($faviconPath),
            // @phpstan-ignore-next-line arguments.count — v12/v13 fallback, the request parameter exists from v14 on.
            'backgroundImageStyles' => $this->authStyleInfo->getBackgroundImageStyles(),
        ];
    }

    /**
     * @return array{logoUrl: string, faviconUrl: string, backgroundImageStyles: string}
     */
    private function resolveViaSystemResources(string $faviconPath, ?ServerRequestInterface $request): array
    {
        $factory = GeneralUtility::makeInstance(SystemResourceFactory::class);
        $publisher = GeneralUtility::makeInstance(SystemResourcePublisherInterface::class);

        $logoUrl = '';
        $logoResource = $this->authStyleInfo->getLogo();
        if ($logoResource instanceof PublicResourceInterface) {
            try {
                $logoUrl = (string) $publisher->generateUri($logoResource, $request);
            } catch (\Throwable $e) {
                $this->logger->warning('AI Suite: could not resolve login logo URI', ['exception' => $e]);
            }
        }

        $faviconUrl = '';
        if ('' !== $faviconPath) {
            try {
                $faviconUrl = (string) $publisher->generateUri($factory->createPublicResource($faviconPath), $request);
            } catch (\Throwable $e) {
                $this->logger->warning('AI Suite: could not resolve favicon URI', ['exception' => $e, 'path' => $faviconPath]);
            }
        }

        return [
            'logoUrl' => $logoUrl,
            'faviconUrl' => $faviconUrl,
            'backgroundImageStyles' => null !== $request ? $this->authStyleInfo->getBackgroundImageStyles($request) : '',
        ];
    }

    private function resolveFileUri(string $filename): string
    {
        if ('' === $filename) {
            return '';
        }
        if (1 === preg_match('/^(https?:)?\/\//', $filename)) {
            return $filename;
        }
        $absoluteFilename = GeneralUtility::getFileAbsFileName(ltrim($filename, '/'));
        if ('' === $absoluteFilename || !is_file($absoluteFilename)) {
            return '';
        }

        return PathUtility::getAbsoluteWebPath($absoluteFilename);
    }
}
