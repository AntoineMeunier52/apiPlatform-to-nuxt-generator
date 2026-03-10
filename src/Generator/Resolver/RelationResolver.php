<?php

declare(strict_types=1);

namespace App\Generator\Resolver;

use App\Generator\Config\NamingConfiguration;
use App\Generator\DTO\Normalized\NormalizedRelation;
use App\Generator\Naming\NamingStrategy;

/**
 * Resolves TypeScript types for relations.
 *
 * Determines whether a relation should be:
 * - An IRI string (e.g., "/api/programs/123")
 * - An inlined object (e.g., { id: number, name: string })
 * - A union type (e.g., string | Program)
 *
 * This decision is based on serialization groups and context.
 */
readonly class RelationResolver
{
    public function __construct(
        private NamingStrategy $namingStrategy,
        private NamingConfiguration $config,
    ) {}

    /**
     * Resolves the TypeScript type for a relation.
     *
     * @param string[] $contextGroups Active serialization groups
     */
    public function resolveRelationType(NormalizedRelation $relation, array $contextGroups): string
    {
        // If the relation should be inlined, use appropriate type name
        if ($relation->isSerializedAsObject) {
            $type = $this->resolveInlinedTypeName($relation->targetClassName);
        } else {
            // Otherwise, return IRI string
            $type = 'string';
        }

        // Handle collections
        if ($relation->isCollection) {
            return $type . '[]';
        }

        return $type;
    }

    /**
     * Resolves relation type with mixed IRI/object support.
     *
     * This generates union types like: string | Program
     * Useful when the API can return either an IRI or a full object.
     *
     * @param string[] $contextGroups
     */
    public function resolveMixedRelationType(NormalizedRelation $relation, array $contextGroups): string
    {
        $targetTypeName = $this->resolveInlinedTypeName($relation->targetClassName);

        // Generate union type: IRI | inlined object
        $type = "string | {$targetTypeName}";

        // Handle collections
        if ($relation->isCollection) {
            return "({$type})[]";
        }

        return $type;
    }

    /**
     * Checks if a relation should use mixed type (IRI | object).
     *
     * This is useful for optional embedding scenarios where the API
     * might return either format depending on query parameters.
     */
    public function shouldUseMixedType(NormalizedRelation $relation): bool
    {
        // For now, we use strict types (either IRI or inlined, not both)
        // This can be extended later based on API Platform configuration
        return false;
    }

    /**
     * Returns the TypeScript type name for an inlined relation target.
     *
     * - Entity classes (App\Entity\*): uses the generated detail type name (e.g. GoalDetail)
     * - DTO/value-object classes (App\Dto\*): uses the bare class short name (e.g. MacroItemOutput)
     */
    private function resolveInlinedTypeName(string $targetClassName): string
    {
        $shortName = $this->namingStrategy->getTypeNameForClass($targetClassName);

        // Entity relations are serialized via generated types with the configured suffix
        if (str_starts_with($targetClassName, 'App\\Entity\\')) {
            return $shortName . $this->config->getTypeSuffix('item_output');
        }

        // DTO/value-object classes use their exact class name as the TypeScript type
        return $shortName;
    }

    /**
     * Resolves IRI-only type for a relation.
     *
     * Always returns string (or string[] for collections).
     */
    public function resolveIriType(NormalizedRelation $relation): string
    {
        if ($relation->isCollection) {
            return 'string[]';
        }

        return 'string';
    }

    /**
     * Resolves inlined object type for a relation.
     *
     * Always returns the target type name (or array for collections).
     */
    public function resolveInlinedType(NormalizedRelation $relation): string
    {
        $targetTypeName = $this->resolveInlinedTypeName($relation->targetClassName);

        if ($relation->isCollection) {
            return $targetTypeName . '[]';
        }

        return $targetTypeName;
    }
}
