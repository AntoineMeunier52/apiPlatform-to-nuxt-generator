<?php

declare(strict_types=1);

namespace App\Generator\TypeScript;

use App\Generator\Writer\FileWriter;
use App\Generator\Config\GeneratorConfiguration;
use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\DTO\Output\CollectionComposableDefinition;
use App\Generator\Resolver\CollectionComposableResolver;

/**
 * Generates resource collection composables (use<Resource>Collection).
 */
class ResourceCollectionComposableGenerator extends CoreGenerator
{
    public function __construct(
        FileWriter $fileWriter,
        GeneratorConfiguration $config,
        private readonly CollectionComposableResolver $resolver,
    ) {
        parent::__construct($fileWriter, $config);
    }

    public function generate(mixed $data = null): void
    {
        if (!$data instanceof NormalizedResource) {
            throw new \InvalidArgumentException('ResourceCollectionComposableGenerator expects NormalizedResource');
        }

        $definition = $this->resolver->resolve($data);

        if ($definition === null) {
            return; // No GET_COLLECTION operation, skip
        }

        $content = $this->generateFileHeader("Collection composable for {$definition->resourceName}");
        $content .= $this->generateCollectionComposable($definition);

        $fileName = "{$definition->composableName}.ts";
        $this->write("composables/{$fileName}", $content);
    }

    private function generateCollectionComposable(CollectionComposableDefinition $def): string
    {
        $queryTypeImport = $def->queryTypeName
            ? "import type { {$def->queryTypeName} } from '../queries/{$def->resourceName}'\n"
            : '';

        $listTypeImport = "import type { {$def->listTypeName} } from '../types/{$def->resourceName}'";

        $queryTypeDefinition = $def->queryTypeName ?? 'Record<string, unknown>';
        $defaultQuery = $def->queryTypeName
            ? "{ page: 1, itemsPerPage: {$def->defaultItemsPerPage} }"
            : "{}";

        $removeMethod = $def->hasDeleteOperation
            ? $this->generateRemoveMethod($def->deleteFunctionName)
            : '';

        // Interface property (type declaration) vs return object export (identifier)
        $removeInterfaceProp = $def->hasDeleteOperation
            ? "\n  /** Delete resource by ID */\n  remove: (id: number | string) => Promise<void>"
            : '';
        $removeReturnExport = $def->hasDeleteOperation ? "\n    remove," : '';

        $deleteImport = $def->hasDeleteOperation
            ? ", {$def->deleteFunctionName}"
            : '';

        $resourceNameLower = strtolower($def->resourceName);
        $resourcePluralName = $def->resourcePluralName;

        return <<<TS
import { ref, computed, type Ref, type ComputedRef } from 'vue'
{$queryTypeImport}{$listTypeImport}
import type { HydraCollection } from '../core/hydra'
import { formatApiError, type ApiError } from '../core/apiError'
import {
  {$def->collectionFunctionName}{$deleteImport},
} from '../api/{$resourceNameLower}'
import {
  getItems,
  getCurrentPage,
  getTotalItems,
  getTotalPages,
  hasNextPage,
  hasPreviousPage,
} from '../core/hydra'

/**
 * Options for {$def->composableName} composable.
 */
export interface Use{$def->resourceName}CollectionOptions {
  /** Default query parameters */
  defaultQuery?: {$queryTypeDefinition}
  /** Auto-fetch when query changes */
  autoFetch?: boolean
  /** Fetch immediately on composable creation */
  immediate?: boolean
}

/**
 * Pagination information.
 */
export interface PaginationInfo {
  currentPage: number
  totalItems: number
  totalPages: number
  itemsPerPage: number
  hasNextPage: boolean
  hasPreviousPage: boolean
}

/**
 * Return type for {$def->composableName} composable.
 */
export interface Use{$def->resourceName}CollectionReturn {
  /** Collection items */
  items: Ref<{$def->listTypeName}[]>
  /** Raw Hydra collection response */
  raw: Ref<HydraCollection<{$def->listTypeName}> | null>
  /** Current query parameters */
  query: Ref<{$queryTypeDefinition}>
  /** Whether a fetch operation is in progress */
  pending: Ref<boolean>
  /** Error from the last fetch operation */
  error: Ref<ApiError | null>
  /** Pagination information */
  pagination: ComputedRef<PaginationInfo | null>

  /** Fetch collection with current query */
  fetch: () => Promise<void>
  /** Refresh collection (alias for fetch) */
  refresh: () => Promise<void>
  /** Set current page and optionally refetch */
  setPage: (page: number) => Promise<void>
  /** Set items per page and optionally refetch */
  setItemsPerPage: (itemsPerPage: number) => Promise<void>
  /** Partially update query and optionally refetch */
  patchQuery: (partialQuery: Partial<{$queryTypeDefinition}>) => Promise<void>
  /** Replace entire query and optionally refetch */
  replaceQuery: (newQuery: {$queryTypeDefinition}) => Promise<void>
  /** Reset query to default and optionally refetch */
  resetQuery: () => Promise<void>{$removeInterfaceProp}
}

/**
 * Composable for managing {$def->resourceName} collection.
 *
 * @example
 * ```typescript
 * // Client-side with auto-fetch
 * const {$resourcePluralName} = {$def->composableName}({{
 *   defaultQuery: {{ page: 1, itemsPerPage: 20 }},
 *   autoFetch: true,
 *   immediate: true
 * }})
 *
 * await {$resourcePluralName}.patchQuery({{ name: 'search' }}) // auto-refetches
 * await {$resourcePluralName}.setPage(2) // auto-refetches
 *
 * // SSR-compatible
 * const {$resourcePluralName} = {$def->composableName}({{ autoFetch: false }})
 * await {$resourcePluralName}.fetch() // manual control
 * ```
 */
export function {$def->composableName}(
  options?: Use{$def->resourceName}CollectionOptions
): Use{$def->resourceName}CollectionReturn {
  const defaultQuery: {$queryTypeDefinition} = {$defaultQuery}
  const query = ref<{$queryTypeDefinition}>({ ...(options?.defaultQuery ?? defaultQuery) })
  const items = ref<{$def->listTypeName}[]>([])
  const raw = ref<HydraCollection<{$def->listTypeName}> | null>(null)
  const pending = ref(false)
  const error = ref<ApiError | null>(null)

  const pagination = computed<PaginationInfo | null>(() => {
    if (!raw.value) return null

    const itemsPerPage = (query.value as { itemsPerPage?: number }).itemsPerPage ?? {$def->defaultItemsPerPage}

    return {
      currentPage: getCurrentPage(raw.value),
      totalItems: getTotalItems(raw.value),
      totalPages: getTotalPages(raw.value, itemsPerPage),
      itemsPerPage,
      hasNextPage: hasNextPage(raw.value),
      hasPreviousPage: hasPreviousPage(raw.value),
    }
  })

  async function fetch(): Promise<void> {
    pending.value = true
    error.value = null
    try {
      const response = await {$def->collectionFunctionName}(query.value)
      raw.value = response
      items.value = getItems(response)
    } catch (err) {
      error.value = formatApiError(err)
      items.value = []
      raw.value = null
    } finally {
      pending.value = false
    }
  }

  async function refresh(): Promise<void> {
    await fetch()
  }

  async function setPage(page: number): Promise<void> {
    query.value = { ...query.value, page } as {$queryTypeDefinition}
    if (options?.autoFetch) {
      await fetch()
    }
  }

  async function setItemsPerPage(itemsPerPage: number): Promise<void> {
    query.value = { ...query.value, itemsPerPage, page: 1 } as {$queryTypeDefinition}
    if (options?.autoFetch) {
      await fetch()
    }
  }

  async function patchQuery(partialQuery: Partial<{$queryTypeDefinition}>): Promise<void> {
    query.value = { ...query.value, ...partialQuery, page: 1 } as {$queryTypeDefinition}
    if (options?.autoFetch) {
      await fetch()
    }
  }

  async function replaceQuery(newQuery: {$queryTypeDefinition}): Promise<void> {
    query.value = newQuery
    if (options?.autoFetch) {
      await fetch()
    }
  }

  async function resetQuery(): Promise<void> {
    query.value = { ...(options?.defaultQuery ?? defaultQuery) }
    if (options?.autoFetch) {
      await fetch()
    }
  }
{$removeMethod}
  if (options?.immediate) {
    void fetch()
  }

  return {
    items,
    raw,
    query,
    pending,
    error,
    pagination,
    fetch,
    refresh,
    setPage,
    setItemsPerPage,
    patchQuery,
    replaceQuery,
    resetQuery,{$removeReturnExport}
  }
}

TS;
    }

    private function generateRemoveMethod(string $deleteFunctionName): string
    {
        return <<<TS

  async function remove(id: number | string): Promise<void> {
    await {$deleteFunctionName}(id)
    await refresh()
  }
TS;
    }
}

