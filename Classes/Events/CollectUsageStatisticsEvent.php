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

namespace AutoDudes\AiSuite\Events;

use Psr\Http\Message\ServerRequestInterface;

final class CollectUsageStatisticsEvent
{
    /** @var list<array<string, mixed>> */
    private array $sections = [];

    public function __construct(
        private readonly string $apiKey,
        private readonly ServerRequestInterface $request,
    ) {}

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @param array<string, mixed> $section
     */
    public function addSection(array $section): void
    {
        $this->sections[] = $section;
    }

    /**
     * @param list<array<string, mixed>> $sections
     */
    public function addGroup(string $heading, array $sections): void
    {
        $first = true;
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            if ($first) {
                $section['heading'] = $heading;
                $first = false;
            }
            $this->sections[] = $section;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSections(): array
    {
        return $this->sections;
    }
}
