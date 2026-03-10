<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Alias de type TypeScript (pour la déduplication)
 */
readonly class TypeAlias
{
    public function __construct(
        /** Nom de l'alias (UserDetail) */
        public string $aliasName,
        /** Nom du type canonique (User) */
        public string $targetName,
        /** Commentaire JSDoc */
        public ?string $comment = null,
    ) {}
}
