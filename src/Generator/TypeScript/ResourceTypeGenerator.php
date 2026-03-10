<?php

declare(strict_types=1);

namespace App\Generator\TypeScript;

use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\DTO\Output\GeneratedType;
use App\Generator\DTO\Output\TypeAlias;
use App\Generator\DTO\Output\TypeProperty;
use App\Generator\Normalizer\PropertyNormalizer;
use App\Generator\Resolver\TypeResolver;
use App\Generator\Deduplication\TypeDeduplicator;
use App\Generator\Writer\FileWriter;
use App\Generator\Config\GeneratorConfiguration;

/**
 * Generates TypeScript type definitions for API resources.
 *
 * Output example (generated/types/Program.ts):
 * ```typescript
 * export interface ProgramDetail {
 *   id: number
 *   name: string
 *   description?: string
 *   weeks: ProgramWeek[]
 * }
 *
 * export interface ProgramList {
 *   id: number
 *   name: string
 * }
 *
 * export interface ProgramCreateInput {
 *   name: string
 *   description?: string
 * }
 *
 * export type ProgramUpdateInput = Partial<ProgramCreateInput>
 * ```
 */
class ResourceTypeGenerator extends CoreGenerator
{
    public function __construct(
        FileWriter $fileWriter,
        GeneratorConfiguration $config,
        private readonly TypeResolver $typeResolver,
        private readonly TypeDeduplicator $typeDeduplicator,
        private readonly PropertyNormalizer $propertyNormalizer,
    ) {
        parent::__construct($fileWriter, $config);
    }

    /**
     * Generates type files for all resources.
     *
     * @param NormalizedResource[] $data
     */
    public function generate(mixed $data = null): void
    {
        $resources = $data ?? [];

        foreach ($resources as $resource) {
            $this->generateResourceTypes($resource);
        }
    }

    /**
     * Generates types for a single resource.
     */
    private function generateResourceTypes(NormalizedResource $resource): void
    {
        $types = $this->collectAllTypes($resource);

        // Merge same-named types with different property sets into a single type.
        // This happens when multiple operations use the same explicit DTO class but with different
        // normalization groups (e.g. basic vs extended groups on the same output DTO).
        $types = $this->mergeTypesByName($types);

        // Deduplicate types
        $result = $this->typeDeduplicator->deduplicateWithPriority($types);
        $uniqueTypes = $result['types'];

        // Filter out self-referencing aliases and aliases that duplicate an existing type name
        $existingTypeNames = array_map(fn(GeneratedType $t) => $t->name, $uniqueTypes);
        $aliases = array_values(array_filter(
            $result['aliases'],
            fn(TypeAlias $a) => $a->aliasName !== $a->targetName
                && !in_array($a->aliasName, $existingTypeNames, true)
        ));

        // Generate file content
        $content = $this->generateTypeFile($resource, $uniqueTypes, $aliases);

        // Write to file
        $filePath = "types/{$resource->shortName}.ts";
        $this->write($filePath, $content);
    }

    /**
     * Merges types that share the same name but have different property sets.
     *
     * When multiple operations use the same explicit DTO class with different normalization
     * groups, they produce same-named types with different properties. We merge them into a
     * single type containing all properties from all variants, making any property that does
     * not appear in all variants optional.
     *
     * Example:
     *   ExerciseLogSummaryOutput (basic)    → {id, name, totalSets}
     *   ExerciseLogSummaryOutput (extended) → {id, name, totalSets, estimatedOneRepMax}
     *   Merged                              → {id, name, totalSets, estimatedOneRepMax?}
     *
     * @param GeneratedType[] $types
     * @return GeneratedType[]
     */
    private function mergeTypesByName(array $types): array
    {
        $groups = [];
        foreach ($types as $type) {
            $groups[$type->name][] = $type;
        }

        $merged = [];
        foreach ($groups as $name => $group) {
            if (count($group) === 1) {
                $merged[] = $group[0];
                continue;
            }

            // Merge all variants: collect all unique properties across variants
            // A property is optional if it doesn't appear in all variants
            $allPropertyNames = [];
            $propertyByName = [];
            $variantCount = count($group);

            foreach ($group as $variant) {
                foreach ($variant->properties as $prop) {
                    $allPropertyNames[$prop->name] = ($allPropertyNames[$prop->name] ?? 0) + 1;
                    if (!isset($propertyByName[$prop->name])) {
                        $propertyByName[$prop->name] = $prop;
                    }
                }
            }

            // Build merged properties: if not in all variants, mark as optional
            $mergedProperties = [];
            foreach ($propertyByName as $propName => $prop) {
                $appearsInAll = $allPropertyNames[$propName] >= $variantCount;
                $mergedProperties[] = new TypeProperty(
                    name: $prop->name,
                    type: $prop->type,
                    isOptional: $prop->isOptional || !$appearsInAll,
                    isArray: $prop->isArray,
                );
            }

            // Use the first variant's metadata (isInput, isCollection)
            $canonical = $group[0];
            $merged[] = new GeneratedType(
                name: $canonical->name,
                properties: $mergedProperties,
                isInput: $canonical->isInput,
                isCollection: $canonical->isCollection,
            );
        }

        return $merged;
    }

    /**
     * Collects all types for a resource (input, output, from all operations).
     * Also collects types for nested DTO value objects referenced in properties.
     *
     * @return GeneratedType[]
     */
    private function collectAllTypes(NormalizedResource $resource): array
    {
        $types = [];

        foreach ($resource->operations as $operation) {
            // Output type
            $outputType = $this->typeResolver->resolveOutputType($operation);
            if ($outputType !== null) {
                $types[] = $outputType;
                // Also generate types for any nested DTO classes referenced in properties
                foreach ($this->collectNestedDtoTypes($outputType, $resource->className) as $nestedType) {
                    $types[] = $nestedType;
                }
            }

            // Input type
            $inputType = $this->typeResolver->resolveInputType($operation);
            if ($inputType !== null) {
                $types[] = $inputType;
            }
        }

        return $types;
    }

    /**
     * Collects TypeScript types for nested DTO value objects referenced in a type's properties.
     *
     * When an output DTO class has properties typed as other non-entity PHP classes (e.g.
     * MacroBreakdownOutput has MacroItemOutput properties), we need to also generate TypeScript
     * types for those nested DTO classes.
     *
     * @param GeneratedType $type The parent generated type to scan
     * @param string $resourceClass The entity class name (to avoid regenerating entity types)
     * @return GeneratedType[] Nested DTO types to add
     */
    private function collectNestedDtoTypes(GeneratedType $type, string $resourceClass): array
    {
        $nestedTypes = [];
        $alreadySeen = [$type->name => true];

        foreach ($type->properties as $property) {
            // Extract the base type name (remove [] and | null)
            $baseType = trim(str_replace(['[]', '| null', '?'], '', $property->type));

            // Skip built-in TypeScript types
            $tsBuiltins = ['string', 'number', 'boolean', 'null', 'undefined', 'any', 'unknown', 'void', 'object'];
            if (in_array($baseType, $tsBuiltins, true) || !ctype_upper($baseType[0] ?? '')) {
                continue;
            }

            // Skip if already seen (avoid duplicates)
            if (isset($alreadySeen[$baseType])) {
                continue;
            }
            $alreadySeen[$baseType] = true;

            // Try to find the corresponding PHP class in App\Dto\ namespace
            $dtoClass = $this->resolveDtoClassName($baseType);
            if ($dtoClass === null || $dtoClass === $resourceClass) {
                continue;
            }

            // Generate the nested DTO type by normalizing its properties (no group filter)
            $properties = $this->propertyNormalizer->normalizeProperties(
                class: $dtoClass,
                normalizationContext: [],
                denormalizationContext: [],
                isOutput: true,
            );

            if (empty($properties)) {
                continue;
            }

            $typeProperties = [];
            foreach ($properties as $prop) {
                $typeProperties[] = new TypeProperty(
                    name: $prop->name,
                    type: $prop->tsType,
                    isOptional: $prop->nullable,
                    isArray: false,
                );
            }

            $nestedTypes[] = new GeneratedType(
                name: $baseType,
                properties: $typeProperties,
                isInput: false,
                isCollection: false,
            );
        }

        return $nestedTypes;
    }

    /**
     * Attempts to resolve a TypeScript type name back to a PHP DTO class.
     * Searches common DTO namespaces.
     */
    private function resolveDtoClassName(string $typeName): ?string
    {
        $candidates = [
            "App\\Dto\\Output\\{$typeName}",
            "App\\Dto\\Input\\{$typeName}",
            "App\\Dto\\{$typeName}",
        ];

        // Also try subdirectory DTOs (e.g. App\Dto\Output\Nutrition\MacroItemOutput)
        // by scanning for any App\Dto class with this short name
        foreach (get_declared_classes() as $class) {
            if (!str_starts_with($class, 'App\\Dto\\')) {
                continue;
            }
            $parts = explode('\\', $class);
            if (end($parts) === $typeName) {
                return $class;
            }
        }

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Generates the complete TypeScript file content.
     *
     * @param GeneratedType[] $types
     * @param TypeAlias[] $aliases
     */
    private function generateTypeFile(
        NormalizedResource $resource,
        array $types,
        array $aliases,
    ): string {
        $content = $this->generateFileHeader(
            "Type definitions for {$resource->shortName} resource"
        );

        // Generate imports (if any relations exist), but skip types defined in this same file
        $localTypeNames = array_map(fn(GeneratedType $t) => $t->name, $types);
        $imports = $this->collectImports($types, $localTypeNames);
        if (!empty($imports)) {
            $content .= $this->generateImports($imports);
        }

        // Generate type interfaces
        foreach ($types as $type) {
            $content .= $this->generateTypeInterface($type);
            $content .= "\n";
        }

        // Generate type aliases
        foreach ($aliases as $alias) {
            $content .= $this->generateTypeAlias(
                name: $alias->aliasName,
                type: $alias->targetName,
                description: "Alias for {$alias->targetName}",
            );
            $content .= "\n";
        }

        return $content;
    }

    /**
     * Generates a TypeScript interface for a type.
     */
    private function generateTypeInterface(GeneratedType $type): string
    {
        $code = '';

        // JSDoc comment
        $description = $this->generateTypeDescription($type);
        $code .= $this->generateJsDoc([$description]);

        // Interface declaration
        $code .= "export interface {$type->name} {\n";

        // Properties
        foreach ($type->properties as $property) {
            $optional = $property->isOptional ? '?' : '';
            $arrayBrackets = $property->isArray ? '[]' : '';
            $code .= "  {$property->name}{$optional}: {$property->type}{$arrayBrackets}\n";
        }

        $code .= "}\n";

        return $code;
    }

    /**
     * Generates a description for a type.
     */
    private function generateTypeDescription(GeneratedType $type): string
    {
        if ($type->isInput) {
            return "Input type for {$type->name}";
        }

        if ($type->isCollection) {
            return "Collection item type for {$type->name}";
        }

        return "Type definition for {$type->name}";
    }

    /**
     * Collects necessary imports from types.
     *
     * @param GeneratedType[] $types
     * @return array<string, string[]>
     */
    private function collectImports(array $types, array $localTypeNames = []): array
    {
        $imports = [];

        foreach ($types as $type) {
            foreach ($type->properties as $property) {
                // Check if property type is a reference to another resource type
                if ($this->isRelationType($property->type)) {
                    $relatedType = $property->type;

                    // Extract the actual type name (remove array brackets, union types, etc.)
                    $typeNames = $this->extractTypeNames($relatedType);

                    foreach ($typeNames as $typeName) {
                        // Skip types already defined in this file (e.g. nested DTOs)
                        if (in_array($typeName, $localTypeNames, true)) {
                            continue;
                        }

                        // File name is the entity/resource base name (strip generated suffixes)
                        $fileBaseName = $this->stripTypeSuffix($typeName);
                        $module = "./{$fileBaseName}";

                        if (!isset($imports[$module])) {
                            $imports[$module] = [];
                        }

                        if (!in_array($typeName, $imports[$module], true)) {
                            $imports[$module][] = $typeName;
                        }
                    }
                }
            }
        }

        return $imports;
    }

    /**
     * Checks if a type is a relation to another resource.
     */
    private function isRelationType(string $type): bool
    {
        // Check if type starts with uppercase (convention for resource types)
        // and is not a built-in type
        $builtInTypes = ['string', 'number', 'boolean', 'null', 'undefined', 'any', 'unknown', 'void'];

        // Remove array brackets and extract base type
        $baseType = trim(str_replace(['[]', '|', '(', ')'], ' ', $type));
        $parts = array_filter(array_map('trim', explode(' ', $baseType)));

        foreach ($parts as $part) {
            if (!in_array($part, $builtInTypes, true) && ctype_upper($part[0] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts type names from a complex type string.
     *
     * Example: "ProgramWeek[] | string" -> ["ProgramWeek"]
     *
     * @return string[]
     */
    private function extractTypeNames(string $type): array
    {
        // Remove array brackets, parentheses, and split by union
        $type = str_replace(['[]', '(', ')'], '', $type);
        $parts = array_map('trim', explode('|', $type));

        $typeNames = [];
        $builtInTypes = ['string', 'number', 'boolean', 'null', 'undefined', 'any', 'unknown', 'void'];

        foreach ($parts as $part) {
            if (!in_array($part, $builtInTypes, true) && ctype_upper($part[0] ?? '')) {
                $typeNames[] = $part;
            }
        }

        return array_unique($typeNames);
    }

    /**
     * Strips generated type suffixes to recover the resource/entity base name (= file name).
     *
     * E.g. "GoalDetail" → "Goal", "ProgramWeekList" → "ProgramWeek",
     *      "MacroItemOutput" → "MacroItemOutput" (no suffix to strip — DTO)
     */
    private function stripTypeSuffix(string $typeName): string
    {
        $naming = $this->config->naming;
        $suffixes = [
            $naming->getTypeSuffix('item_output'),      // Detail
            $naming->getTypeSuffix('collection_output'), // List
            $naming->getTypeSuffix('create_input'),      // CreateInput
            $naming->getTypeSuffix('update_input'),      // UpdateInput
            $naming->getTypeSuffix('replace_input'),     // ReplaceInput
        ];

        foreach ($suffixes as $suffix) {
            if ($suffix !== '' && str_ends_with($typeName, $suffix)) {
                return substr($typeName, 0, -strlen($suffix));
            }
        }

        return $typeName;
    }
}
