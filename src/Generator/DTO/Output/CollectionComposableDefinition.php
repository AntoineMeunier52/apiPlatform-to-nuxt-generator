<?php

declare(strict_types=1);

namespace App\Generator\DTO\Output;

/**
 * Definition for a collection composable (use<Resource>Collection).
 */
readonly class CollectionComposableDefinition
{
    public function __construct(
        /** Composable name (e.g., useProgramCollection) */
        public string $composableName,
        /** Resource short name (e.g., Program) */
        public string $resourceName,
        /** Resource plural name, lowercased and properly pluralized (e.g., programs, categories) */
        public string $resourcePluralName,
        /** Query type name (e.g., ProgramQuery) - resolved from QueryTypeResolver */
        public ?string $queryTypeName,
        /** List type name (e.g., ProgramList) */
        public string $listTypeName,
        /** Collection API function name (e.g., getPrograms) */
        public string $collectionFunctionName,
        /** Whether DELETE operation exists */
        public bool $hasDeleteOperation,
        /** Delete API function name (e.g., deleteProgram) */
        public ?string $deleteFunctionName,
        /** Default items per page from config */
        public int $defaultItemsPerPage,
    ) {}
}
