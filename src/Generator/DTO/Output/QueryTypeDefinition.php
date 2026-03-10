<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Définition d'un type de query pour une collection
 */
readonly class QueryTypeDefinition
{
    /**
     * @param QueryProperty[] $properties
     */
    public function __construct(
        /** Nom du type (UserCollectionQuery) */
        public string $typeName,
        /** Propriétés du type */
        public array $properties,
    ) {}

    /**
     * Vérifie si le type a des propriétés
     */
    public function hasProperties(): bool
    {
        return !empty($this->properties);
    }
}
