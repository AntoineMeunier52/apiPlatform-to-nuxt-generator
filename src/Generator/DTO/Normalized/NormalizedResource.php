<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Ressource normalisée contenant toutes les métadonnées nécessaires à la génération
 */
readonly class NormalizedResource
{
    /**
     * @param NormalizedOperation[] $operations Opérations de la ressource
     * @param NormalizedProperty[] $properties Propriétés de la ressource
     * @param NormalizedIdentifier[] $identifiers Identifiants (id, uuid, slug)
     * @param array<string, string[]> $groupsToProperties Mapping groupes → propriétés
     */
    public function __construct(
        /** Nom complet de la classe (App\Entity\User) */
        public string $className,
        /** Nom court (User) */
        public string $shortName,
        /** Nom pluriel (Users) */
        public string $pluralName,
        /** Chemin de base de l'API (/api/users) */
        public string $basePath,
        /** Opérations de la ressource */
        public array $operations,
        /** Propriétés de la ressource */
        public array $properties,
        /** Identifiants de la ressource */
        public array $identifiers,
        /** Mapping groupes → propriétés */
        public array $groupsToProperties = [],
    ) {}

    /**
     * Retourne l'identifiant principal (premier identifiant)
     */
    public function getPrimaryIdentifier(): ?NormalizedIdentifier
    {
        return $this->identifiers[0] ?? null;
    }

    /**
     * Retourne une opération par son type
     */
    public function getOperationByType(OperationType $type): ?NormalizedOperation
    {
        foreach ($this->operations as $operation) {
            if ($operation->operationType === $type) {
                return $operation;
            }
        }

        return null;
    }

    /**
     * Retourne les propriétés pour un groupe donné
     *
     * @return NormalizedProperty[]
     */
    public function getPropertiesForGroup(string $group): array
    {
        return array_filter(
            $this->properties,
            fn (NormalizedProperty $prop) => $prop->belongsToGroup($group)
        );
    }

    /**
     * Retourne les propriétés pour plusieurs groupes
     *
     * @param string[] $groups
     *
     * @return NormalizedProperty[]
     */
    public function getPropertiesForGroups(array $groups): array
    {
        if (empty($groups)) {
            return $this->properties;
        }

        return array_filter(
            $this->properties,
            fn (NormalizedProperty $prop) => $prop->belongsToAnyGroup($groups)
        );
    }

    /**
     * Retourne les propriétés lisibles
     *
     * @return NormalizedProperty[]
     */
    public function getReadableProperties(): array
    {
        return array_filter(
            $this->properties,
            fn (NormalizedProperty $prop) => $prop->readable
        );
    }

    /**
     * Retourne les propriétés écrivables
     *
     * @return NormalizedProperty[]
     */
    public function getWritableProperties(): array
    {
        return array_filter(
            $this->properties,
            fn (NormalizedProperty $prop) => $prop->writable
        );
    }

    /**
     * Vérifie si la ressource a des opérations de collection
     */
    public function hasCollectionOperations(): bool
    {
        foreach ($this->operations as $operation) {
            if ($operation->isCollection) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne le nom du dossier pour cette ressource (kebab-case)
     */
    public function getFolderName(): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $this->shortName) ?? $this->shortName);
    }
}
