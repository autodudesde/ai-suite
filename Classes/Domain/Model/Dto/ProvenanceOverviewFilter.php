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

namespace AutoDudes\AiSuite\Domain\Model\Dto;

use AutoDudes\AiSuite\Service\ProvenanceStructureService;

final class ProvenanceOverviewFilter
{
    public const PER_PAGE = 50;

    /** @var list<string> */
    public const MODES = [
        ProvenanceContext::MODE_GENERATED,
        ProvenanceContext::MODE_ASSISTED,
        ProvenanceContext::MODE_TRANSLATED,
    ];

    /** @var list<string> */
    public const REVIEW_STATES = ['yes', 'no'];

    private function __construct(
        public readonly string $area,
        public readonly string $mode,
        public readonly string $feature,
        public readonly string $tablename,
        public readonly string $reviewed,
        public readonly int $page,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     * @param list<string>         $knownTables
     */
    public static function fromParameters(array $parameters, string $area = '', array $knownTables = []): self
    {
        $area = self::sanitiseArea('' !== $area ? $area : (string) ($parameters['area'] ?? ''));
        $values = (array) (((array) ($parameters['filters'] ?? []))[$area] ?? []);

        return new self(
            $area,
            self::oneOf((string) ($values['mode'] ?? ''), self::MODES),
            self::oneOf((string) ($values['feature'] ?? ''), ProvenanceContext::FEATURES),
            ProvenanceStructureService::AREA_RECORDS === $area
                ? self::oneOf((string) ($values['tablename'] ?? ''), $knownTables)
                : '',
            self::oneOf((string) ($values['reviewed'] ?? ''), self::REVIEW_STATES),
            max(1, (int) ($values['page'] ?? 1)),
        );
    }

    /**
     * @param array<string, mixed> $parameters
     * @param list<string>         $knownTables
     *
     * @return array<string, self>
     */
    public static function allFromParameters(array $parameters, array $knownTables = []): array
    {
        $filters = [];
        foreach (ProvenanceStructureService::AREAS as $area) {
            $filters[$area] = self::fromParameters($parameters, $area, $knownTables);
        }

        return $filters;
    }

    /**
     * @param array<string, self> $others
     *
     * @return array<string, mixed>
     */
    public function toUriParameters(array $others = []): array
    {
        $filters = [];
        foreach ($others as $area => $filter) {
            $filters[$area] = $filter->toQueryValues();
        }
        $filters[$this->area] = $this->toQueryValues();

        return ['area' => $this->area, 'filters' => array_filter($filters)];
    }

    /**
     * @param list<string> $includeTables
     * @param list<string> $excludeTables
     *
     * @return array<string, mixed>
     */
    public function toRepositoryFilters(array $includeTables, array $excludeTables = []): array
    {
        return [
            'mode' => $this->mode,
            'feature' => $this->feature,
            'tablename' => $this->tablename,
            'reviewed' => $this->reviewed,
            'includeTables' => $includeTables,
            'excludeTables' => $excludeTables,
        ];
    }

    public function withPage(int $page): self
    {
        return new self($this->area, $this->mode, $this->feature, $this->tablename, $this->reviewed, max(1, $page));
    }

    public function offset(): int
    {
        return ($this->page - 1) * self::PER_PAGE;
    }

    /**
     * @return array<string, string>
     */
    public function toQueryValues(): array
    {
        return array_filter([
            'mode' => $this->mode,
            'feature' => $this->feature,
            'tablename' => $this->tablename,
            'reviewed' => $this->reviewed,
            'page' => 1 === $this->page ? '' : (string) $this->page,
        ]);
    }

    private static function sanitiseArea(string $area): string
    {
        return in_array($area, ProvenanceStructureService::AREAS, true)
            ? $area
            : ProvenanceStructureService::AREA_PAGES;
    }

    /**
     * @param list<string> $allowed
     */
    private static function oneOf(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : '';
    }
}
