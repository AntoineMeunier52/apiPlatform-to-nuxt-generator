<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Relation normalisée entre ressources
 */
readonly class NormalizedRelation
{
    /**
     * @param string[] $serializationGroups Groupes utilisés pour sérialiser la relation
     */
    public function __construct(
        /** Nom court de la ressource cible (Category) */
        public string $targetResource,
        /** Nom complet de la classe cible (App\Entity\Category) */
        public string $targetClassName,
        /** Type de relation (ManyToOne, OneToMany, etc.) */
        public RelationType $type,
        /** Si la relation est une collection */
        public bool $isCollection,
        /** Si la relation est sérialisée comme objet (true) ou IRI (false) */
        public bool $isSerializedAsObject,
        /** Groupes de sérialisation pour cette relation */
        public array $serializationGroups = [],
    ) {}

    /**
     * Retourne le type TypeScript pour cette relation (IRI ou type imbriqué)
     */
    public function getTsType(string $nestedTypeName = null): string
    {
        if ($this->isSerializedAsObject && $nestedTypeName !== null) {
            return $this->isCollection ? "{$nestedTypeName}[]" : $nestedTypeName;
        }

        return $this->isCollection ? 'string[]' : 'string';
    }
}
