<?php

declare(strict_types=1);

namespace App\Generator\TypeScript;

use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\DTO\Normalized\NormalizedOperation;
use App\Generator\DTO\Output\OperationSignature;
use App\Generator\Resolver\OperationSignatureResolver;
use App\Generator\Resolver\PathParamResolver;
use App\Generator\Writer\FileWriter;
use App\Generator\Config\GeneratorConfiguration;

/**
 * Generates TypeScript API functions for resources.
 *
 * Output example (generated/api/program.ts):
 * ```typescript
 * import type { ProgramDetail, ProgramList, ProgramCreateInput } from '../types/Program'
 * import type { ProgramQuery } from '../queries/Program'
 * import type { HydraCollection } from '../core/hydra'
 * import { apiFetcher } from '../core/fetcher'
 *
 * export async function getPrograms(query?: ProgramQuery): Promise<HydraCollection<ProgramList>> {
 *   return apiFetcher.get('/api/programs', { query })
 * }
 *
 * export async function getProgram(id: number): Promise<ProgramDetail> {
 *   return apiFetcher.get(`/api/programs/${id}`)
 * }
 *
 * export async function createProgram(data: ProgramCreateInput): Promise<ProgramDetail> {
 *   return apiFetcher.post('/api/programs', { body: data })
 * }
 * ```
 */
class ResourceApiGenerator extends CoreGenerator
{
    public function __construct(
        FileWriter $fileWriter,
        GeneratorConfiguration $config,
        private readonly OperationSignatureResolver $signatureResolver,
        private readonly PathParamResolver $pathParamResolver,
    ) {
        parent::__construct($fileWriter, $config);
    }

    /**
     * Generates API function files for all resources.
     *
     * @param NormalizedResource[] $resources
     */
    public function generate(mixed $data = null): void
    {
        $resources = $data;

        foreach ($resources as $resource) {
            $this->generateResourceApi($resource);
        }
    }

    /**
     * Generates API functions for a single resource.
     */
    private function generateResourceApi(NormalizedResource $resource): void
    {
        $content = $this->generateApiFile($resource);

        // Write to file (lowercase filename convention)
        $filePath = "api/" . strtolower($resource->shortName) . ".ts";
        $this->write($filePath, $content);
    }

    /**
     * Generates the complete API file content.
     */
    private function generateApiFile(NormalizedResource $resource): string
    {
        $content = $this->generateFileHeader(
            "API functions for {$resource->shortName} resource"
        );

        // Generate imports
        $content .= $this->generateApiImports($resource);
        $content .= "\n";

        // Generate functions for each operation, skipping duplicate function names
        $seenFunctionNames = [];
        foreach ($resource->operations as $operation) {
            $signature = $this->signatureResolver->resolveSignature($operation);
            if (isset($seenFunctionNames[$signature->functionName])) {
                continue;
            }
            $seenFunctionNames[$signature->functionName] = true;
            $content .= $this->generateOperationFunction($operation);
            $content .= "\n";
        }

        return $content;
    }

    /**
     * Generates imports for the API file.
     */
    private function generateApiImports(NormalizedResource $resource): string
    {
        $imports = [];

        // Collect all type names used
        $typeNames = $this->collectTypeNames($resource);

        if (!empty($typeNames)) {
            $imports["../types/{$resource->shortName}"] = $typeNames;
        }

        // Check if query types are needed
        if ($this->hasQueryTypes($resource)) {
            $imports["../queries/{$resource->shortName}"] = ["{$resource->shortName}Query"];
        }

        // Import Hydra types if needed
        if ($this->hasPaginatedOperations($resource)) {
            $imports['../core/hydra'] = ['HydraCollection'];
        }

        // Import fetcher
        $content = $this->generateImports($imports);
        $content .= "import { apiFetcher } from '../core/fetcher'\n";

        return $content;
    }

    /**
     * Generates a function for a single operation.
     */
    private function generateOperationFunction(NormalizedOperation $operation): string
    {
        $signature = $this->signatureResolver->resolveSignature($operation);

        $code = '';

        // JSDoc comment
        $code .= $this->generateOperationJsDoc($operation, $signature);

        // Function declaration
        $params = $this->signatureResolver->generateParametersString($signature);
        $returnType = $signature->returnsVoid ? 'Promise<void>' : "Promise<" . ($signature->isPaginated ? "HydraCollection<{$signature->outputTypeName}>" : $signature->outputTypeName) . ">";
        $code .= "export async function {$signature->functionName}{$params}: {$returnType} {\n";

        // Function body
        $body = $this->generateFunctionBody($operation, $signature);
        $code .= $this->indent($body, 1);

        $code .= "}\n";

        return $code;
    }

    /**
     * Generates JSDoc for an operation.
     */
    private function generateOperationJsDoc(NormalizedOperation $operation, OperationSignature $signature): string
    {
        $lines = [];

        // Description
        $lines[] = $this->generateOperationDescription($operation);

        // Add param documentation
        foreach ($signature->pathParams as $param) {
            $lines[] = "@param {$param->name} - Path parameter";
        }

        if ($signature->inputTypeName !== null) {
            $lines[] = "@param data - Request body";
        }

        if ($signature->queryTypeName !== null) {
            $lines[] = "@param query - Query parameters";
        }

        return $this->generateJsDoc($lines);
    }

    /**
     * Generates operation description.
     */
    private function generateOperationDescription(NormalizedOperation $operation): string
    {
        $resourceName = $this->getResourceNameFromClass($operation->class);

        return match ($operation->operationType->value) {
            'GetCollection' => "Fetches a collection of {$resourceName}",
            'Get' => "Fetches a single {$resourceName}",
            'Post' => "Creates a new {$resourceName}",
            'Put' => "Replaces a {$resourceName}",
            'Patch' => "Updates a {$resourceName}",
            'Delete' => "Deletes a {$resourceName}",
            default => "Custom operation: {$operation->customOperationName}",
        };
    }

    /**
     * Extracts resource name from class name.
     */
    private function getResourceNameFromClass(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }

    /**
     * Generates the function body.
     */
    private function generateFunctionBody(NormalizedOperation $operation, OperationSignature $signature): string
    {
        $method = strtolower($signature->httpMethod);
        $path = $this->pathParamResolver->buildPathString($signature->path, $signature->pathParams);

        $code = '';

        // Build options object
        $hasOptions = $signature->inputTypeName !== null || $signature->queryTypeName !== null;

        if (!$hasOptions) {
            // Simple call without options
            $code .= "return apiFetcher.{$method}({$path})\n";
        } else {
            // Call with options
            $code .= "return apiFetcher.{$method}({$path}, {\n";

            if ($signature->queryTypeName !== null) {
                $code .= "  query,\n";
            }

            if ($signature->inputTypeName !== null) {
                $code .= "  body: data,\n";
            }

            $code .= "})\n";
        }

        return $code;
    }

    /**
     * Collects all type names used in operations.
     *
     * @return string[]
     */
    private function collectTypeNames(NormalizedResource $resource): array
    {
        $typeNames = [];

        foreach ($resource->operations as $operation) {
            $signature = $this->signatureResolver->resolveSignature($operation);

            // Add output type if not void
            if (!$signature->returnsVoid) {
                $typeNames[] = $signature->outputTypeName;
            }

            // Add input type if exists
            if ($signature->inputTypeName !== null) {
                $typeNames[] = $signature->inputTypeName;
            }
        }

        return array_unique($typeNames);
    }

    /**
     * Checks if resource has query types.
     */
    private function hasQueryTypes(NormalizedResource $resource): bool
    {
        foreach ($resource->operations as $operation) {
            if (!empty($operation->filters) || $operation->isPaginated) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if resource has paginated operations.
     */
    private function hasPaginatedOperations(NormalizedResource $resource): bool
    {
        foreach ($resource->operations as $operation) {
            if ($operation->isPaginated) {
                return true;
            }
        }

        return false;
    }
}
