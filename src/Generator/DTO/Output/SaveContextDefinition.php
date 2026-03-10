<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Definition for enhanced useSave context.
 */
readonly class SaveContextDefinition
{
    /**
     * @param string[] $operations Available operations (create, update, replace)
     */
    public function __construct(
        /** Available operations for this save composable */
        public array $operations,
    ) {}
}
