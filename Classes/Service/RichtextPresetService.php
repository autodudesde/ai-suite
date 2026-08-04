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
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\SingletonInterface;

class RichtextPresetService implements SingletonInterface
{
    /** @var array<string, list<string>> */
    private array $cache = [];

    public function __construct(
        private readonly Richtext $richtext,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Tags that survive RteHtmlParser when an editor saves the record. Markup outside this list is
     * dropped on the next backend save, so an agent writing it loses the content silently.
     *
     * @param array<string, mixed> $fieldConfig
     *
     * @return list<string>
     */
    public function getAllowedTags(string $table, string $field, string $recordType, array $fieldConfig): array
    {
        $cacheKey = $table.'.'.$field.'.'.$recordType;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            // pid 0: page TSconfig overrides are not reflected, the preset default is.
            $configuration = $this->richtext->getConfiguration($table, $field, 0, $recordType, $fieldConfig);
        } catch (\Throwable $e) {
            $this->logger->warning('RichtextPreset: could not resolve the RTE configuration', [
                'table' => $table,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);

            return $this->cache[$cacheKey] = [];
        }

        $allowed = $configuration['processing']['allowTags'] ?? null;
        if (!is_array($allowed)) {
            return $this->cache[$cacheKey] = [];
        }

        $tags = array_values(array_unique(array_filter(array_map(
            static fn ($tag): string => trim((string) $tag),
            $allowed,
        ))));
        sort($tags);

        return $this->cache[$cacheKey] = $tags;
    }
}
