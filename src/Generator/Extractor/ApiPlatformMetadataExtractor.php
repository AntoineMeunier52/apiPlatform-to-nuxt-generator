<?php

declare(strict_types=1);

namespace App\Generator\Extractor;

use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * Service d'extraction des métadonnées API Platform brutes
 */
readonly class ApiPlatformMetadataExtractor
{
    public function __construct(
        private ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        private PropertyMetadataFactoryInterface $propertyMetadataFactory,
    ) {}

    /**
     * Retourne la liste de toutes les classes de ressources API Platform
     *
     * @return string[]
     */
    public function getResourceClasses(): array
    {
        $classes = [];
        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            $classes[] = $resourceClass;
        }

        return $classes;
    }

    /**
     * Retourne les métadonnées d'une ressource
     */
    public function getResourceMetadata(string $resourceClass): ResourceMetadataCollection
    {
        return $this->resourceMetadataCollectionFactory->create($resourceClass);
    }

    /**
     * Retourne les noms des propriétés d'une ressource
     *
     * @return string[]
     */
    public function getPropertyNames(string $resourceClass): array
    {
        $names = [];
        foreach ($this->propertyNameCollectionFactory->create($resourceClass) as $propertyName) {
            $names[] = $propertyName;
        }

        return $names;
    }

    /**
     * Retourne les métadonnées d'une propriété
     */
    public function getPropertyMetadata(string $resourceClass, string $propertyName): \ApiPlatform\Metadata\ApiProperty
    {
        return $this->propertyMetadataFactory->create($resourceClass, $propertyName);
    }

    /**
     * Vérifie si une classe est une ressource API Platform
     */
    public function isResource(string $className): bool
    {
        try {
            $this->resourceMetadataCollectionFactory->create($className);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Retourne le nom court d'une ressource
     */
    public function getShortName(string $resourceClass): string
    {
        $metadata = $this->resourceMetadataCollectionFactory->create($resourceClass);

        foreach ($metadata as $resource) {
            $shortName = $resource->getShortName();
            if ($shortName !== null) {
                return $shortName;
            }
        }

        // Fallback: utiliser le nom de classe
        $parts = explode('\\', $resourceClass);

        return array_pop($parts);
    }

    /**
     * Extracts resource metadata for a given resource class.
     * Returns null if the resource has no metadata.
     */
    public function extractResourceMetadata(string $resourceClass): ?ResourceMetadataCollection
    {
        try {
            $metadata = $this->resourceMetadataCollectionFactory->create($resourceClass);

            if ($metadata->count() === 0) {
                return null;
            }

            return $metadata;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Extracts all operations for a given resource class.
     *
     * @return \ApiPlatform\Metadata\Operation[]
     */
    public function extractOperations(string $resourceClass): array
    {
        try {
            $metadata = $this->resourceMetadataCollectionFactory->create($resourceClass);
            $operations = [];

            foreach ($metadata as $resourceMetadata) {
                foreach ($resourceMetadata->getOperations() as $operation) {
                    $operations[] = $operation;
                }
            }

            return $operations;
        } catch (\Exception) {
            return [];
        }
    }
}
