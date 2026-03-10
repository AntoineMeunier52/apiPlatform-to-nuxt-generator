<?php

declare(strict_types=1);

namespace App\Generator\TypeScript;

use App\Generator\Writer\FileWriter;
use App\Generator\Config\GeneratorConfiguration;
use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\DTO\Output\ResourceComposableDefinition;
use App\Generator\Resolver\ResourceComposableResolver;

/**
 * Generates resource item composables (use<Resource>Resource).
 */
class ResourceItemComposableGenerator extends CoreGenerator
{
    public function __construct(
        FileWriter $fileWriter,
        GeneratorConfiguration $config,
        private readonly ResourceComposableResolver $resolver,
    ) {
        parent::__construct($fileWriter, $config);
    }

    public function generate(mixed $data = null): void
    {
        if (!$data instanceof NormalizedResource) {
            throw new \InvalidArgumentException('ResourceItemComposableGenerator expects NormalizedResource');
        }

        $definition = $this->resolver->resolve($data);

        if ($definition === null) {
            return; // No relevant operations, skip
        }

        $content = $this->generateFileHeader("Resource composable for {$definition->resourceName}");
        $content .= $this->generateResourceComposable($definition);

        $fileName = "{$definition->composableName}.ts";
        $this->write("composables/{$fileName}", $content);
    }

    private function generateResourceComposable(ResourceComposableDefinition $def): string
    {
        $resourceNameLower = strtolower($def->resourceName);

        // Build type imports (all types come from the same file per resource)
        $importedTypes = [$def->detailTypeName];
        if ($def->createInputType) {
            $importedTypes[] = $def->createInputType;
        }
        if ($def->updateInputType) {
            $importedTypes[] = $def->updateInputType;
        }
        if ($def->replaceInputType) {
            $importedTypes[] = $def->replaceInputType;
        }
        $importedTypes = array_unique($importedTypes);
        $importedTypesStr = implode(', ', $importedTypes);
        $typeImportsStr = "import type { {$importedTypesStr} } from '../types/{$def->resourceName}'";

        // Build API function imports
        $apiFunctions = array_values($def->functionNames);
        $apiFunctionsStr = implode(",\n  ", $apiFunctions);

        // Build methods
        $methods = [];

        if ($def->hasGet) {
            $methods[] = $this->generateFetchMethod($def->functionNames['get']);
        }

        if ($def->hasPost) {
            $methods[] = $this->generateCreateMethod($def->functionNames['post'], $def->createInputType, $def->detailTypeName, $def->identifierName);
        }

        if ($def->hasPatch) {
            $methods[] = $this->generateUpdateMethod($def->functionNames['patch'], $def->updateInputType, $def->detailTypeName);
        }

        if ($def->hasPut) {
            $methods[] = $this->generateReplaceMethod($def->functionNames['put'], $def->replaceInputType, $def->detailTypeName);
        }

        if ($def->hasDelete) {
            $methods[] = $this->generateRemoveMethod($def->functionNames['delete']);
        }

        $methodsStr = implode("\n\n", $methods);

        // Build return interface properties
        $returnProps = [];

        if ($def->hasGet) {
            $returnProps[] = "  /** Fetch resource by ID */\n  fetch: (id: number | string) => Promise<void>";
            $returnProps[] = "  /** Refresh current resource */\n  refresh: () => Promise<void>";
        }

        if ($def->hasPost) {
            $returnProps[] = "  /** Create new resource */\n  create: (input: {$def->createInputType}) => Promise<{$def->detailTypeName} | null>";
        }

        if ($def->hasPatch) {
            $returnProps[] = "  /** Update existing resource (partial) */\n  update: (id: number | string, input: {$def->updateInputType}) => Promise<{$def->detailTypeName} | null>";
        }

        if ($def->hasPut) {
            $returnProps[] = "  /** Replace existing resource (full) */\n  replace: (id: number | string, input: {$def->replaceInputType}) => Promise<{$def->detailTypeName} | null>";
        }

        if ($def->hasDelete) {
            $returnProps[] = "  /** Delete resource */\n  remove: (id: number | string) => Promise<void>";
        }

        $returnPropsStr = implode("\n", $returnProps);

        // Build return object exports
        $returnExports = ['item', 'pending', 'error'];

        if ($def->hasGet) {
            $returnExports[] = 'fetch';
            $returnExports[] = 'refresh';
        }
        if ($def->hasPost) {
            $returnExports[] = 'create';
        }
        if ($def->hasPatch) {
            $returnExports[] = 'update';
        }
        if ($def->hasPut) {
            $returnExports[] = 'replace';
        }
        if ($def->hasDelete) {
            $returnExports[] = 'remove';
        }
        $returnExports[] = 'clear';

        $returnExportsStr = implode(",\n    ", $returnExports);

        return <<<TS
import { ref, type Ref } from 'vue'
{$typeImportsStr}
import { formatApiError, type ApiError } from '../core/apiError'
import {
  {$apiFunctionsStr},
} from '../api/{$resourceNameLower}'

/**
 * Return type for {$def->composableName} composable.
 */
export interface Use{$def->resourceName}ResourceReturn {
  /** Current resource item */
  item: Ref<{$def->detailTypeName} | null>
  /** Whether an operation is in progress */
  pending: Ref<boolean>
  /** Error from the last operation */
  error: Ref<ApiError | null>

{$returnPropsStr}

  /** Clear current resource and state */
  clear: () => void
}

/**
 * Composable for managing {$def->resourceName} resource.
 *
 * @example
 * ```typescript
 * const {$resourceNameLower} = {$def->composableName}()
 *
 * await {$resourceNameLower}.fetch(123)
 * await {$resourceNameLower}.update(123, {{ name: 'Updated' }})
 * await {$resourceNameLower}.create({{ name: 'New' }})
 * {$resourceNameLower}.clear()
 * ```
 */
export function {$def->composableName}(): Use{$def->resourceName}ResourceReturn {
  const item = ref<{$def->detailTypeName} | null>(null)
  const pending = ref(false)
  const error = ref<ApiError | null>(null)
  const currentId = ref<number | string | null>(null)

{$methodsStr}

  function clear(): void {
    item.value = null
    currentId.value = null
    error.value = null
  }

  return {
    {$returnExportsStr},
  }
}

TS;
    }

    private function generateFetchMethod(string $getFunctionName): string
    {
        return <<<TS
  async function fetch(id: number | string): Promise<void> {
    pending.value = true
    error.value = null
    currentId.value = id
    try {
      item.value = await {$getFunctionName}(id)
    } catch (err) {
      error.value = formatApiError(err)
      item.value = null
    } finally {
      pending.value = false
    }
  }

  async function refresh(): Promise<void> {
    if (currentId.value === null) {
      throw new Error('No item loaded, call fetch() first')
    }
    await fetch(currentId.value)
  }
TS;
    }

    private function generateCreateMethod(string $createFunctionName, string $inputType, string $detailTypeName, string $identifierName): string
    {
        return <<<TS
  async function create(input: {$inputType}): Promise<{$detailTypeName} | null> {
    pending.value = true
    error.value = null
    try {
      const result = await {$createFunctionName}(input)
      item.value = result
      currentId.value = result.{$identifierName} ?? null
      return result
    } catch (err) {
      error.value = formatApiError(err)
      return null
    } finally {
      pending.value = false
    }
  }
TS;
    }

    private function generateUpdateMethod(string $updateFunctionName, string $inputType, string $detailTypeName): string
    {
        return <<<TS
  async function update(id: number | string, input: {$inputType}): Promise<{$detailTypeName} | null> {
    pending.value = true
    error.value = null
    try {
      const result = await {$updateFunctionName}(id, input)
      item.value = result
      currentId.value = id
      return result
    } catch (err) {
      error.value = formatApiError(err)
      return null
    } finally {
      pending.value = false
    }
  }
TS;
    }

    private function generateReplaceMethod(string $replaceFunctionName, string $inputType, string $detailTypeName): string
    {
        return <<<TS
  async function replace(id: number | string, input: {$inputType}): Promise<{$detailTypeName} | null> {
    pending.value = true
    error.value = null
    try {
      const result = await {$replaceFunctionName}(id, input)
      item.value = result
      currentId.value = id
      return result
    } catch (err) {
      error.value = formatApiError(err)
      return null
    } finally {
      pending.value = false
    }
  }
TS;
    }

    private function generateRemoveMethod(string $deleteFunctionName): string
    {
        return <<<TS
  async function remove(id: number | string): Promise<void> {
    pending.value = true
    error.value = null
    try {
      await {$deleteFunctionName}(id)
      if (currentId.value === id) {
        clear()
      }
    } catch (err) {
      error.value = formatApiError(err)
    } finally {
      pending.value = false
    }
  }
TS;
    }
}

