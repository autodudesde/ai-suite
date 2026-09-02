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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

class SystemDomainResolver implements SingletonInterface
{
    private ?string $resolvedDomain = null;

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly SiteFinder $siteFinder,
    ) {}

    public function resolve(?string $explicitDomain = null): string
    {
        $explicit = $this->normalizeHost((string) $explicitDomain);
        if (null !== $explicit) {
            return $explicit;
        }

        if (null !== $this->resolvedDomain) {
            return $this->resolvedDomain;
        }

        $this->resolvedDomain = $this->fromConfiguration() ?? $this->fromRequest() ?? $this->fromSites() ?? '';

        return $this->resolvedDomain;
    }

    public function resolveBaseUrl(): string
    {
        $configured = $this->readConfiguredDomain();
        if ('' !== $configured) {
            $url = $this->toBaseUrl($configured);
            if (null !== $url) {
                return $url;
            }
        }

        foreach ($this->siteFinder->getAllSites() as $site) {
            $url = $this->toBaseUrl((string) $site->getBase());
            if (null !== $url) {
                return $url;
            }
        }

        $domain = $this->resolve();

        return '' === $domain ? '' : 'https://'.$domain;
    }

    private function fromConfiguration(): ?string
    {
        return $this->normalizeHost($this->readConfiguredDomain());
    }

    private function fromRequest(): ?string
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return null;
        }

        $normalizedParams = $request->getAttribute('normalizedParams');
        if (!$normalizedParams instanceof NormalizedParams) {
            return null;
        }

        return $this->normalizeHost($normalizedParams->getHttpHost());
    }

    private function fromSites(): ?string
    {
        foreach ($this->siteFinder->getAllSites() as $site) {
            $host = $this->normalizeHost((string) $site->getBase());
            if (null !== $host) {
                return $host;
            }
        }

        return null;
    }

    private function readConfiguredDomain(): string
    {
        try {
            $extConf = $this->extensionConfiguration->get('ai_suite');
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return '';
        }

        return is_array($extConf) ? trim((string) ($extConf['aiSuiteSystemDomain'] ?? '')) : '';
    }

    private function normalizeHost(string $candidate): ?string
    {
        $candidate = trim($candidate);
        if ('' === $candidate) {
            return null;
        }

        if (1 !== preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);
        if (!is_array($parts) || !isset($parts['host']) || '' === $parts['host']) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if (('https' === $scheme && 443 === $port) || ('http' === $scheme && 80 === $port)) {
            $port = null;
        }

        return null === $port ? $parts['host'] : $parts['host'].':'.$port;
    }

    private function toBaseUrl(string $candidate): ?string
    {
        $candidate = trim($candidate);
        if ('' === $candidate) {
            return null;
        }

        if (1 !== preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);
        if (!is_array($parts) || !isset($parts['host']) || '' === $parts['host']) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$parts['host'].$port;
    }
}
