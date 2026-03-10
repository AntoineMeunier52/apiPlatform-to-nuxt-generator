<?php

declare(strict_types=1);

namespace App\Generator\Resolver;

use App\Generator\DTO\Normalized\NormalizedOperation;
use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\DTO\Normalized\OperationType;
use App\Generator\DTO\Output\ResourceComposableDefinition;
use App\Generator\Naming\NamingStrategy;

/**
 * Resolves resource item composable definitions from resources.
 */
readonly class ResourceComposableResolver
{
    public function __construct(
        private NamingStrategy $namingStrategy,
    ) {}

    /**
     * Resolves resource composable definition for a resource.
     * Returns null if no relevant operations exist (GET, POST, PATCH, PUT, DELETE).
     */
    public function resolve(NormalizedResource $resource): ?ResourceComposableDefinition
    {
        $operations = $this->categorizeOperations($resource);

        if (empty($operations)) {
            return null;
        }

        return new ResourceComposableDefinition(
            composableName: "use{$resource->shortName}Resource",
            resourceName: $resource->shortName,
            detailTypeName: $this->namingStrategy->generateOutputTypeName(
                $resource->className,
                OperationType::GET,
                false
            ),
            hasGet: isset($operations['get']),
            hasPost: isset($operations['post']),
            hasPatch: isset($operations['patch']),
            hasPut: isset($operations['put']),
            hasDelete: isset($operations['delete']),
            createInputType: isset($operations['post'])
                ? $this->namingStrategy->generateInputTypeName($resource->className, OperationType::POST)
                : null,
            updateInputType: isset($operations['patch'])
                ? $this->namingStrategy->generateInputTypeName($resource->className, OperationType::PATCH)
                : null,
            replaceInputType: isset($operations['put'])
                ? $this->namingStrategy->generateInputTypeName($resource->className, OperationType::PUT)
                : null,
            functionNames: array_map(
                fn($op) => $this->namingStrategy->generateFunctionName($op),
                $operations
            ),
            identifierName: $resource->getPrimaryIdentifier()?->name ?? 'id',
        );
    }

    /**
     * Categorizes operations by type, preferring canonical (standard CRUD) over custom ones.
     *
     * When a resource has multiple operations of the same HTTP method (e.g. two POSTs,
     * one canonical and one with a custom URI), the canonical one is preferred.
     * Standard operations have auto-generated names starting with '_api_'.
     *
     * @return array<string, NormalizedOperation>
     */
    private function categorizeOperations(NormalizedResource $resource): array
    {
        $operations = [];

        foreach ($resource->operations as $operation) {
            $key = match ($operation->operationType) {
                OperationType::GET => 'get',
                OperationType::POST => 'post',
                OperationType::PATCH => 'patch',
                OperationType::PUT => 'put',
                OperationType::DELETE => 'delete',
                default => null,
            };

            if ($key === null) {
                continue;
            }

            $isCanonical = str_starts_with($operation->name, '_api_');
            $slotEmpty = !isset($operations[$key]);
            $slotHasNonCanonical = isset($operations[$key])
                && !str_starts_with($operations[$key]->name, '_api_');

            // Fill empty slot, or upgrade non-canonical slot to canonical
            if ($slotEmpty || ($isCanonical && $slotHasNonCanonical)) {
                $operations[$key] = $operation;
            }
        }

        return $operations;
    }
}
