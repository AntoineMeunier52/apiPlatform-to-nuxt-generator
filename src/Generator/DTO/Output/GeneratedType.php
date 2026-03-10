<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Type TypeScript généré
 */
readonly class GeneratedType
{
    /**
     * @param TypeProperty[] $properties
     */
    public function __construct(
        /** Nom du type (User, UserList, UserCreateInput) */
        public string $name,
        /** Propriétés du type */
        public array $properties,
        /** Si c'est un type d'entrée (payload) */
        public bool $isInput = false,
        /** Si c'est une collection */
        public bool $isCollection = false,
        /** Description JSDoc */
        public ?string $description = null,
    ) {}

    /**
     * Vérifie si le type a des propriétés
     */
    public function hasProperties(): bool
    {
        return !empty($this->properties);
    }

    /**
     * Retourne une signature unique pour la déduplication
     */
    public function getSignature(): string
    {
        $parts = [];
        foreach ($this->properties as $property) {
            $parts[] = \sprintf(
                '%s:%s:%s:%s',
                $property->name,
                $property->type,
                $property->isOptional ? 'opt' : 'req',
                $property->isArray ? 'arr' : 'single'
            );
        }

        sort($parts);

        return implode('|', $parts);
    }
}
