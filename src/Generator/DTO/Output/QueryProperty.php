<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Propriété d'un type de query
 */
readonly class QueryProperty
{
    public function __construct(
        /** Nom du paramètre (email, email[], order[name]) */
        public string $name,
        /** Type TypeScript (string, string[], 'asc' | 'desc') */
        public string $tsType,
        /** Si le paramètre est optionnel */
        public bool $optional,
        /** Description JSDoc */
        public ?string $description = null,
    ) {}
}
