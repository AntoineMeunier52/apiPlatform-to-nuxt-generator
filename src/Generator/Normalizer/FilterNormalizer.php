<?php

declare(strict_types=1);

namespace App\Generator\Normalizer;

use ApiPlatform\Metadata\Operation;
use App\Generator\DTO\Normalized\FilterType;
use App\Generator\DTO\Normalized\NormalizedFilter;
use App\Generator\Extractor\FilterMetadataExtractor;

/**
 * Normalizes API Platform filters into NormalizedFilter DTOs.
 *
 * Transforms raw filter metadata into a consistent structure
 * that can be used to generate TypeScript query parameter types.
 */
readonly class FilterNormalizer
{
    public function __construct(
        private FilterMetadataExtractor $filterExtractor,
    ) {}

    /**
     * Normalizes all filters for an operation.
     *
     * @param Operation $operation The operation to extract filters from
     * @return NormalizedFilter[]
     */
    public function normalizeFilters(Operation $operation): array
    {
        // Use the FilterMetadataExtractor which already handles the extraction
        return $this->filterExtractor->extract($operation);
    }

    /**
     * Normalizes filter metadata into NormalizedFilter instances.
     *
     * @param array<string, mixed> $filterMetadata
     * @return NormalizedFilter[]
     */
    private function normalizeFilterMetadata(array $filterMetadata): array
    {
        $filterClass = $filterMetadata['class'] ?? null;
        $properties = $filterMetadata['properties'] ?? [];

        if ($filterClass === null || empty($properties)) {
            return [];
        }

        $filterType = $this->detectFilterType($filterClass);
        $filters = [];

        foreach ($properties as $property => $config) {
            $filters[] = $this->createNormalizedFilter(
                $property,
                $filterType,
                $config
            );
        }

        return $filters;
    }

    /**
     * Creates a NormalizedFilter instance for a single property.
     *
     * @param array<string, mixed>|string|null $config
     */
    private function createNormalizedFilter(
        string $property,
        FilterType $filterType,
        array|string|null $config,
    ): NormalizedFilter {
        // Normalize config to array
        if (is_string($config)) {
            $config = ['strategy' => $config];
        } elseif ($config === null) {
            $config = [];
        }

        return match ($filterType) {
            FilterType::SEARCH => $this->createSearchFilter($property, $config),
            FilterType::DATE => $this->createDateFilter($property, $config),
            FilterType::BOOLEAN => $this->createBooleanFilter($property, $config),
            FilterType::NUMERIC => $this->createNumericFilter($property, $config),
            FilterType::RANGE => $this->createRangeFilter($property, $config),
            FilterType::ORDER => $this->createOrderFilter($property, $config),
            FilterType::EXISTS => $this->createExistsFilter($property, $config),
        };
    }

    /**
     * Creates a search filter.
     *
     * @param array<string, mixed> $config
     */
    private function createSearchFilter(string $property, array $config): NormalizedFilter
    {
        $strategy = $config['strategy'] ?? 'partial';

        return new NormalizedFilter(
            property: $property,
            type: FilterType::SEARCH,
            strategy: $strategy,
            isArray: false,
            tsType: 'string',
            queryParams: [$property => 'string'],
        );
    }

    /**
     * Creates a date filter.
     *
     * @param array<string, mixed> $config
     */
    private function createDateFilter(string $property, array $config): NormalizedFilter
    {
        // Date filters support before/after/strictly_before/strictly_after
        return new NormalizedFilter(
            property: $property,
            type: FilterType::DATE,
            strategy: null,
            isArray: false,
            tsType: 'string',
            queryParams: [
                $property . '[before]' => 'string',
                $property . '[strictly_before]' => 'string',
                $property . '[after]' => 'string',
                $property . '[strictly_after]' => 'string',
            ],
        );
    }

    /**
     * Creates a boolean filter.
     *
     * @param array<string, mixed> $config
     */
    private function createBooleanFilter(string $property, array $config): NormalizedFilter
    {
        return new NormalizedFilter(
            property: $property,
            type: FilterType::BOOLEAN,
            strategy: null,
            isArray: false,
            tsType: 'boolean',
            queryParams: [$property => 'boolean'],
        );
    }

    /**
     * Creates a numeric filter.
     *
     * @param array<string, mixed> $config
     */
    private function createNumericFilter(string $property, array $config): NormalizedFilter
    {
        return new NormalizedFilter(
            property: $property,
            type: FilterType::NUMERIC,
            strategy: null,
            isArray: false,
            tsType: 'number',
            queryParams: [$property => 'number'],
        );
    }

    /**
     * Creates a range filter.
     *
     * @param array<string, mixed> $config
     */
    private function createRangeFilter(string $property, array $config): NormalizedFilter
    {
        // Range filters support gt/gte/lt/lte
        return new NormalizedFilter(
            property: $property,
            type: FilterType::RANGE,
            strategy: null,
            isArray: false,
            tsType: 'number',
            queryParams: [
                $property . '[gt]' => 'number',
                $property . '[gte]' => 'number',
                $property . '[lt]' => 'number',
                $property . '[lte]' => 'number',
            ],
        );
    }

    /**
     * Creates an order filter.
     *
     * @param array<string, mixed> $config
     */
    private function createOrderFilter(string $property, array $config): NormalizedFilter
    {
        return new NormalizedFilter(
            property: $property,
            type: FilterType::ORDER,
            strategy: null,
            isArray: false,
            tsType: "'asc' | 'desc'",
            queryParams: ['order[' . $property . ']' => "'asc' | 'desc'"],
        );
    }

    /**
     * Creates an exists filter.
     *
     * @param array<string, mixed> $config
     */
    private function createExistsFilter(string $property, array $config): NormalizedFilter
    {
        return new NormalizedFilter(
            property: $property,
            type: FilterType::EXISTS,
            strategy: null,
            isArray: false,
            tsType: 'boolean',
            queryParams: ['exists[' . $property . ']' => 'boolean'],
        );
    }

    /**
     * Detects filter type from filter class name.
     */
    private function detectFilterType(string $filterClass): FilterType
    {
        return match (true) {
            str_contains($filterClass, 'SearchFilter') => FilterType::SEARCH,
            str_contains($filterClass, 'DateFilter') => FilterType::DATE,
            str_contains($filterClass, 'BooleanFilter') => FilterType::BOOLEAN,
            str_contains($filterClass, 'NumericFilter') => FilterType::NUMERIC,
            str_contains($filterClass, 'RangeFilter') => FilterType::RANGE,
            str_contains($filterClass, 'OrderFilter') => FilterType::ORDER,
            str_contains($filterClass, 'ExistsFilter') => FilterType::EXISTS,
            default => FilterType::SEARCH, // Fallback
        };
    }
}
