<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Contexte de sérialisation/désérialisation pour une opération
 */
readonly class OperationContext
{
    /**
     * @param string[] $groups Groupes de sérialisation
     * @param NormalizedProperty[] $properties Propriétés effectives pour ce contexte
     */
    public function __construct(
        /** Groupes de sérialisation */
        public array $groups,
        /** Classe DTO si spécifiée (pour input/output custom) */
        public ?string $class = null,
        /** Propriétés effectives pour ce contexte */
        public array $properties = [],
    ) {}

    /**
     * Vérifie si un DTO custom est utilisé
     */
    public function hasCustomClass(): bool
    {
        return $this->class !== null;
    }

    /**
     * Filtre les propriétés par groupe
     *
     * @return NormalizedProperty[]
     */
    public function getPropertiesForGroups(): array
    {
        if (empty($this->groups)) {
            return $this->properties;
        }

        return array_filter(
            $this->properties,
            fn (NormalizedProperty $prop) => $prop->belongsToAnyGroup($this->groups)
        );
    }
}
