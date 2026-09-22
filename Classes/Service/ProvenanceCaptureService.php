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

use AutoDudes\AiSuite\Domain\Model\Dto\ProvenanceContext;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * A request-scoped window in which every DataHandler write is attributed to an AI feature.
 */
class ProvenanceCaptureService implements SingletonInterface
{
    /** @var list<ProvenanceContext> */
    private array $stack = [];

    /** @var list<array{table: string, uid: int, action: string, fields: list<string>, context: ProvenanceContext}> */
    private array $captured = [];

    public function __construct(
        protected readonly ProvenanceService $provenanceService,
    ) {}

    public function begin(ProvenanceContext $context): void
    {
        $outer = $this->current();
        if ('' === $context->model && null !== $outer && '' !== $outer->model) {
            $context = new ProvenanceContext($context->mode, $context->feature, $outer->model, $context->client);
        }

        $this->stack[] = $context;
    }

    public function isActive(): bool
    {
        return [] !== $this->stack;
    }

    public function current(): ?ProvenanceContext
    {
        return [] === $this->stack ? null : $this->stack[count($this->stack) - 1];
    }

    public function refineModel(string $model): void
    {
        $depth = count($this->stack);
        if ('' === $model || 0 === $depth) {
            return;
        }

        $context = $this->stack[$depth - 1];
        if ('' !== $context->model) {
            return;
        }

        $this->stack[$depth - 1] = new ProvenanceContext($context->mode, $context->feature, $model, $context->client);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function capture(string $table, int $uid, string $action, array $fields = []): void
    {
        $context = $this->current();
        if (null === $context || '' === $table || $uid <= 0) {
            return;
        }

        foreach ($this->captured as $index => $existing) {
            if ($existing['table'] === $table && $existing['uid'] === $uid) {
                $merged = array_values(array_unique(array_merge(
                    $existing['fields'],
                    self::writtenFieldNames($fields),
                )));
                sort($merged);
                $this->captured[$index]['fields'] = $merged;

                return;
            }
        }

        $this->captured[] = [
            'table' => $table,
            'uid' => $uid,
            'action' => $action,
            'fields' => self::writtenFieldNames($fields),
            'context' => $context,
        ];
    }

    /**
     * @return list<array{table: string, uid: int, action: string, fields: list<string>, context: ProvenanceContext}>
     */
    public function flush(): array
    {
        $captured = $this->captured;
        $this->captured = [];

        return $captured;
    }

    public function end(): void
    {
        array_pop($this->stack);

        if ([] !== $this->stack) {
            return;
        }

        foreach ($this->flush() as $entry) {
            $this->provenanceService->record(
                $entry['context'],
                $entry['table'],
                $entry['uid'],
                $entry['fields'],
            );
        }
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return list<string>
     */
    private static function writtenFieldNames(array $fields): array
    {
        $names = array_keys(array_filter(
            $fields,
            static fn ($value): bool => is_scalar($value) && '' !== (string) $value,
        ));
        sort($names);

        return array_values(array_map(strval(...), $names));
    }
}
