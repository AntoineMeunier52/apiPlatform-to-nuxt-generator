<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Opération normalisée d'une ressource API Platform
 *
 * Contient TOUTES les informations nécessaires pour générer le code TypeScript
 * sans avoir à retourner aux métadonnées API Platform brutes.
 */
readonly class NormalizedOperation
{
    /**
     * @param array<string, NormalizedIdentifier> $uriVariables Variables d'URI avec leurs types
     * @param array<string, mixed> $normalizationContextRaw Contexte de normalisation brut
     * @param array<string, mixed> $denormalizationContextRaw Contexte de dénormalisation brut
     * @param NormalizedFilter[] $filters Filtres pour les opérations de collection
     */
    public function __construct(
        // Identification
        /** Nom de l'opération (_api_users_get_collection ou custom) */
        public string $name,
        /** Classe de l'opération (GetCollection::class) */
        public string $class,
        /** Type d'opération */
        public OperationType $operationType,
        /** Méthode HTTP (GET, POST, PATCH, PUT, DELETE) */
        public string $httpMethod,

        // Routing
        /** Template d'URI (/api/users/{id}) */
        public string $uriTemplate,
        /** Variables d'URI avec leurs types */
        public array $uriVariables,

        // Behavior flags
        /** Si c'est une opération de collection */
        public bool $isCollection,
        /** Si l'opération supporte la pagination */
        public bool $isPaginated,
        /** Si l'opération accepte un body (POST, PATCH, PUT) */
        public bool $acceptsBody,
        /** Si l'opération retourne un body (pas DELETE) */
        public bool $returnsBody,
        /** Statut HTTP de succès (200, 201, 204) */
        public int $successStatus,

        // Input/Output classes
        /** Classe DTO d'entrée si spécifiée */
        public ?string $inputClass,
        /** Classe DTO de sortie si spécifiée */
        public ?string $outputClass,

        // Serialization contexts
        /** Contexte d'entrée (dénormalisation) */
        public ?OperationContext $input,
        /** Contexte de sortie (normalisation) */
        public ?OperationContext $output,
        /** Contexte de normalisation brut */
        public array $normalizationContextRaw,
        /** Contexte de dénormalisation brut */
        public array $denormalizationContextRaw,

        // Filters
        /** Filtres pour les opérations de collection */
        public array $filters,

        // Custom operation metadata
        /** Si c'est une opération custom */
        public bool $isCustom,
        /** Nom de l'opération custom (activate, ban, etc.) */
        public ?string $customOperationName,
    ) {}

    /**
     * Vérifie si l'opération a des paramètres d'URL
     */
    public function hasPathParams(): bool
    {
        return !empty($this->uriVariables);
    }

    /**
     * Vérifie si l'opération a des filtres
     */
    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }

    /**
     * Retourne les groupes de normalisation
     *
     * @return string[]
     */
    public function getNormalizationGroups(): array
    {
        return $this->output?->groups ?? $this->normalizationContextRaw['groups'] ?? [];
    }

    /**
     * Retourne les groupes de dénormalisation
     *
     * @return string[]
     */
    public function getDenormalizationGroups(): array
    {
        return $this->input?->groups ?? $this->denormalizationContextRaw['groups'] ?? [];
    }
}
