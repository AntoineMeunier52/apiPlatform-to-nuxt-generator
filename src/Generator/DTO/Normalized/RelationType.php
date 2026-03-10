<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Types de relations Doctrine
 */
enum RelationType: string
{
    case MANY_TO_ONE = 'ManyToOne';
    case ONE_TO_MANY = 'OneToMany';
    case MANY_TO_MANY = 'ManyToMany';
    case ONE_TO_ONE = 'OneToOne';

    /**
     * Vérifie si la relation est une collection
     */
    public function isCollection(): bool
    {
        return $this === self::ONE_TO_MANY || $this === self::MANY_TO_MANY;
    }

    /**
     * Vérifie si c'est une relation vers un seul élément
     */
    public function isSingle(): bool
    {
        return $this === self::MANY_TO_ONE || $this === self::ONE_TO_ONE;
    }
}
