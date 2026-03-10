<?php

declare(strict_types=1);

namespace App\Generator\Resolver;

use App\Generator\DTO\Normalized\NormalizedOperation;
use App\Generator\DTO\Output\PathParam;

/**
 * Resolves path parameters (URI variables) for operations.
 *
 * Converts Doctrine identifier types to TypeScript types:
 * - int -> number
 * - string (UUID) -> string
 * - Ulid -> string
 * - etc.
 */
readonly class PathParamResolver
{
    public function __construct(
        private TypeMapper $typeMapper,
    ) {}

    /**
     * Resolves all path parameters for an operation.
     *
     * @return PathParam[]
     */
    public function resolvePathParams(NormalizedOperation $operation): array
    {
        $pathParams = [];

        foreach ($operation->uriVariables as $parameterName => $parameterInfo) {
            $pathParams[] = $this->resolvePathParam($parameterName, $parameterInfo);
        }

        return $pathParams;
    }

    /**
     * Resolves a single path parameter.
     *
     * @param array{class: string, type: string} $parameterInfo
     */
    private function resolvePathParam(string $name, array $parameterInfo): PathParam
    {
        $phpType = $parameterInfo['type'];
        $tsType = $this->mapPhpTypeToTypeScript($phpType);

        return new PathParam(
            name: $name,
            tsType: $tsType,
            isOptional: false, // Path params are never optional
        );
    }

    /**
     * Maps PHP identifier type to TypeScript type.
     */
    private function mapPhpTypeToTypeScript(string $phpType): string
    {
        // Path params are required URL segments — strip nullable prefix/suffix
        $phpType = ltrim($phpType, '?');
        $phpType = str_replace(['|null', 'null|'], '', $phpType);
        $phpType = trim($phpType);

        // Handle common identifier types
        return match ($phpType) {
            'int', 'integer' => 'number',
            'string' => 'string',
            'Symfony\Component\Uid\Ulid' => 'string',
            'Symfony\Component\Uid\Uuid' => 'string',
            'Ramsey\Uuid\UuidInterface' => 'string',
            default => $this->typeMapper->mapPhpTypeToTypeScript($phpType),
        };
    }

    /**
     * Extracts path param names from URI template.
     *
     * Example: "/api/programs/{id}/weeks/{weekId}" -> ["id", "weekId"]
     *
     * @return string[]
     */
    public function extractParamNamesFromUriTemplate(string $uriTemplate): array
    {
        preg_match_all('/\{([^}]+)\}/', $uriTemplate, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Builds the path string for fetch call.
     *
     * Converts "/api/programs/{id}/weeks/{weekId}"
     * to "`/api/programs/${id}/weeks/${weekId}`"
     */
    public function buildPathString(string $uriTemplate, array $pathParams): string
    {
        // Clean up API Platform internal patterns
        $path = $this->cleanUriTemplate($uriTemplate);

        foreach ($pathParams as $param) {
            $path = str_replace(
                '{' . $param->name . '}',
                '${' . $param->name . '}',
                $path
            );
        }

        return '`' . $path . '`';
    }

    /**
     * Cleans API Platform URI template patterns.
     *
     * Removes {._format} and other internal patterns.
     */
    private function cleanUriTemplate(string $uriTemplate): string
    {
        // Remove {._format} pattern
        $cleaned = preg_replace('/\{\._format\}/', '', $uriTemplate) ?? $uriTemplate;

        // Ensure path starts with /api if not already
        if (!str_starts_with($cleaned, '/api/')) {
            $cleaned = '/api' . $cleaned;
        }

        return $cleaned;
    }
}
