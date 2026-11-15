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

final class AuditFindingActionsEvent
{
    /**
     * @var list<array{identifier: string, label: string, icon: string, payload: string}>
     */
    private array $actions = [];

    /**
     * @param array<string, mixed> $issue
     */
    public function __construct(
        private readonly int $pageId,
        private readonly string $auditLabel,
        private readonly array $issue,
        private readonly bool $hasOwnFix,
    ) {}

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function getAuditLabel(): string
    {
        return $this->auditLabel;
    }

    /**
     * @return array<string, mixed>
     */
    public function getIssue(): array
    {
        return $this->issue;
    }

    public function hasOwnFix(): bool
    {
        return $this->hasOwnFix;
    }

    public function addAction(string $identifier, string $label, string $icon, string $payload): void
    {
        $this->actions[] = ['identifier' => $identifier, 'label' => $label, 'icon' => $icon, 'payload' => $payload];
    }

    /**
     * @return list<array{identifier: string, label: string, icon: string, payload: string}>
     */
    public function getActions(): array
    {
        return $this->actions;
    }
}
