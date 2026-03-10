<?php

declare(strict_types=1);

namespace App\Generator\Normalizer;

use App\Generator\DTO\Normalized\NormalizedProperty;
use App\Generator\DTO\Normalized\NormalizedRelation;
use App\Generator\DTO\Normalized\RelationType;
use App\Generator\Resolver\TypeMapper;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use ApiPlatform\Metadata\ApiResource;

/**
 * Normalizes entity properties into NormalizedProperty DTOs.
 *
 * Handles:
 * - Type extraction and mapping
 * - Relation detection
 * - Serialization group analysis
 * - Nullable detection
 * - Array/collection handling
 */
readonly class PropertyNormalizer
{
    public function __construct(
        private PropertyInfoExtractorInterface $propertyInfo,
        private ClassMetadataFactoryInterface $serializerMetadataFactory,
        private TypeMapper $typeMapper,
        private SerializationContextAnalyzer $contextAnalyzer,
    ) {}

    /**
     * Normalizes all properties of a class.
     *
     * @param array<string, mixed> $normalizationContext For output types
     * @param array<string, mixed> $denormalizationContext For input types
     * @return NormalizedProperty[]
     */
    public function normalizeProperties(
        string $class,
        array $normalizationContext = [],
        array $denormalizationContext = [],
        bool $isOutput = true,
    ): array {
        $properties = $this->propertyInfo->getProperties($class) ?? [];
        $normalizedProperties = [];

        foreach ($properties as $propertyName) {
            $property = $this->normalizeProperty(
                $class,
                $propertyName,
                $normalizationContext,
                $denormalizationContext,
                $isOutput
            );

            if ($property !== null) {
                $normalizedProperties[] = $property;
            }
        }

        return $normalizedProperties;
    }

    /**
     * Normalizes a single property.
     */
    public function normalizeProperty(
        string $class,
        string $propertyName,
        array $normalizationContext = [],
        array $denormalizationContext = [],
        bool $isOutput = true,
    ): ?NormalizedProperty {
        $propertyGroups = $this->extractPropertyGroups($class, $propertyName);

        // Check if property is exposed based on context
        if ($isOutput) {
            if (!$this->contextAnalyzer->isPropertyReadable($propertyGroups, $normalizationContext)) {
                return null; // Property not exposed in this operation
            }
        } else {
            if (!$this->contextAnalyzer->isPropertyWritable($propertyGroups, $denormalizationContext)) {
                return null; // Property not writable in this operation
            }
        }

        $mainType = $this->propertyInfo->getType($class, $propertyName);

        if ($mainType === null) {
            // Fallback to mixed type
            return new NormalizedProperty(
                name: $propertyName,
                phpType: 'mixed',
                tsType: 'any',
                nullable: true,
                identifier: false,
                readable: $this->contextAnalyzer->isPropertyReadable($propertyGroups, $normalizationContext),
                writable: $this->contextAnalyzer->isPropertyWritable($propertyGroups, $denormalizationContext),
                relation: null,
                groups: $propertyGroups,
            );
        }

        // Handle both new TypeInfo and legacy PropertyInfo types
        $phpType = $this->extractPhpType($mainType);
        $nullable = $this->extractIsNullable($mainType) || $this->isNullableByGroups($propertyGroups, $normalizationContext);
        $isArray = $this->extractIsCollection($mainType);
        $relation = $this->extractRelationFromType($class, $propertyName, $mainType, $normalizationContext);

        // Map PHP type to TypeScript type
        $tsType = $this->typeMapper->mapPhpTypeToTypeScript($phpType);
        if ($isArray && !str_ends_with($tsType, '[]')) {
            $tsType .= '[]';
        }
        if ($nullable && !str_contains($tsType, '| null')) {
            $tsType .= ' | null';
        }

        // Check if it's an identifier
        $isIdentifier = $this->isIdentifierProperty($class, $propertyName);

        return new NormalizedProperty(
            name: $propertyName,
            phpType: $phpType,
            tsType: $tsType,
            nullable: $nullable,
            identifier: $isIdentifier,
            readable: $this->contextAnalyzer->isPropertyReadable($propertyGroups, $normalizationContext),
            writable: $this->contextAnalyzer->isPropertyWritable($propertyGroups, $denormalizationContext),
            relation: $relation,
            groups: $propertyGroups,
        );
    }

    /**
     * Extracts serialization groups for a property.
     *
     * @return string[]
     */
    private function extractPropertyGroups(string $class, string $propertyName): array
    {
        try {
            $classMetadata = $this->serializerMetadataFactory->getMetadataFor($class);
            $attributesMetadata = $classMetadata->getAttributesMetadata();

            if (!isset($attributesMetadata[$propertyName])) {
                return [];
            }

            $propertyMetadata = $attributesMetadata[$propertyName];
            return $propertyMetadata->getGroups();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Extracts PHP type string from either new TypeInfo or legacy PropertyInfo Type.
     */
    private function extractPhpType(LegacyType|Type $type): string
    {
        // Handle new TypeInfo types
        if ($type instanceof Type) {
            // Unwrap NullableType to get the inner type
            if ($type instanceof NullableType) {
                return $this->extractPhpType($type->getWrappedType());
            }
            if ($type instanceof BuiltinType) {
                return (string) $type;
            }
            if ($type instanceof ObjectType) {
                return $type->getClassName();
            }
            if ($type instanceof CollectionType) {
                $collectionValueType = $type->getCollectionValueType();
                if ($collectionValueType instanceof ObjectType) {
                    return $collectionValueType->getClassName();
                }
                // Handle nullable inner type in collections
                if ($collectionValueType instanceof NullableType) {
                    $inner = $collectionValueType->getWrappedType();
                    if ($inner instanceof ObjectType) {
                        return $inner->getClassName();
                    }
                }
                return 'array';
            }
            // UnionType: extract first non-null type
            if ($type instanceof UnionType) {
                foreach ($type->getTypes() as $innerType) {
                    if ($innerType instanceof BuiltinType && (string)$innerType === 'null') {
                        continue;
                    }
                    return $this->extractPhpType($innerType);
                }
            }
            return 'mixed';
        }

        // Handle legacy PropertyInfo Type
        if ($type->getClassName() !== null) {
            return $type->getClassName();
        }
        return $type->getBuiltinType();
    }

    /**
     * Checks if type is nullable.
     */
    private function extractIsNullable(LegacyType|Type $type): bool
    {
        if ($type instanceof Type) {
            return $type instanceof NullableType;
        }

        // Legacy PropertyInfo
        return $type->isNullable();
    }

    /**
     * Checks if type is a collection/array.
     */
    private function extractIsCollection(LegacyType|Type $type): bool
    {
        if ($type instanceof Type) {
            // Unwrap NullableType to check inner type
            if ($type instanceof NullableType) {
                return $this->extractIsCollection($type->getWrappedType());
            }
            return $type instanceof CollectionType;
        }

        // Legacy PropertyInfo
        return $type->isCollection();
    }

    /**
     * Extracts the target class name from a type (if it's an object type).
     */
    private function extractClassName(LegacyType|Type $type): ?string
    {
        if ($type instanceof Type) {
            if ($type instanceof ObjectType) {
                return $type->getClassName();
            }
            if ($type instanceof CollectionType) {
                $valueType = $type->getCollectionValueType();
                if ($valueType instanceof ObjectType) {
                    return $valueType->getClassName();
                }
            }
            return null;
        }

        // Legacy PropertyInfo
        return $type->getClassName();
    }

    /**
     * Extracts relation information if the property is a relation.
     */
    private function extractRelationFromType(
        string $class,
        string $propertyName,
        LegacyType|Type $type,
        array $normalizationContext,
    ): ?NormalizedRelation {
        // Extract class name from the type
        $className = $this->extractClassName($type);
        if ($className === null) {
            return null;
        }

        // Check if it's a built-in type (DateTimeInterface, etc.)
        if ($this->isBuiltInClass($className)) {
            return null;
        }

        $isCollection = $this->extractIsCollection($type);

        // Handle application-level DTO value objects (non-entity App\ classes).
        // These are always serialized as inline objects, never as IRI strings.
        if ($this->isValueObjectDto($className)) {
            $parts = explode('\\', $className);
            return new NormalizedRelation(
                targetResource: end($parts),
                targetClassName: $className,
                type: RelationType::MANY_TO_ONE,
                isCollection: $isCollection,
                isSerializedAsObject: true, // DTOs are always inlined, never IRIs
                serializationGroups: [],
            );
        }

        // Check if it's a Doctrine entity (has @ORM\Entity or is an API Platform resource)
        if (!$this->isEntity($className)) {
            return null;
        }
        $relationType = $this->determineRelationType($class, $propertyName, $isCollection);

        // Determine if relation should be inlined based on groups
        $relationGroups = $this->extractAllPropertyGroupsFromClass($className);
        $contextGroups = $this->contextAnalyzer->extractNormalizationGroups($normalizationContext);
        $isSerializedAsObject = $this->contextAnalyzer->shouldInlineRelation($relationGroups, $contextGroups);

        // Extract short name from full class name
        $parts = explode('\\', $className);
        $targetResource = end($parts);

        return new NormalizedRelation(
            targetResource: $targetResource,
            targetClassName: $className,
            type: $relationType,
            isCollection: $isCollection,
            isSerializedAsObject: $isSerializedAsObject,
            serializationGroups: $relationGroups,
        );
    }

    /**
     * Determines the relation type (OneToOne, ManyToOne, OneToMany, ManyToMany).
     */
    private function determineRelationType(string $class, string $propertyName, bool $isCollection): RelationType
    {
        // Use reflection to check for Doctrine annotations/attributes
        try {
            $reflectionClass = new \ReflectionClass($class);
            $reflectionProperty = $reflectionClass->getProperty($propertyName);
            $attributes = $reflectionProperty->getAttributes();

            foreach ($attributes as $attribute) {
                $attributeName = $attribute->getName();

                return match ($attributeName) {
                    'Doctrine\ORM\Mapping\OneToOne' => RelationType::ONE_TO_ONE,
                    'Doctrine\ORM\Mapping\ManyToOne' => RelationType::MANY_TO_ONE,
                    'Doctrine\ORM\Mapping\OneToMany' => RelationType::ONE_TO_MANY,
                    'Doctrine\ORM\Mapping\ManyToMany' => RelationType::MANY_TO_MANY,
                    default => null,
                };

                if ($relationType !== null) {
                    return $relationType;
                }
            }
        } catch (\Throwable) {
            // Fallback to guessing based on collection
        }

        // Fallback: guess based on whether it's a collection
        return $isCollection ? RelationType::ONE_TO_MANY : RelationType::MANY_TO_ONE;
    }

    /**
     * Checks if a class is a built-in PHP class (not a domain entity).
     */
    private function isBuiltInClass(string $className): bool
    {
        $builtInClasses = [
            \DateTime::class,
            \DateTimeImmutable::class,
            \DateTimeInterface::class,
            \DateInterval::class,
            \DatePeriod::class,
            \stdClass::class,
        ];

        return in_array($className, $builtInClasses, true);
    }

    /**
     * Checks if a class is an application-level value object DTO.
     *
     * Value object DTOs are concrete PHP classes in the App\ namespace that are:
     * - NOT Doctrine entities or API Platform resources
     * - NOT abstract or interface
     * - Part of the application code (App\ prefix)
     *
     * These should be typed as their TypeScript class name (inlined), not as IRI strings.
     */
    private function isValueObjectDto(string $className): bool
    {
        // Only consider classes in our application namespace
        if (!str_starts_with($className, 'App\\')) {
            return false;
        }

        // Must not be an entity or API resource (those have their own IRI handling)
        if ($this->isEntity($className)) {
            return false;
        }

        try {
            $ref = new \ReflectionClass($className);
            // Exclude enums — BackedEnums are handled by TypeMapper as string literal unions
            if ($ref->isEnum()) {
                return false;
            }
            // Must be a concrete, instantiable class
            return !$ref->isAbstract() && !$ref->isInterface();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Checks if a class is a Doctrine entity.
     */
    private function isEntity(string $className): bool
    {
        try {
            $reflectionClass = new \ReflectionClass($className);
            $attributes = $reflectionClass->getAttributes();

            foreach ($attributes as $attribute) {
                $attributeName = $attribute->getName();

                if (str_contains($attributeName, 'Doctrine\ORM\Mapping\Entity')
                    || str_contains($attributeName, 'ApiPlatform\Metadata\ApiResource')) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Extracts all groups from all properties of a class.
     *
     * @return string[]
     */
    private function extractAllPropertyGroupsFromClass(string $class): array
    {
        $allGroups = [];

        try {
            $classMetadata = $this->serializerMetadataFactory->getMetadataFor($class);
            $attributesMetadata = $classMetadata->getAttributesMetadata();

            foreach ($attributesMetadata as $propertyMetadata) {
                $allGroups = array_merge($allGroups, $propertyMetadata->getGroups());
            }
        } catch (\Throwable) {
            // Ignore
        }

        return array_unique($allGroups);
    }

    /**
     * Checks if property should be nullable based on skip_null_values.
     */
    private function isNullableByGroups(array $propertyGroups, array $normalizationContext): bool
    {
        // If skip_null_values is true, properties can be omitted (= optional in TS)
        return $this->contextAnalyzer->shouldSkipNullValues($normalizationContext);
    }

    /**
     * Checks if a property is an identifier (primary key).
     */
    private function isIdentifierProperty(string $class, string $propertyName): bool
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
            $reflectionProperty = $reflectionClass->getProperty($propertyName);
            $attributes = $reflectionProperty->getAttributes();

            foreach ($attributes as $attribute) {
                $attributeName = $attribute->getName();

                if (str_contains($attributeName, 'Doctrine\ORM\Mapping\Id')
                    || str_contains($attributeName, 'ORM\Id')) {
                    return true;
                }
            }

            // Also check by name convention
            return in_array($propertyName, ['id', 'uuid'], true);
        } catch (\Throwable) {
            // Fallback to name check
            return in_array($propertyName, ['id', 'uuid'], true);
        }
    }
}
