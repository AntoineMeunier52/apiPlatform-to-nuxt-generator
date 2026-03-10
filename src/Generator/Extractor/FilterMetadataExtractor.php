<?php

declare(strict_types=1);

namespace App\Generator\Extractor;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\NumericFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\FilterInterface;
use ApiPlatform\Metadata\Operation;
use App\Generator\DTO\Normalized\FilterType;
use App\Generator\DTO\Normalized\NormalizedFilter;
use Psr\Container\ContainerInterface;

/**
 * Service d'extraction des filtres d'une opération
 */
readonly class FilterMetadataExtractor
{
    public function __construct(
        private ContainerInterface $filterLocator,
    ) {}

    /**
     * Extrait les filtres d'une opération
     *
     * @return NormalizedFilter[]
     */
    public function extract(Operation $operation): array
    {
        $normalizedFilters = [];
        $resourceClass = $operation->getClass();

        // Extract from legacy ApiFilter attributes
        $filters = $operation->getFilters() ?? [];
        foreach ($filters as $filterId) {
            if (!$this->filterLocator->has($filterId)) {
                continue;
            }

            $filter = $this->filterLocator->get($filterId);
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            $normalizedFilters = array_merge(
                $normalizedFilters,
                $this->extractFromFilter($filter, $filterId, $resourceClass)
            );
        }

        // Extract from QueryParameter (API Platform 4 recommended way)
        $parameters = $operation->getParameters() ?? [];
        $processedFilters = []; // Track filters to avoid duplicates

        foreach ($parameters as $paramKey => $parameter) {
            // Get filter ID from the parameter
            $filterId = $parameter->getFilter() ?? null;
            if ($filterId === null || isset($processedFilters[$filterId])) {
                continue;
            }

            if (!$this->filterLocator->has($filterId)) {
                continue;
            }

            $filter = $this->filterLocator->get($filterId);
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            $normalizedFilters = array_merge(
                $normalizedFilters,
                $this->extractFromFilter($filter, $filterId, $resourceClass)
            );

            $processedFilters[$filterId] = true;
        }

        return $normalizedFilters;
    }

    /**
     * Extrait les filtres normalisés depuis un filtre API Platform
     *
     * @return NormalizedFilter[]
     */
    private function extractFromFilter(FilterInterface $filter, string $filterId, string $resourceClass): array
    {
        $normalizedFilters = [];
        $filterClass = $filter::class;

        // Récupérer la description du filtre avec le nom de la classe de ressource
        $description = method_exists($filter, 'getDescription')
            ? $filter->getDescription($resourceClass)
            : [];

        foreach ($description as $paramName => $paramConfig) {
            $property = $paramConfig['property'] ?? $paramName;
            $strategy = $paramConfig['strategy'] ?? 'exact';
            $isArray = str_ends_with($paramName, '[]');

            $filterType = $this->determineFilterType($filterClass, $paramName);
            $tsType = $this->determineTsType($filterType, $paramConfig);
            $allowedValues = $this->determineAllowedValues($filterType, $paramConfig);

            $normalizedFilters[] = new NormalizedFilter(
                name: $isArray ? substr($paramName, 0, -2) : $paramName,
                filterClass: $filterClass,
                property: $property,
                strategy: $strategy,
                type: $filterType,
                tsType: $tsType,
                isArray: $isArray,
                allowedValues: $allowedValues,
            );
        }

        return $normalizedFilters;
    }

    /**
     * Détermine le type de filtre
     */
    private function determineFilterType(string $filterClass, string $paramName): FilterType
    {
        // Filtres de tri (order[xxx])
        if (str_starts_with($paramName, 'order[')) {
            return FilterType::ORDER;
        }

        // Filtres d'existence (exists[xxx])
        if (str_starts_with($paramName, 'exists[')) {
            return FilterType::EXISTS;
        }

        // Filtres de date (xxx[after], xxx[before])
        if (preg_match('/\[(after|before|strictly_after|strictly_before)\]$/', $paramName)) {
            return FilterType::DATE;
        }

        return match (true) {
            is_a($filterClass, SearchFilter::class, true) => FilterType::SEARCH,
            is_a($filterClass, BooleanFilter::class, true) => FilterType::BOOLEAN,
            is_a($filterClass, DateFilter::class, true) => FilterType::DATE,
            is_a($filterClass, RangeFilter::class, true) => FilterType::RANGE,
            is_a($filterClass, OrderFilter::class, true) => FilterType::ORDER,
            is_a($filterClass, ExistsFilter::class, true) => FilterType::EXISTS,
            is_a($filterClass, NumericFilter::class, true) => FilterType::NUMERIC,
            default => FilterType::SEARCH,
        };
    }

    /**
     * Détermine le type TypeScript pour un filtre
     *
     * @param array<string, mixed> $paramConfig
     */
    private function determineTsType(FilterType $filterType, array $paramConfig): string
    {
        // Type explicite dans la config
        if (isset($paramConfig['type'])) {
            return match ($paramConfig['type']) {
                'int', 'integer', 'float', 'double' => 'number',
                'bool', 'boolean' => 'boolean',
                default => 'string',
            };
        }

        return $filterType->getDefaultTsType();
    }

    /**
     * Détermine les valeurs autorisées pour un filtre
     *
     * @param array<string, mixed> $paramConfig
     *
     * @return string[]
     */
    private function determineAllowedValues(FilterType $filterType, array $paramConfig): array
    {
        if ($filterType === FilterType::ORDER) {
            return ['asc', 'desc'];
        }

        if (isset($paramConfig['enum'])) {
            return $paramConfig['enum'];
        }

        return [];
    }
}
