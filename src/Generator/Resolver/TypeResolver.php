<?php

declare(strict_types=1);

namespace App\Generator\Resolver;

use App\Generator\DTO\Normalized\NormalizedProperty;
use App\Generator\DTO\Normalized\NormalizedOperation;
use App\Generator\DTO\Output\TypeProperty;
use App\Generator\DTO\Output\GeneratedType;
use App\Generator\Naming\NamingStrategy;
use App\Generator\Normalizer\PropertyNormalizer;
use App\Generator\Config\GeneratorConfiguration;

/**
 * Resolves TypeScript type definitions from normalized properties and operations.
 *
 * This service is responsible for:
 * - Converting normalized properties to TypeScript type properties
 * - Resolving property types (primitives, relations, arrays)
 * - Handling nullability and optional properties
 * - Generating input/output types for operations
 */
readonly class TypeResolver
{
    public function __construct(
        private TypeMapper $typeMapper,
        private RelationResolver $relationResolver,
        private PropertyNormalizer $propertyNormalizer,
        private NamingStrategy $namingStrategy,
        private GeneratorConfiguration $config,
    ) {}

    /**
     * Resolves output type (response type) for an operation.
     *
     * When an explicit output DTO class is used (different from entity class), normalize its
     * properties without entity-level group filtering - the DTO either has its own groups or none.
     */
    public function resolveOutputType(NormalizedOperation $operation): ?GeneratedType
    {
        if (!$operation->returnsBody || $operation->output === null) {
            return null;
        }

        $isExplicitDto = $operation->output->class !== $operation->class;

        // For explicit DTO output classes: use the operation normalizationContext as-is so
        // DTO-level groups are respected, but don't apply entity-level group filtering.
        $normContext = $isExplicitDto ? $operation->normalizationContextRaw : $operation->normalizationContextRaw;

        $properties = $this->propertyNormalizer->normalizeProperties(
            class: $operation->output->class,
            normalizationContext: $normContext,
            denormalizationContext: [],
            isOutput: true,
        );

        // If the DTO has no groups (returned empty properties) and context has groups,
        // re-extract without group filtering (treat DTO as fully exposed)
        if (empty($properties) && $isExplicitDto && !empty($operation->getNormalizationGroups())) {
            $properties = $this->propertyNormalizer->normalizeProperties(
                class: $operation->output->class,
                normalizationContext: [], // No group filter → all properties exposed
                denormalizationContext: [],
                isOutput: true,
            );
        }

        $typeProperties = $this->resolveTypeProperties($properties, $operation->output->groups);

        $typeName = $this->namingStrategy->generateOutputTypeNameForOperation($operation);

        return new GeneratedType(
            name: $typeName,
            properties: $typeProperties,
            isInput: false,
            isCollection: $operation->isCollection,
        );
    }

    /**
     * Resolves input type (request body type) for an operation.
     *
     * When an explicit input DTO class is used (different from entity class), normalize its
     * properties without entity-level group filtering - the DTO either has its own groups or none.
     */
    public function resolveInputType(NormalizedOperation $operation): ?GeneratedType
    {
        if (!$operation->acceptsBody || $operation->input === null) {
            return null;
        }

        $isExplicitDto = $operation->input->class !== $operation->class;

        $properties = $this->propertyNormalizer->normalizeProperties(
            class: $operation->input->class,
            normalizationContext: [],
            denormalizationContext: $operation->denormalizationContextRaw,
            isOutput: false,
        );

        // If the DTO has no groups (returned empty properties) and context has groups,
        // re-extract without group filtering (treat DTO as fully exposed)
        if (empty($properties) && $isExplicitDto && !empty($operation->getDenormalizationGroups())) {
            $properties = $this->propertyNormalizer->normalizeProperties(
                class: $operation->input->class,
                normalizationContext: [],
                denormalizationContext: [], // No group filter → all properties accepted
                isOutput: false,
            );
        }

        $typeProperties = $this->resolveTypeProperties($properties, $operation->input->groups);

        $typeName = $this->namingStrategy->generateInputTypeNameForOperation($operation);

        return new GeneratedType(
            name: $typeName,
            properties: $typeProperties,
            isInput: true,
            isCollection: false,
        );
    }

    /**
     * Resolves an array of TypeProperty from NormalizedProperty array.
     *
     * @param NormalizedProperty[] $properties
     * @param string[] $contextGroups
     * @return TypeProperty[]
     */
    private function resolveTypeProperties(array $properties, array $contextGroups): array
    {
        $typeProperties = [];

        foreach ($properties as $property) {
            $typeProperty = $this->resolveTypeProperty($property, $contextGroups);

            if ($typeProperty !== null) {
                $typeProperties[] = $typeProperty;
            }
        }

        return $typeProperties;
    }

    /**
     * Resolves a single TypeProperty from a NormalizedProperty.
     *
     * @param string[] $contextGroups
     */
    private function resolveTypeProperty(NormalizedProperty $property, array $contextGroups): ?TypeProperty
    {
        // Determine TypeScript type
        $tsType = $this->resolvePropertyType($property, $contextGroups);

        if ($tsType === null) {
            return null;
        }

        // Determine if optional (nullable or not required)
        $isOptional = $property->nullable;

        return new TypeProperty(
            name: $property->name,
            type: $tsType,
            isOptional: $isOptional,
            isArray: false, // Array info is in the tsType already
        );
    }

    /**
     * Resolves the TypeScript type for a property.
     *
     * @param string[] $contextGroups
     */
    private function resolvePropertyType(NormalizedProperty $property, array $contextGroups): ?string
    {
        // Handle relations
        if ($property->relation !== null) {
            return $this->relationResolver->resolveRelationType(
                $property->relation,
                $contextGroups
            );
        }

        // Handle enums
        if ($this->typeMapper->isBackedEnum($property->phpType)) {
            return $this->typeMapper->mapEnumToTypeScript($property->phpType);
        }

        // Handle primitive types
        return $this->typeMapper->mapPhpTypeToTypeScript($property->phpType);
    }

    /**
     * Resolves a base type for a resource (all properties, no groups).
     *
     * This is used for generating a "full" type definition that can be referenced.
     */
    public function resolveBaseType(string $resourceClass, string $shortName): GeneratedType
    {
        $properties = $this->propertyNormalizer->normalizeProperties(
            class: $resourceClass,
            normalizationContext: [],
            denormalizationContext: [],
            isOutput: true,
        );

        $typeProperties = $this->resolveTypeProperties($properties, []);

        return new GeneratedType(
            name: $shortName,
            properties: $typeProperties,
            isInput: false,
            isCollection: false,
        );
    }

    /**
     * Checks if two GeneratedType instances are identical (for deduplication).
     */
    public function areTypesIdentical(GeneratedType $type1, GeneratedType $type2): bool
    {
        if ($type1->isInput !== $type2->isInput) {
            return false;
        }

        if ($type1->isCollection !== $type2->isCollection) {
            return false;
        }

        if (count($type1->properties) !== count($type2->properties)) {
            return false;
        }

        // Compare properties (order-independent)
        $props1 = $this->sortPropertiesByName($type1->properties);
        $props2 = $this->sortPropertiesByName($type2->properties);

        foreach ($props1 as $index => $prop1) {
            $prop2 = $props2[$index];

            if ($prop1->name !== $prop2->name
                || $prop1->type !== $prop2->type
                || $prop1->isOptional !== $prop2->isOptional
                || $prop1->isArray !== $prop2->isArray) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sorts properties by name for comparison.
     *
     * @param TypeProperty[] $properties
     * @return TypeProperty[]
     */
    private function sortPropertiesByName(array $properties): array
    {
        $sorted = $properties;
        usort($sorted, fn(TypeProperty $a, TypeProperty $b) => $a->name <=> $b->name);
        return $sorted;
    }
}
