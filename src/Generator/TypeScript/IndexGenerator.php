<?php

declare(strict_types=1);

namespace App\Generator\TypeScript;

use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\Writer\FileWriter;
use App\Generator\Config\GeneratorConfiguration;

/**
 * Generates index.ts files for convenient imports.
 *
 * Creates barrel exports:
 * - types/index.ts - Exports all type definitions
 * - queries/index.ts - Exports all query types
 * - api/index.ts - Exports all API functions
 * - composables/index.ts - Exports all composables
 * - index.ts - Main entry point
 */
class IndexGenerator extends CoreGenerator
{
    /**
     * Generates all index files.
     *
     * @param NormalizedResource[] $resources
     */
    public function generate(mixed $data = null): void
    {
        $resources = $data ?? [];

        $this->generateTypesIndex($resources);
        $this->generateQueriesIndex($resources);
        $this->generateApiIndex($resources);
        $this->generateComposablesIndex($resources);
        $this->generateCoreIndex();
        $this->generateMainIndex();
    }

    /**
     * Generates types/index.ts
     */
    private function generateTypesIndex(array $resources): void
    {
        $content = $this->generateFileHeader('Type definitions index');

        foreach ($resources as $resource) {
            $content .= "export * from './{$resource->shortName}'\n";
        }

        $this->write('types/index.ts', $content);
    }

    /**
     * Generates queries/index.ts
     */
    private function generateQueriesIndex(array $resources): void
    {
        $content = $this->generateFileHeader('Query types index');

        foreach ($resources as $resource) {
            // Only export if the resource has query types
            if ($this->hasQueryTypes($resource)) {
                $content .= "export * from './{$resource->shortName}'\n";
            }
        }

        $this->write('queries/index.ts', $content);
    }

    /**
     * Generates api/index.ts
     */
    private function generateApiIndex(array $resources): void
    {
        $content = $this->generateFileHeader('API functions index');

        foreach ($resources as $resource) {
            $filename = strtolower($resource->shortName);
            $content .= "export * from './{$filename}'\n";
        }

        $this->write('api/index.ts', $content);
    }

    /**
     * Generates composables/index.ts
     *
     * @param NormalizedResource[] $resources
     */
    private function generateComposablesIndex(array $resources = []): void
    {
        $content = $this->generateFileHeader('Composables index');

        $content .= "export * from './useSave'\n";
        $content .= "export * from './useApiError'\n";

        // Export resource-level composables
        foreach ($resources as $resource) {
            $content .= "export * from './use{$resource->shortName}Collection'\n";
            $content .= "export * from './use{$resource->shortName}Resource'\n";
        }

        $this->write('composables/index.ts', $content);
    }

    /**
     * Generates core/index.ts
     */
    private function generateCoreIndex(): void
    {
        $content = $this->generateFileHeader('Core utilities index');

        $content .= "export * from './types'\n";
        $content .= "export * from './fetcher'\n";
        $content .= "export * from './hydra'\n";
        $content .= "export * from './apiError'\n";

        $this->write('core/index.ts', $content);
    }

    /**
     * Generates main index.ts at root
     */
    private function generateMainIndex(): void
    {
        $content = $this->generateFileHeader('Main entry point for generated API client');

        $content .= <<<'TS'
// Export all types
export * from './types'

// Export all query types
export * from './queries'

// Export all API functions
export * from './api'

// Export all composables
export * from './composables'

// Export core utilities
export * from './core'

TS;

        $this->write('index.ts', $content);
    }

    /**
     * Checks if a resource has query types.
     */
    private function hasQueryTypes(NormalizedResource $resource): bool
    {
        foreach ($resource->operations as $operation) {
            if (!empty($operation->filters) || $operation->isPaginated) {
                return true;
            }
        }

        return false;
    }
}
