<?php

declare(strict_types=1);

namespace App\Generator\Extractor;

use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use App\Generator\DTO\Normalized\NormalizedIdentifier;
use App\Generator\Resolver\TypeMapper;

/**
 * Service d'extraction des identifiants d'une ressource
 */
readonly class IdentifierMetadataExtractor
{
    public function __construct(
        private PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        private PropertyMetadataFactoryInterface $propertyMetadataFactory,
        private TypeMapper $typeMapper,
    ) {}

    /**
     * Extrait les identifiants d'une ressource
     *
     * @return NormalizedIdentifier[]
     */
    public function extract(string $resourceClass): array
    {
        $identifiers = [];

        foreach ($this->propertyNameCollectionFactory->create($resourceClass) as $propertyName) {
            $metadata = $this->propertyMetadataFactory->create($resourceClass, $propertyName);

            if ($metadata->isIdentifier()) {
                $nativeType = $metadata->getNativeType();
                $phpType = $this->extractPhpType($nativeType);
                $tsType = $this->typeMapper->mapPhpTypeToTypeScript($phpType);

                $identifiers[] = new NormalizedIdentifier(
                    name: $propertyName,
                    phpType: $phpType,
                    tsType: $tsType,
                );
            }
        }

        // Si aucun identifiant explicite, chercher 'id'
        if (empty($identifiers)) {
            $identifiers = $this->findDefaultIdentifier($resourceClass);
        }

        return $identifiers;
    }

    /**
     * Trouve l'identifiant par défaut (id)
     *
     * @return NormalizedIdentifier[]
     */
    private function findDefaultIdentifier(string $resourceClass): array
    {
        try {
            $metadata = $this->propertyMetadataFactory->create($resourceClass, 'id');
            $nativeType = $metadata->getNativeType();
            $phpType = $this->extractPhpType($nativeType);

            return [
                new NormalizedIdentifier(
                    name: 'id',
                    phpType: $phpType,
                    tsType: $this->typeMapper->mapPhpTypeToTypeScript($phpType),
                ),
            ];
        } catch (\Exception) {
            // Pas de propriété id
            return [];
        }
    }

    /**
     * Extrait le type PHP depuis un type natif Symfony
     */
    private function extractPhpType(mixed $nativeType): string
    {
        if ($nativeType === null) {
            return 'mixed';
        }

        if (\is_string($nativeType)) {
            return $nativeType;
        }

        // Symfony TypeInfo Type
        if (\is_object($nativeType) && method_exists($nativeType, '__toString')) {
            return (string) $nativeType;
        }

        if (\is_object($nativeType) && method_exists($nativeType, 'getBuiltinType')) {
            return $nativeType->getBuiltinType();
        }

        return 'mixed';
    }
}
