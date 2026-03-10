<?php

declare(strict_types=1);

namespace App\Generator\Normalizer;

use Symfony\Component\PropertyInfo\Type;

/**
 * Analyzes serialization context (normalization/denormalization groups)
 * to determine which properties are actually exposed for a given operation.
 *
 * This is crucial for generating accurate TypeScript types that reflect
 * what the API actually returns/accepts per operation.
 */
readonly class SerializationContextAnalyzer
{
    /**
     * Extracts groups from normalization context.
     *
     * @param array<string, mixed> $context
     * @return string[]
     */
    public function extractNormalizationGroups(array $context): array
    {
        return $this->extractGroups($context, 'groups');
    }

    /**
     * Extracts groups from denormalization context.
     *
     * @param array<string, mixed> $context
     * @return string[]
     */
    public function extractDenormalizationGroups(array $context): array
    {
        return $this->extractGroups($context, 'groups');
    }

    /**
     * Determines if a property is exposed in the given groups.
     *
     * @param string[] $propertyGroups Groups declared on the property
     * @param string[] $contextGroups Groups active in the serialization context
     */
    public function isPropertyExposed(array $propertyGroups, array $contextGroups): bool
    {
        if (empty($contextGroups)) {
            // No groups specified = all properties exposed (Symfony default behavior)
            return true;
        }

        if (empty($propertyGroups)) {
            // Property has no groups but context requires groups = not exposed
            return false;
        }

        // Property is exposed if it has at least one group in common with context
        return !empty(array_intersect($propertyGroups, $contextGroups));
    }

    /**
     * Analyzes if a property is readable in the given normalization context.
     *
     * @param string[] $propertyGroups
     * @param array<string, mixed> $normalizationContext
     */
    public function isPropertyReadable(array $propertyGroups, array $normalizationContext): bool
    {
        $contextGroups = $this->extractNormalizationGroups($normalizationContext);
        return $this->isPropertyExposed($propertyGroups, $contextGroups);
    }

    /**
     * Analyzes if a property is writable in the given denormalization context.
     *
     * @param string[] $propertyGroups
     * @param array<string, mixed> $denormalizationContext
     */
    public function isPropertyWritable(array $propertyGroups, array $denormalizationContext): bool
    {
        $contextGroups = $this->extractDenormalizationGroups($denormalizationContext);
        return $this->isPropertyExposed($propertyGroups, $contextGroups);
    }

    /**
     * Determines the relation depth for a property based on context.
     *
     * @param array<string, mixed> $context
     */
    public function getMaxDepth(array $context, string $propertyName): ?int
    {
        // Check for enable_max_depth
        if (!($context['enable_max_depth'] ?? false)) {
            return null;
        }

        // Check for specific max_depth attributes (requires Symfony Serializer metadata)
        // This is a simplified version - real implementation would need to check
        // the actual MaxDepth attribute on the property
        return null;
    }

    /**
     * Checks if circular reference handling is enabled.
     *
     * @param array<string, mixed> $context
     */
    public function hasCircularReferenceHandler(array $context): bool
    {
        return isset($context['circular_reference_handler'])
            || isset($context['circular_reference_limit']);
    }

    /**
     * Determines the effective relation type based on serialization groups.
     *
     * This helps decide if a relation should be:
     * - Inlined (full object)
     * - IRI only (string reference)
     * - Mixed (IRI | object)
     *
     * @param string[] $relationGroups Groups on the related entity
     * @param string[] $contextGroups Groups in the current context
     */
    public function shouldInlineRelation(array $relationGroups, array $contextGroups): bool
    {
        // If the relation has groups that match the context, it should be inlined
        return $this->isPropertyExposed($relationGroups, $contextGroups);
    }

    /**
     * Extracts skip_null_values setting from context.
     *
     * @param array<string, mixed> $context
     */
    public function shouldSkipNullValues(array $context): bool
    {
        return $context['skip_null_values'] ?? false;
    }

    /**
     * Generic group extraction from context.
     *
     * @param array<string, mixed> $context
     * @return string[]
     */
    private function extractGroups(array $context, string $key): array
    {
        if (!isset($context[$key])) {
            return [];
        }

        $groups = $context[$key];

        if (is_string($groups)) {
            return [$groups];
        }

        if (is_array($groups)) {
            return array_values(array_filter($groups, 'is_string'));
        }

        return [];
    }

    /**
     * Merges multiple contexts (useful for inheritance or composed operations).
     *
     * @param array<array<string, mixed>> $contexts
     * @return array<string, mixed>
     */
    public function mergeContexts(array $contexts): array
    {
        $merged = [];

        foreach ($contexts as $context) {
            foreach ($context as $key => $value) {
                if ($key === 'groups' && isset($merged['groups'])) {
                    // Merge groups arrays
                    $merged['groups'] = array_unique(array_merge(
                        (array) $merged['groups'],
                        (array) $value
                    ));
                } else {
                    $merged[$key] = $value;
                }
            }
        }

        return $merged;
    }

    /**
     * Normalizes context to a consistent format.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function normalizeContext(array $context): array
    {
        $normalized = $context;

        // Ensure groups is always an array
        if (isset($normalized['groups']) && is_string($normalized['groups'])) {
            $normalized['groups'] = [$normalized['groups']];
        }

        return $normalized;
    }
}
