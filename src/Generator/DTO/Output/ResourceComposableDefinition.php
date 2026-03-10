<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Definition for a resource item composable (use<Resource>Resource).
 */
readonly class ResourceComposableDefinition
{
    /**
     * @param array<string, string> $functionNames Operation type => function name mapping
     */
    public function __construct(
        /** Composable name (e.g., useProgramResource) */
        public string $composableName,
        /** Resource short name (e.g., Program) */
        public string $resourceName,
        /** Detail type name (e.g., ProgramDetail) */
        public string $detailTypeName,
        /** Whether GET operation exists */
        public bool $hasGet,
        /** Whether POST operation exists */
        public bool $hasPost,
        /** Whether PATCH operation exists */
        public bool $hasPatch,
        /** Whether PUT operation exists */
        public bool $hasPut,
        /** Whether DELETE operation exists */
        public bool $hasDelete,
        /** Create input type name (e.g., ProgramCreateInput) */
        public ?string $createInputType,
        /** Update input type name (e.g., ProgramUpdateInput) */
        public ?string $updateInputType,
        /** Replace input type name (e.g., ProgramReplaceInput) */
        public ?string $replaceInputType,
        /** Function names per operation */
        public array $functionNames,
        /** Primary identifier field name (e.g., id, uuid) */
        public string $identifierName,
    ) {}
}
