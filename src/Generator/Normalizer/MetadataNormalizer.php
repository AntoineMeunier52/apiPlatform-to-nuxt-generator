<?php

declare(strict_types=1);

namespace App\Generator\Normalizer;

use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\Extractor\ApiPlatformMetadataExtractor;
use App\Generator\Extractor\IdentifierMetadataExtractor;

/**
 * Main orchestrator for metadata normalization.
 *
 * This service coordinates the extraction and normalization of all API Platform
 * resources into NormalizedResource DTOs, which serve as the intermediate model
 * between extraction and generation.
 */
readonly class MetadataNormalizer
{
    public function __construct(
        private ApiPlatformMetadataExtractor $metadataExtractor,
        private OperationNormalizer $operationNormalizer,
        private PropertyNormalizer $propertyNormalizer,
        private FilterNormalizer $filterNormalizer,
        private IdentifierMetadataExtractor $identifierExtractor,
    ) {}

    /**
     * Normalizes all API Platform resources.
     *
     * @return NormalizedResource[]
     */
    public function normalizeAllResources(): array
    {
        $resourceClasses = $this->metadataExtractor->getResourceClasses();
        $resources = [];

        foreach ($resourceClasses as $resourceClass) {
            // Skip built-in API Platform resources (Error, ValidationException, etc.)
            // They belong to the ApiPlatform namespace and are not user-facing REST resources.
            if (str_starts_with($resourceClass, 'ApiPlatform\\')) {
                continue;
            }

            $resource = $this->normalizeResource($resourceClass);

            if ($resource !== null) {
                $resources[] = $resource;
            }
        }

        return $resources;
    }

    /**
     * Normalizes a single resource.
     */
    public function normalizeResource(string $resourceClass): ?NormalizedResource
    {
        try {
            $metadata = $this->metadataExtractor->extractResourceMetadata($resourceClass);

            if ($metadata === null) {
                return null;
            }

            $operations = $this->metadataExtractor->extractOperations($resourceClass);
            $normalizedOperations = [];

            foreach ($operations as $operation) {
                try {
                    $normalizedOperations[] = $this->operationNormalizer->normalizeOperation(
                        $operation,
                        $resourceClass
                    );
                } catch (\Throwable $e) {
                    // Skip invalid operations silently
                    continue;
                }
            }

            if (empty($normalizedOperations)) {
                return null;
            }

            // Extract identifiers
            $identifiers = $this->identifierExtractor->extract($resourceClass);

            // Extract base properties (from the resource class itself)
            $properties = $this->propertyNormalizer->normalizeProperties(
                $resourceClass,
                normalizationContext: [],
                denormalizationContext: [],
                isOutput: true,
            );

            // Extract short name and plural name
            $shortName = $this->extractShortName($resourceClass);
            $pluralName = $shortName . 's'; // Simple pluralization

            // Extract base path from first operation or fallback
            $basePath = '/api/' . strtolower($pluralName);
            if (!empty($normalizedOperations)) {
                // Use path from first operation
                $firstOp = $normalizedOperations[0];
                if ($firstOp->uriTemplate) {
                    // Extract base path (remove {id} placeholders)
                    $basePath = preg_replace('/\/\{[^}]+\}$/', '', $firstOp->uriTemplate) ?? $basePath;
                }
            }

            // Build groups to properties mapping
            $groupsToProperties = [];
            foreach ($properties as $property) {
                foreach ($property->groups as $group) {
                    if (!isset($groupsToProperties[$group])) {
                        $groupsToProperties[$group] = [];
                    }
                    $groupsToProperties[$group][] = $property->name;
                }
            }

            return new NormalizedResource(
                className: $resourceClass,
                shortName: $shortName,
                pluralName: $pluralName,
                basePath: $basePath,
                operations: $normalizedOperations,
                properties: $properties,
                identifiers: $identifiers,
                groupsToProperties: $groupsToProperties,
            );
        } catch (\Throwable $e) {
            // Log error for debugging
            error_log("MetadataNormalizer error for $resourceClass: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Extracts short name from class name.
     * Example: App\Entity\Program -> Program
     */
    private function extractShortName(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }
}
