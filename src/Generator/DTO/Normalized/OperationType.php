<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Types d'opérations API Platform
 */
enum OperationType: string
{
    case GET_COLLECTION = 'GetCollection';
    case GET = 'Get';
    case POST = 'Post';
    case PATCH = 'Patch';
    case PUT = 'Put';
    case DELETE = 'Delete';
    case CUSTOM = 'Custom';

    /**
     * Vérifie si l'opération accepte un body
     */
    public function acceptsBody(): bool
    {
        return match ($this) {
            self::POST, self::PATCH, self::PUT, self::CUSTOM => true,
            default => false,
        };
    }

    /**
     * Vérifie si l'opération retourne un body
     */
    public function returnsBody(): bool
    {
        return $this !== self::DELETE;
    }

    /**
     * Vérifie si c'est une mise à jour partielle (PATCH)
     */
    public function isPartialUpdate(): bool
    {
        return $this === self::PATCH;
    }

    /**
     * Vérifie si c'est un remplacement complet (PUT)
     */
    public function isFullReplace(): bool
    {
        return $this === self::PUT;
    }

    /**
     * Vérifie si c'est une opération de lecture
     */
    public function isReadOperation(): bool
    {
        return $this === self::GET || $this === self::GET_COLLECTION;
    }

    /**
     * Vérifie si c'est une opération de collection
     */
    public function isCollectionOperation(): bool
    {
        return $this === self::GET_COLLECTION;
    }

    /**
     * Détermine le statut HTTP de succès par défaut
     */
    public function getDefaultSuccessStatus(): int
    {
        return match ($this) {
            self::POST => 201,
            self::DELETE => 204,
            default => 200,
        };
    }
}
