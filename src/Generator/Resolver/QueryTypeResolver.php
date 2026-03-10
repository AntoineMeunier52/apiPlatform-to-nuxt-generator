<?php

declare(strict_types=1);

namespace App\Generator\Resolver;

use App\Generator\DTO\Normalized\NormalizedOperation;
use App\Generator\DTO\Output\QueryTypeDefinition;
use App\Generator\DTO\Output\QueryProperty;
use App\Generator\Naming\NamingStrategy;
use App\Generator\Normalizer\FilterNormalizer;

/**
 * Resolves TypeScript query parameter types from API Platform filters.
 *
 * Generates TypeScript interfaces for query parameters like:
 *
 * interface ProgramQuery {
 *   name?: string;
 *   createdAt?: { before?: string; after?: string };
 *   page?: number;
 *   itemsPerPage?: number;
 * }
 */
readonly class QueryTypeResolver
{
    public function __construct(
        private FilterNormalizer $filterNormalizer,
        private NamingStrategy $namingStrategy,
    ) {}

    /**
     * Resolves the query type name for an operation.
     * Returns null if the operation has no query parameters.
     */
    public function resolveQueryType(NormalizedOperation $operation): ?string
    {
        if (!$this->hasQueryParams($operation)) {
            return null;
        }

        return $this->namingStrategy->generateQueryTypeName($operation->class);
    }

    /**
     * Resolves the full query type definition with all properties.
     */
    public function resolveQueryTypeDefinition(NormalizedOperation $operation): ?QueryTypeDefinition
    {
        if (!$this->hasQueryParams($operation)) {
            return null;
        }

        $properties = [];

        // Add filter properties (already normalized in NormalizedOperation)
        foreach ($operation->filters as $filter) {
            $properties[] = new QueryProperty(
                name: $filter->getQueryParamName(),
                tsType: $filter->getFullTsType(),
                optional: true,
                description: "Filter: {$filter->type->value} on {$filter->property}",
            );
        }

        // Add pagination properties if collection is paginated
        if ($operation->isPaginated) {
            $properties[] = new QueryProperty(
                name: 'page',
                tsType: 'number',
                optional: true,
                description: 'Page number',
            );

            $properties[] = new QueryProperty(
                name: 'itemsPerPage',
                tsType: 'number',
                optional: true,
                description: 'Items per page',
            );
        }

        $typeName = $this->namingStrategy->generateQueryTypeName($operation->class);

        return new QueryTypeDefinition(
            typeName: $typeName,
            properties: $properties,
        );
    }

    /**
     * Checks if an operation has query parameters.
     */
    private function hasQueryParams(NormalizedOperation $operation): bool
    {
        // Has filters
        if (!empty($operation->filters)) {
            return true;
        }

        // Has pagination
        if ($operation->isPaginated) {
            return true;
        }

        return false;
    }

    /**
     * Generates all unique query type definitions for a resource.
     *
     * Since multiple operations might share the same query type,
     * this method deduplicates them.
     *
     * @param NormalizedOperation[] $operations
     * @return QueryTypeDefinition[]
     */
    public function resolveUniqueQueryTypes(array $operations): array
    {
        $queryTypes = [];
        $seenTypeNames = [];

        foreach ($operations as $operation) {
            $queryType = $this->resolveQueryTypeDefinition($operation);

            if ($queryType === null) {
                continue;
            }

            // Deduplicate by name
            if (isset($seenTypeNames[$queryType->typeName])) {
                continue;
            }

            $queryTypes[] = $queryType;
            $seenTypeNames[$queryType->typeName] = true;
        }

        return $queryTypes;
    }
}
