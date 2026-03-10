<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Propriété normalisée d'une ressource
 */
readonly class NormalizedProperty
{
    /**
     * @param string[] $groups Groupes de sérialisation où cette propriété apparaît
     */
    public function __construct(
        /** Nom de la propriété */
        public string $name,
        /** Type PHP (string, int, ?DateTimeInterface) */
        public string $phpType,
        /** Type TypeScript (string, number, string | null) */
        public string $tsType,
        /** Si la propriété est nullable */
        public bool $nullable,
        /** Si c'est un identifiant */
        public bool $identifier,
        /** Si la propriété est lisible (serialization) */
        public bool $readable,
        /** Si la propriété est écrivable (deserialization) */
        public bool $writable,
        /** Relation si c'est une propriété de relation */
        public ?NormalizedRelation $relation = null,
        /** Groupes de sérialisation */
        public array $groups = [],
    ) {}

    /**
     * Vérifie si c'est une relation
     */
    public function isRelation(): bool
    {
        return $this->relation !== null;
    }

    /**
     * Vérifie si la propriété appartient à un groupe donné
     */
    public function belongsToGroup(string $group): bool
    {
        return \in_array($group, $this->groups, true);
    }

    /**
     * Vérifie si la propriété appartient à au moins un des groupes donnés
     *
     * @param string[] $groups
     */
    public function belongsToAnyGroup(array $groups): bool
    {
        if (empty($groups)) {
            return true; // Pas de filtre de groupe
        }

        return !empty(array_intersect($this->groups, $groups));
    }
}
