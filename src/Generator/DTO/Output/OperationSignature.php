<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Signature complète d'une fonction API générée
 */
readonly class OperationSignature
{
    /**
     * @param PathParam[] $pathParams
     */
    public function __construct(
        /** Nom de la fonction (listUsers, getUser, createUser) */
        public string $functionName,
        /** Méthode HTTP (GET, POST, PATCH, PUT, DELETE) */
        public string $httpMethod,
        /** Chemin de l'API (/api/users ou /api/users/{id}) */
        public string $path,
        /** Paramètres de chemin */
        public array $pathParams,
        /** Nom du type de query (UserCollectionQuery | null) */
        public ?string $queryTypeName,
        /** Nom du type d'entrée (UserCreateInput | null) */
        public ?string $inputTypeName,
        /** Nom du type de sortie (User | UserList | void) */
        public string $outputTypeName,
        /** Si c'est une opération de collection */
        public bool $isCollection,
        /** Si l'opération est paginée */
        public bool $isPaginated,
        /** Si l'opération retourne void */
        public bool $returnsVoid,
        /** Description JSDoc */
        public ?string $description = null,
        /** Nom de l'opération API Platform source */
        public ?string $sourceOperation = null,
    ) {}

    /**
     * Vérifie si l'opération a des paramètres de chemin
     */
    public function hasPathParams(): bool
    {
        return !empty($this->pathParams);
    }

    /**
     * Vérifie si l'opération a un type de query
     */
    public function hasQueryType(): bool
    {
        return $this->queryTypeName !== null;
    }

    /**
     * Vérifie si l'opération a un type d'entrée
     */
    public function hasInputType(): bool
    {
        return $this->inputTypeName !== null;
    }

    /**
     * Retourne le chemin avec les variables résolues pour le template
     */
    public function getPathTemplate(): string
    {
        // Convertir {id} en ${id} pour les template literals
        return preg_replace('/\{(\w+)\}/', '\${$1}', $this->path) ?? $this->path;
    }

    /**
     * Vérifie si le chemin est dynamique (contient des variables)
     */
    public function hasDynamicPath(): bool
    {
        return str_contains($this->path, '{');
    }
}
