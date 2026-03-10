<?php

declare(strict_types=1);

namespace App\Generator\Resolver;

use App\Generator\DTO\Normalized\NormalizedOperation;
use App\Generator\DTO\Output\OperationSignature;
use App\Generator\DTO\Output\PathParam;
use App\Generator\Naming\NamingStrategy;

/**
 * Resolves TypeScript function signatures for API operations.
 *
 * Generates signatures like:
 * - getProgram(id: number): Promise<ProgramDetail>
 * - createProgram(data: ProgramCreateInput): Promise<ProgramDetail>
 * - getPrograms(query?: ProgramQuery): Promise<HydraCollection<ProgramList>>
 */
readonly class OperationSignatureResolver
{
    public function __construct(
        private NamingStrategy $namingStrategy,
        private PathParamResolver $pathParamResolver,
        private QueryTypeResolver $queryTypeResolver,
    ) {}

    /**
     * Resolves the full function signature for an operation.
     */
    public function resolveSignature(NormalizedOperation $operation): OperationSignature
    {
        $functionName = $this->namingStrategy->generateFunctionName($operation);
        $pathParams = $this->pathParamResolver->resolvePathParams($operation);
        $queryTypeName = $this->queryTypeResolver->resolveQueryType($operation);
        $inputTypeName = $this->resolveBodyType($operation);
        $outputTypeName = $this->resolveReturnType($operation);

        return new OperationSignature(
            functionName: $functionName,
            httpMethod: $operation->httpMethod,
            path: $operation->uriTemplate,
            pathParams: $pathParams,
            queryTypeName: $queryTypeName,
            inputTypeName: $inputTypeName,
            outputTypeName: $outputTypeName,
            isCollection: $operation->isCollection,
            isPaginated: $operation->isPaginated,
            returnsVoid: !$operation->returnsBody,
            description: "Performs {$operation->httpMethod} operation on {$operation->uriTemplate}",
            sourceOperation: $operation->name,
        );
    }

    /**
     * Resolves the request body type (for POST/PUT/PATCH).
     */
    private function resolveBodyType(NormalizedOperation $operation): ?string
    {
        if (!$operation->acceptsBody || $operation->input === null) {
            return null;
        }

        return $this->namingStrategy->generateInputTypeNameForOperation($operation);
    }

    /**
     * Resolves the return type for the operation.
     */
    private function resolveReturnType(NormalizedOperation $operation): string
    {
        if (!$operation->returnsBody) {
            return 'void';
        }

        return $this->namingStrategy->generateOutputTypeNameForOperation($operation);
    }

    /**
     * Generates TypeScript function parameters string.
     *
     * Example outputs:
     * - "(id: number)"
     * - "(id: number, data: ProgramCreateInput)"
     * - "(query?: ProgramQuery)"
     * - "(id: number, query?: ProgramQuery)"
     */
    public function generateParametersString(OperationSignature $signature): string
    {
        $params = [];

        // Add path params
        foreach ($signature->pathParams as $pathParam) {
            $optional = $pathParam->isOptional ? '?' : '';
            $params[] = "{$pathParam->name}{$optional}: {$pathParam->type}";
        }

        // Add body param
        if ($signature->inputTypeName !== null) {
            $params[] = "data: {$signature->inputTypeName}";
        }

        // Add query param (always optional)
        if ($signature->queryTypeName !== null) {
            $params[] = "query?: {$signature->queryTypeName}";
        }

        return '(' . implode(', ', $params) . ')';
    }

    /**
     * Generates TypeScript function signature as string.
     *
     * Example: "getProgram(id: number): Promise<ProgramDetail>"
     */
    public function generateSignatureString(OperationSignature $signature): string
    {
        $params = $this->generateParametersString($signature);
        $returnType = $signature->returnsVoid ? 'Promise<void>' : "Promise<" . ($signature->isPaginated ? "HydraCollection<{$signature->outputTypeName}>" : $signature->outputTypeName) . ">";
        return "{$signature->functionName}{$params}: {$returnType}";
    }

    /**
     * Generates export function declaration.
     *
     * Example:
     * export async function getProgram(id: number): Promise<ProgramDetail> {
     *   // implementation
     * }
     */
    public function generateFunctionDeclaration(OperationSignature $signature, string $body): string
    {
        $params = $this->generateParametersString($signature);
        $returnType = $signature->returnsVoid ? 'Promise<void>' : "Promise<" . ($signature->isPaginated ? "HydraCollection<{$signature->outputTypeName}>" : $signature->outputTypeName) . ">";
        $declaration = "export async function {$signature->functionName}{$params}: {$returnType}";

        return "{$declaration} {\n{$body}\n}";
    }
}
