<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Propriété d'un type TypeScript généré
 */
readonly class TypeProperty
{
    public function __construct(
        /** Nom de la propriété */
        public string $name,
        /** Type TypeScript */
        public string $type,
        /** Si la propriété est optionnelle */
        public bool $isOptional,
        /** Si la propriété est un tableau */
        public bool $isArray = false,
        /** Description JSDoc */
        public ?string $description = null,
    ) {}

    /**
     * Retourne le type complet avec array
     */
    public function getFullType(): string
    {
        if ($this->isArray) {
            return $this->type . '[]';
        }

        return $this->type;
    }
}
