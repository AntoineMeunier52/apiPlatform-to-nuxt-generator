<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Paramètre de chemin d'URL
 */
readonly class PathParam
{
    public function __construct(
        /** Nom du paramètre (id, uuid, slug) */
        public string $name,
        /** Type TypeScript (number, string) */
        public string $tsType,
        /** Si le paramètre est optionnel */
        public bool $isOptional = false,
    ) {
        // Alias for backward compatibility
    }

    /**
     * Alias for tsType for backward compatibility
     */
    public function __get(string $name): mixed
    {
        if ($name === 'type') {
            return $this->tsType;
        }
        throw new \Error("Undefined property: PathParam::\$$name");
    }
}
