<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Filtre normalisé pour une opération de collection
 */
readonly class NormalizedFilter
{
    /**
     * @param string[] $allowedValues Valeurs autorisées (pour OrderFilter: ['asc', 'desc'])
     */
    public function __construct(
        /** Nom du filtre (email, name, createdAt) */
        public string $name,
        /** Classe du filtre (SearchFilter::class) */
        public string $filterClass,
        /** Propriété filtrée */
        public string $property,
        /** Stratégie (exact, partial, start, end, word_start) */
        public string $strategy,
        /** Type de filtre */
        public FilterType $type,
        /** Type TypeScript */
        public string $tsType,
        /** Si le filtre accepte un tableau (email[]) */
        public bool $isArray,
        /** Valeurs autorisées */
        public array $allowedValues = [],
    ) {}

    /**
     * Retourne le nom du paramètre de query string
     */
    public function getQueryParamName(): string
    {
        if ($this->isArray) {
            return $this->name . '[]';
        }

        return $this->name;
    }

    /**
     * Retourne le type TypeScript complet (avec tableau si applicable)
     */
    public function getFullTsType(): string
    {
        if ($this->isArray) {
            return $this->tsType . '[]';
        }

        return $this->tsType;
    }
}
