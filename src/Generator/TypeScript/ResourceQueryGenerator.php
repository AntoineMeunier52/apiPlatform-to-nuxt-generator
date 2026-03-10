<?php

declare(strict_types=1);

namespace App\Generator\TypeScript;

use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\Resolver\QueryTypeResolver;
use App\Generator\Writer\FileWriter;
use App\Generator\Config\GeneratorConfiguration;

/**
 * Generates TypeScript query parameter types for API resources.
 *
 * Output example (generated/queries/Program.ts):
 * ```typescript
 * export interface ProgramQuery {
 *   name?: string
 *   'createdAt[before]'?: string
 *   'createdAt[after]'?: string
 *   'order[name]'?: 'asc' | 'desc'
 *   page?: number
 *   itemsPerPage?: number
 * }
 * ```
 */
class ResourceQueryGenerator extends CoreGenerator
{
    public function __construct(
        FileWriter $fileWriter,
        GeneratorConfiguration $config,
        private readonly QueryTypeResolver $queryTypeResolver,
    ) {
        parent::__construct($fileWriter, $config);
    }

    /**
     * Generates query type files for all resources.
     *
     * @param NormalizedResource[] $resources
     */
    public function generate(mixed $data = null): void
    {
        $resources = $data;

        foreach ($resources as $resource) {
            $this->generateResourceQuery($resource);
        }
    }

    /**
     * Generates query types for a single resource.
     */
    private function generateResourceQuery(NormalizedResource $resource): void
    {
        // Collect all unique query types for this resource
        $queryTypes = $this->queryTypeResolver->resolveUniqueQueryTypes($resource->operations);

        if (empty($queryTypes)) {
            return; // No query types to generate
        }

        // Generate file content
        $content = $this->generateQueryFile($resource, $queryTypes);

        // Write to file
        $filePath = "queries/{$resource->shortName}.ts";
        $this->write($filePath, $content);
    }

    /**
     * Generates the complete query file content.
     *
     * @param \App\Generator\DTO\Output\QueryTypeDefinition[] $queryTypes
     */
    private function generateQueryFile(NormalizedResource $resource, array $queryTypes): string
    {
        $content = $this->generateFileHeader(
            "Query parameter types for {$resource->shortName} resource"
        );

        foreach ($queryTypes as $queryType) {
            $content .= $this->generateQueryInterface($queryType);
            $content .= "\n";
        }

        return $content;
    }

    /**
     * Generates a query interface.
     */
    private function generateQueryInterface(\App\Generator\DTO\Output\QueryTypeDefinition $queryType): string
    {
        $code = '';

        // JSDoc comment
        $code .= $this->generateJsDoc(["Query parameters for {$queryType->typeName}"]);

        // Interface declaration
        $code .= "export interface {$queryType->typeName} {\n";

        // Properties
        foreach ($queryType->properties as $property) {
            $optional = $property->optional ? '?' : '';

            // Handle special property names (with brackets, etc.)
            $propName = $this->formatPropertyName($property->name);

            // Add JSDoc if description exists
            if ($property->description !== null) {
                $comment = $this->indent("/** {$property->description} */\n", 1);
                $code .= $comment;
            }

            $code .= "  {$propName}{$optional}: {$property->tsType}\n";
        }

        $code .= "}\n";

        return $code;
    }

    /**
     * Formats property name for TypeScript.
     * Properties with special characters need quotes.
     */
    private function formatPropertyName(string $name): string
    {
        // If name contains brackets or other special chars, wrap in quotes
        if (preg_match('/[^a-zA-Z0-9_$]/', $name)) {
            return "'{$name}'";
        }

        return $name;
    }
}
