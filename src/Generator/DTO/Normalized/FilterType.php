<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Types de filtres API Platform
 */
enum FilterType: string
{
    case SEARCH = 'search';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case RANGE = 'range';
    case ORDER = 'order';
    case EXISTS = 'exists';
    case NUMERIC = 'numeric';

    /**
     * Retourne le type TypeScript par défaut pour ce filtre
     */
    public function getDefaultTsType(): string
    {
        return match ($this) {
            self::SEARCH => 'string',
            self::BOOLEAN => 'boolean',
            self::DATE => 'string',
            self::RANGE => 'number',
            self::ORDER => "'asc' | 'desc'",
            self::EXISTS => 'boolean',
            self::NUMERIC => 'number',
        };
    }

    /**
     * Vérifie si le filtre supporte les valeurs multiples
     */
    public function supportsArray(): bool
    {
        return match ($this) {
            self::SEARCH, self::NUMERIC => true,
            default => false,
        };
    }
}
