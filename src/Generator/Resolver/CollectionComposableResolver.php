<?php

declare(strict_types=1);

namespace App\Generator\Resolver;

use App\Generator\Config\GeneratorConfiguration;
use App\Generator\DTO\Normalized\NormalizedOperation;
use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\DTO\Normalized\OperationType;
use App\Generator\DTO\Output\CollectionComposableDefinition;
use App\Generator\Naming\NamingStrategy;

/**
 * Resolves collection composable definitions from resources.
 */
readonly class CollectionComposableResolver
{
    public function __construct(
        private NamingStrategy $namingStrategy,
        private QueryTypeResolver $queryTypeResolver,
        private GeneratorConfiguration $config,
    ) {}

    /**
     * Resolves collection composable definition for a resource.
     * Returns null if no GET_COLLECTION operation exists.
     */
    public function resolve(NormalizedResource $resource): ?CollectionComposableDefinition
    {
        $collectionOp = $this->findCollectionOperation($resource);
        if ($collectionOp === null) {
            return null;
        }

        $deleteOp = $this->findDeleteOperation($resource);

        // Use QueryTypeResolver to get query type name (consistent naming)
        $queryTypeDefinition = $this->queryTypeResolver->resolveQueryTypeDefinition($collectionOp);
        $queryTypeName = $queryTypeDefinition?->typeName;

        return new CollectionComposableDefinition(
            composableName: "use{$resource->shortName}Collection",
            resourceName: $resource->shortName,
            resourcePluralName: strtolower($this->namingStrategy->pluralize($resource->shortName)),
            queryTypeName: $queryTypeName,
            listTypeName: $this->namingStrategy->generateOutputTypeName(
                $resource->className,
                $collectionOp->operationType,
                true
            ),
            collectionFunctionName: $this->namingStrategy->generateFunctionName($collectionOp),
            hasDeleteOperation: $deleteOp !== null,
            deleteFunctionName: $deleteOp ? $this->namingStrategy->generateFunctionName($deleteOp) : null,
            defaultItemsPerPage: $this->config->defaultItemsPerPage,
        );
    }

    /**
     * Finds the GET_COLLECTION operation.
     */
    private function findCollectionOperation(NormalizedResource $resource): ?NormalizedOperation
    {
        foreach ($resource->operations as $operation) {
            if ($operation->operationType === OperationType::GET_COLLECTION) {
                return $operation;
            }
        }

        return null;
    }

    /**
     * Finds the DELETE operation.
     */
    private function findDeleteOperation(NormalizedResource $resource): ?NormalizedOperation
    {
        foreach ($resource->operations as $operation) {
            if ($operation->operationType === OperationType::DELETE) {
                return $operation;
            }
        }

        return null;
    }
}
