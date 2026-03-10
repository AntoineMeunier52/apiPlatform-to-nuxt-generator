<?php

declare(strict_types=1);

namespace App\Generator\DTO\Normalized;

/**
 * Identifiant normalisé d'une ressource
 */
readonly class NormalizedIdentifier
{
    public function __construct(
        /** Nom de l'identifiant (id, uuid, slug) */
        public string $name,
        /** Type PHP (int, string, Uuid) */
        public string $phpType,
        /** Type TypeScript (number, string) */
        public string $tsType,
    ) {}
}
