<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class SecurityAdvisoryStore
{
    public const EXTENSIONS = ['ai_suite', 'ai_suite_mcp', 'cheddi', 'ai_suite_transparency'];

    private const REGISTRY_NAMESPACE = 'ai_suite';

    private const REGISTRY_KEY = 'securityAdvisories';

    private const REWRITE_AFTER_SECONDS = 3600;

    private const STALE_AFTER_SECONDS = 86400;

    public function __construct(
        private readonly Registry $registry,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function installedVersions(): array
    {
        $versions = [];
        foreach (self::EXTENSIONS as $extensionKey) {
            if (ExtensionManagementUtility::isLoaded($extensionKey)) {
                $versions[$extensionKey] = ExtensionManagementUtility::getExtensionVersion($extensionKey);
            }
        }

        return $versions;
    }

    /**
     * @param array<mixed> $body
     */
    public function recordAnswer(array $body, bool $isError): void
    {
        if (!array_key_exists('securityAdvisories', $body) && $isError) {
            return;
        }
        $advisories = is_array($body['securityAdvisories'] ?? null) ? self::sanitize($body['securityAdvisories']) : [];
        $state = $this->state();
        $now = time();
        if ($advisories === $state['advisories'] && $now - $state['checkedAt'] < self::REWRITE_AFTER_SECONDS) {
            return;
        }
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, [
            'advisories' => $advisories,
            'checkedAt' => $now,
            'attemptedAt' => $now,
        ]);
    }

    public function recordAttempt(): void
    {
        $state = $this->state();
        $state['attemptedAt'] = time();
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, $state);
    }

    public function isStale(): bool
    {
        return time() - $this->state()['attemptedAt'] >= self::STALE_AFTER_SECONDS;
    }

    /**
     * @return list<array{extension: string, installed: string, fixedIn: string, severity: string, advisory: string, url: string, blocking: bool}>
     */
    public function advisories(): array
    {
        $installed = self::installedVersions();

        return array_values(array_filter(
            $this->state()['advisories'],
            static fn (array $advisory): bool => ($installed[$advisory['extension']] ?? null) === $advisory['installed'],
        ));
    }

    public function checkedAt(): int
    {
        return $this->state()['checkedAt'];
    }

    /**
     * @return array{advisories: list<array{extension: string, installed: string, fixedIn: string, severity: string, advisory: string, url: string, blocking: bool}>, checkedAt: int, attemptedAt: int}
     */
    private function state(): array
    {
        $state = $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY);
        if (!is_array($state)) {
            return ['advisories' => [], 'checkedAt' => 0, 'attemptedAt' => 0];
        }

        return [
            'advisories' => is_array($state['advisories'] ?? null) ? self::sanitize($state['advisories']) : [],
            'checkedAt' => (int) ($state['checkedAt'] ?? 0),
            'attemptedAt' => (int) ($state['attemptedAt'] ?? 0),
        ];
    }

    /**
     * @param array<mixed> $advisories
     *
     * @return list<array{extension: string, installed: string, fixedIn: string, severity: string, advisory: string, url: string, blocking: bool}>
     */
    private static function sanitize(array $advisories): array
    {
        $clean = [];
        foreach ($advisories as $advisory) {
            if (!is_array($advisory)) {
                continue;
            }
            $text = static fn (string $field): string => is_string($advisory[$field] ?? null) ? mb_substr($advisory[$field], 0, 255) : '';
            $entry = [
                'extension' => $text('extension'),
                'installed' => $text('installed'),
                'fixedIn' => $text('fixedIn'),
                'severity' => $text('severity'),
                'advisory' => $text('advisory'),
                'url' => $text('url'),
                'blocking' => true === ($advisory['blocking'] ?? null),
            ];
            if (!in_array($entry['extension'], self::EXTENSIONS, true) || '' === $entry['fixedIn']) {
                continue;
            }
            if (!str_starts_with($entry['url'], 'https://')) {
                $entry['url'] = '';
            }
            $clean[] = $entry;
        }

        return $clean;
    }
}
