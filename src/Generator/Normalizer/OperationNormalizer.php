<?php

declare(strict_types=1);

namespace App\Generator\Normalizer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use App\Generator\DTO\Normalized\NormalizedOperation;
use App\Generator\DTO\Normalized\OperationType;
use App\Generator\DTO\Normalized\OperationContext;
use App\Generator\Extractor\IdentifierMetadataExtractor;

/**
 * Normalizes API Platform operations into NormalizedOperation DTOs.
 *
 * This is a critical component that analyzes each operation to determine:
 * - Operation type (GET_COLLECTION, GET_ITEM, POST, PUT, PATCH, DELETE, CUSTOM)
 * - HTTP method and URI template
 * - Input/output classes and contexts
 * - Whether it accepts/returns a body
 * - Whether it's paginated
 */
readonly class OperationNormalizer
{
    public function __construct(
        private IdentifierMetadataExtractor $identifierExtractor,
        private SerializationContextAnalyzer $contextAnalyzer,
        private FilterNormalizer $filterNormalizer,
    ) {}

    /**
     * Normalizes a single operation.
     */
    public function normalizeOperation(Operation $operation, string $resourceClass): NormalizedOperation
    {
        if (!$operation instanceof HttpOperation) {
            throw new \InvalidArgumentException('Only HTTP operations are supported');
        }

        $operationType = $this->detectOperationType($operation);
        $isCollection = $operation instanceof CollectionOperationInterface;

        // Extract contexts
        $normalizationContextRaw = $operation->getNormalizationContext() ?? [];
        $denormalizationContextRaw = $operation->getDenormalizationContext() ?? [];

        // Normalize contexts
        $normalizationContext = $this->contextAnalyzer->normalizeContext($normalizationContextRaw);
        $denormalizationContext = $this->contextAnalyzer->normalizeContext($denormalizationContextRaw);

        // Determine input/output classes
        $inputData = $operation->getInput();
        $outputData = $operation->getOutput();

        $inputClass = $inputData['class'] ?? null;
        $outputClass = $outputData['class'] ?? null;

        // Treat empty strings as null
        if ($inputClass === '' || $inputClass === null) {
            $inputClass = null;
        }
        if ($outputClass === '' || $outputClass === null) {
            $outputClass = null;
        }

        // Additional validation - ensure class actually exists if not null
        if ($inputClass !== null && !class_exists($inputClass) && !interface_exists($inputClass)) {
            error_log("Invalid input class '$inputClass' for operation {$operation->getName()}");
            $inputClass = null;
        }
        if ($outputClass !== null && !class_exists($outputClass) && !interface_exists($outputClass)) {
            error_log("Invalid output class '$outputClass' for operation {$operation->getName()}");
            $outputClass = null;
        }

        // Detect explicit output: false (API Platform stores it as ['class' => null], not null).
        // This distinguishes "output: false" from "no output: specified" (where getOutput() === null).
        $outputExplicitlyDisabled = $outputData !== null && array_key_exists('class', $outputData) && $outputData['class'] === null;

        // If no explicit input/output, use the resource class.
        // Exception: user-defined operations (name not auto-generated) without an explicit
        // input: attribute are signal-only actions (e.g. /complete, /cancel) that should
        // have no body, even if they inherit denormalizationContext from the resource level.
        // Developers must use `input: EntityClass::class` explicitly to enable body on user-defined ops.
        if ($inputClass === null && $this->acceptsBody($operationType)) {
            $isUserDefinedOperation = !str_starts_with($operation->getName() ?? '', '_api_');
            $hasExplicitInput = $inputData !== null; // inputData is non-null only with explicit input:
            if (!$isUserDefinedOperation || $hasExplicitInput) {
                $inputClass = $resourceClass;
            }
        }
        if ($outputClass === null && !$outputExplicitlyDisabled && $this->returnsBody($operationType)) {
            $outputClass = $resourceClass;
        }

        // Create operation contexts
        // Debug logging
        if ($outputClass === '' || $outputClass === null) {
            error_log("Operation {$operation->getName()}: outputClass is empty or null, using resourceClass: $resourceClass");
        }

        $inputContext = $inputClass !== null && $inputClass !== ''
            ? new OperationContext(
                class: $inputClass,
                groups: $this->contextAnalyzer->extractDenormalizationGroups($denormalizationContext),
            )
            : null;

        $outputContext = $outputClass !== null && $outputClass !== ''
            ? new OperationContext(
                class: $outputClass,
                groups: $this->contextAnalyzer->extractNormalizationGroups($normalizationContext),
            )
            : null;

        // Extract URI variables (path parameters)
        $uriVariables = $this->extractUriVariables($operation, $resourceClass);

        // Determine if custom operation
        $isCustom = $this->isCustomOperation($operation, $operationType);
        $customOperationName = $isCustom ? $this->extractCustomOperationName($operation) : null;

        return new NormalizedOperation(
            name: $operation->getName() ?? 'unknown',
            class: $resourceClass,
            operationType: $operationType,
            httpMethod: strtoupper($operation->getMethod() ?? 'GET'),
            uriTemplate: $operation->getUriTemplate() ?? '',
            uriVariables: $uriVariables,
            isCollection: $isCollection,
            isPaginated: $this->isPaginated($operation, $isCollection),
            acceptsBody: $this->acceptsBody($operationType),
            returnsBody: !$outputExplicitlyDisabled && $this->returnsBody($operationType),
            successStatus: $this->getSuccessStatus($operationType),
            inputClass: $inputClass,
            outputClass: $outputClass,
            input: $inputContext,
            output: $outputContext,
            normalizationContextRaw: $normalizationContextRaw,
            denormalizationContextRaw: $denormalizationContextRaw,
            filters: $this->filterNormalizer->normalizeFilters($operation),
            isCustom: $isCustom,
            customOperationName: $customOperationName,
        );
    }

    /**
     * Detects the operation type from the API Platform operation.
     */
    private function detectOperationType(HttpOperation $operation): OperationType
    {
        $method = strtoupper($operation->getMethod() ?? 'GET');
        $isCollection = $operation instanceof CollectionOperationInterface;

        // Standard CRUD operations
        if ($method === 'GET' && $isCollection) {
            return OperationType::GET_COLLECTION;
        }

        if ($method === 'GET' && !$isCollection) {
            return OperationType::GET;
        }

        if ($method === 'POST') {
            return OperationType::POST;
        }

        if ($method === 'PUT') {
            return OperationType::PUT;
        }

        if ($method === 'PATCH') {
            return OperationType::PATCH;
        }

        if ($method === 'DELETE') {
            return OperationType::DELETE;
        }

        // Custom operation
        return OperationType::CUSTOM;
    }

    /**
     * Determines if an operation is a custom operation (not standard CRUD).
     */
    private function isCustomOperation(HttpOperation $operation, OperationType $operationType): bool
    {
        // Only CUSTOM operation type is considered custom
        // We don't check operation names because API Platform generates internal names
        // like "_api_/users{._format}_get_collection" for standard operations
        return $operationType === OperationType::CUSTOM;
    }

    /**
     * Extracts custom operation name from operation metadata.
     */
    private function extractCustomOperationName(HttpOperation $operation): ?string
    {
        $name = $operation->getName();

        if ($name === null) {
            return null;
        }

        // Remove standard prefixes if any
        $name = preg_replace('/^(api_|_api)/', '', $name);

        return $name;
    }

    /**
     * Extracts URI variables (path parameters) from the operation.
     *
     * @return array<string, array{class: string, type: string}>
     */
    private function extractUriVariables(HttpOperation $operation, string $resourceClass): array
    {
        $uriVariables = $operation->getUriVariables() ?? [];
        $normalized = [];

        foreach ($uriVariables as $parameterName => $link) {
            // Get identifier metadata
            $identifiers = $this->identifierExtractor->extract($resourceClass);
            $identifier = $identifiers[0] ?? null;

            if ($identifier === null) {
                // Fallback to string type
                $normalized[$parameterName] = [
                    'class' => $resourceClass,
                    'type' => 'string',
                ];
                continue;
            }

            $normalized[$parameterName] = [
                'class' => $resourceClass,
                'type' => $identifier->phpType,
            ];
        }

        return $normalized;
    }

    /**
     * Determines if operation is paginated.
     */
    private function isPaginated(HttpOperation $operation, bool $isCollection): bool
    {
        if (!$isCollection) {
            return false;
        }

        // Check if pagination is explicitly disabled
        $paginationEnabled = $operation->getPaginationEnabled();

        if ($paginationEnabled === false) {
            return false;
        }

        // By default, collections are paginated in API Platform
        return true;
    }

    /**
     * Determines if operation accepts a request body.
     */
    private function acceptsBody(OperationType $operationType): bool
    {
        return match ($operationType) {
            OperationType::POST,
            OperationType::PUT,
            OperationType::PATCH => true,
            default => false,
        };
    }

    /**
     * Determines if operation returns a response body.
     */
    private function returnsBody(OperationType $operationType): bool
    {
        return match ($operationType) {
            OperationType::DELETE => false,
            default => true,
        };
    }

    /**
     * Gets the expected success HTTP status code for the operation.
     */
    private function getSuccessStatus(OperationType $operationType): int
    {
        return match ($operationType) {
            OperationType::POST => 201,
            OperationType::DELETE => 204,
            default => 200,
        };
    }
}
