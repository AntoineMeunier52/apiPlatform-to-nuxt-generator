<?php

declare(strict_types=1);

namespace App\Generator\TypeScript;

use App\Generator\Writer\FileWriter;
use App\Generator\Config\GeneratorConfiguration;

/**
 * Generates reusable Nuxt composables.
 *
 * Generates:
 * - composables/useSave.ts - Enhanced save composable with lifecycle hooks
 * - composables/useApiError.ts - Composable for API error handling
 * - composables/use<Resource>Collection.ts - Collection management per resource
 * - composables/use<Resource>Resource.ts - Item CRUD operations per resource
 * - core/fetcher.ts - API fetcher implementation
 * - core/hydra.ts - Hydra pagination types and helpers
 * - core/apiError.ts - API error types and helpers
 */
class ComposableGenerator extends CoreGenerator
{
    public function __construct(
        FileWriter $fileWriter,
        GeneratorConfiguration $config,
        private readonly UseSaveGenerator $useSaveGenerator,
        private readonly ResourceCollectionComposableGenerator $collectionComposableGenerator,
        private readonly ResourceItemComposableGenerator $itemComposableGenerator,
    ) {
        parent::__construct($fileWriter, $config);
    }

    /**
     * Generates all composable files.
     *
     * @param array<NormalizedResource> $data Array of normalized resources
     */
    public function generate(mixed $data = null): void
    {
        $resources = $data ?? [];

        // Generate core files
        $this->generateFetcher();
        $this->generateHydra();
        $this->generateApiFetcherInterface();
        $this->generateApiError();

        // Generate enhanced useSave
        $this->useSaveGenerator->generate();

        // Generate useApiError (existing)
        $this->generateUseApiError();

        // Generate resource-level composables
        foreach ($resources as $resource) {
            $this->collectionComposableGenerator->generate($resource);
            $this->itemComposableGenerator->generate($resource);
        }
    }

    /**
     * Generates the ApiFetcher interface.
     */
    private function generateApiFetcherInterface(): void
    {
        $content = $this->generateFileHeader('ApiFetcher interface for framework-agnostic API calls');

        $content .= <<<'TS'
/**
 * API Fetcher interface
 *
 * This interface abstracts the HTTP client implementation,
 * allowing the generated code to work with any HTTP library.
 */
export interface ApiFetcherOptions {
  query?: Record<string, any>
  body?: any
  headers?: Record<string, string>
}

export interface ApiFetcher {
  get<T = any>(path: string, options?: ApiFetcherOptions): Promise<T>
  post<T = any>(path: string, options?: ApiFetcherOptions): Promise<T>
  put<T = any>(path: string, options?: ApiFetcherOptions): Promise<T>
  patch<T = any>(path: string, options?: ApiFetcherOptions): Promise<T>
  delete<T = any>(path: string, options?: ApiFetcherOptions): Promise<T>
}

TS;

        $this->write('core/types.ts', $content);
    }

    /**
     * Generates the fetcher implementation.
     */
    private function generateFetcher(): void
    {
        $content = $this->generateFileHeader('API Fetcher implementation');

        $baseUrl = $this->config->client->baseUrl;
        $credentials = $this->config->client->credentials;

        $content .= <<<TS
import type { ApiFetcher, ApiFetcherOptions } from './types'

/**
 * Default API fetcher implementation using native fetch
 */
class FetcherImplementation implements ApiFetcher {
  private baseUrl: string
  private defaultOptions: RequestInit

  constructor(baseUrl: string = '{$baseUrl}', credentials: RequestCredentials = '{$credentials}') {
    this.baseUrl = baseUrl
    this.defaultOptions = {
      credentials,
      headers: {
        'Content-Type': 'application/ld+json',
        'Accept': 'application/ld+json',
      },
    }
  }

  private async request<T>(method: string, path: string, options?: ApiFetcherOptions): Promise<T> {
    const url = new URL(path, typeof window !== 'undefined' ? window.location.origin : 'http://localhost')

    // Add query parameters
    if (options?.query) {
      Object.entries(options.query).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          url.searchParams.append(key, String(value))
        }
      })
    }

    const requestInit: RequestInit = {
      ...this.defaultOptions,
      method,
      headers: {
        ...this.defaultOptions.headers,
        ...options?.headers,
      },
    }

    // Add body for POST/PUT/PATCH
    if (options?.body) {
      requestInit.body = JSON.stringify(options.body)
    }

    const response = await fetch(url.toString(), requestInit)

    if (!response.ok) {
      throw await this.handleError(response)
    }

    // Handle 204 No Content
    if (response.status === 204) {
      return undefined as T
    }

    return response.json()
  }

  private async handleError(response: Response): Promise<ApiError> {
    let errorData: any = {}

    try {
      errorData = await response.json()
    } catch {
      // Response body is not JSON
    }

    return {
      status: response.status,
      title: errorData['hydra:title'] || response.statusText,
      detail: errorData['hydra:description'] || errorData.detail || 'An error occurred',
      violations: errorData.violations || [],
      type: errorData['@type'] || 'Error',
    }
  }

  async get<T = any>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return this.request<T>('GET', path, options)
  }

  async post<T = any>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return this.request<T>('POST', path, options)
  }

  async put<T = any>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return this.request<T>('PUT', path, options)
  }

  async patch<T = any>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return this.request<T>('PATCH', path, options)
  }

  async delete<T = any>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return this.request<T>('DELETE', path, options)
  }
}

/**
 * API Error type
 */
export interface ApiError {
  status: number
  title: string
  detail: string
  violations: Array<{ propertyPath: string; message: string }>
  type: string
}

/**
 * Singleton fetcher instance
 */
export const apiFetcher: ApiFetcher = new FetcherImplementation()

TS;

        $this->write('core/fetcher.ts', $content);
    }

    /**
     * Generates Hydra pagination types and helpers.
     */
    private function generateHydra(): void
    {
        $content = $this->generateFileHeader('Hydra JSON-LD pagination types and helpers');

        $defaultItemsPerPage = $this->config->defaultItemsPerPage;

        $content .= <<<TS
/**
 * Hydra Collection response
 */
export interface HydraCollection<T> {
  '@context': string
  '@id': string
  '@type': 'hydra:Collection'
  'hydra:member': T[]
  'hydra:totalItems': number
  'hydra:view'?: HydraView
  'hydra:search'?: HydraSearch
}

/**
 * Hydra View (pagination links)
 */
export interface HydraView {
  '@id': string
  '@type': 'hydra:PartialCollectionView'
  'hydra:first'?: string
  'hydra:last'?: string
  'hydra:previous'?: string
  'hydra:next'?: string
}

/**
 * Hydra Search (available filters)
 */
export interface HydraSearch {
  '@type': 'hydra:IriTemplate'
  'hydra:template': string
  'hydra:variableRepresentation': string
  'hydra:mapping': HydraMapping[]
}

export interface HydraMapping {
  '@type': 'IriTemplateMapping'
  variable: string
  property: string | null
  required: boolean
}

/**
 * Extracts items from Hydra collection
 */
export function getItems<T>(collection: HydraCollection<T>): T[] {
  return collection['hydra:member']
}

/**
 * Gets total items count from Hydra collection
 */
export function getTotalItems<T>(collection: HydraCollection<T>): number {
  return collection['hydra:totalItems']
}

/**
 * Checks if collection has next page
 */
export function hasNextPage<T>(collection: HydraCollection<T>): boolean {
  return collection['hydra:view']?.['hydra:next'] !== undefined
}

/**
 * Checks if collection has previous page
 */
export function hasPreviousPage<T>(collection: HydraCollection<T>): boolean {
  return collection['hydra:view']?.['hydra:previous'] !== undefined
}

/**
 * Calculates total pages
 */
export function getTotalPages<T>(collection: HydraCollection<T>, itemsPerPage: number = {$defaultItemsPerPage}): number {
  return Math.ceil(collection['hydra:totalItems'] / itemsPerPage)
}

/**
 * Gets current page from view
 */
export function getCurrentPage<T>(collection: HydraCollection<T>): number {
  const view = collection['hydra:view']
  if (!view) return 1

  const url = new URL(view['@id'], 'http://localhost')
  const page = url.searchParams.get('page')
  return page ? parseInt(page, 10) : 1
}

TS;

        $this->write('core/hydra.ts', $content);
    }

    /**
     * Generates API error types and formatApiError helper.
     */
    private function generateApiError(): void
    {
        $content = $this->generateFileHeader('API error types and helpers');

        $content .= <<<'TS'
/**
 * API violation from validation errors
 */
export interface ApiViolation {
  propertyPath: string
  message: string
}

/**
 * Structured API error
 */
export interface ApiError {
  status: number
  title: string
  detail: string
  violations: ApiViolation[]
  type: string
}

/**
 * Normalizes caught errors into ApiError format.
 * Always use this instead of casting with 'as ApiError'.
 */
export function formatApiError(err: unknown): ApiError {
  // Already an ApiError
  if (
    err &&
    typeof err === 'object' &&
    'status' in err &&
    'title' in err &&
    'detail' in err &&
    'violations' in err
  ) {
    return err as ApiError
  }

  // HTTP error response
  if (err && typeof err === 'object' && 'status' in err) {
    const httpError = err as any
    return {
      status: httpError.status ?? 500,
      title: httpError.title ?? httpError.statusText ?? 'Error',
      detail: httpError.detail ?? httpError.message ?? 'An error occurred',
      violations: httpError.violations ?? [],
      type: httpError.type ?? 'Error',
    }
  }

  // Generic error
  if (err instanceof Error) {
    return {
      status: 500,
      title: 'Error',
      detail: err.message,
      violations: [],
      type: 'Error',
    }
  }

  // Unknown error
  return {
    status: 500,
    title: 'Error',
    detail: 'An unknown error occurred',
    violations: [],
    type: 'Error',
  }
}

TS;

        $this->write('core/apiError.ts', $content);
    }

    /**
     * Generates useApiError composable.
     */
    private function generateUseApiError(): void
    {
        $content = $this->generateFileHeader('useApiError composable for API error handling');

        $content .= <<<'TS'
import { computed } from 'vue'
import type { ComputedRef } from 'vue'
import type { ApiError, ApiViolation } from '../core/apiError'

/**
 * Composable for working with API errors
 */
export function useApiError(error: ApiError | null) {
  const hasError: ComputedRef<boolean> = computed(() => error !== null)

  const errorMessage: ComputedRef<string> = computed(() => {
    if (!error) return ''
    return error.detail || error.title
  })

  const violations: ComputedRef<ApiViolation[]> = computed(() => {
    return error?.violations || []
  })

  const getViolation = (propertyPath: string): string | null => {
    if (!error) return null
    const violation = error.violations.find(v => v.propertyPath === propertyPath)
    return violation?.message || null
  }

  const hasViolation = (propertyPath: string): boolean => {
    return getViolation(propertyPath) !== null
  }

  const isValidationError: ComputedRef<boolean> = computed(() => {
    return error?.status === 422 || (error?.violations?.length ?? 0) > 0
  })

  const isNotFoundError: ComputedRef<boolean> = computed(() => {
    return error?.status === 404
  })

  const isUnauthorizedError: ComputedRef<boolean> = computed(() => {
    return error?.status === 401
  })

  const isForbiddenError: ComputedRef<boolean> = computed(() => {
    return error?.status === 403
  })

  return {
    hasError,
    errorMessage,
    violations,
    getViolation,
    hasViolation,
    isValidationError,
    isNotFoundError,
    isUnauthorizedError,
    isForbiddenError,
  }
}

TS;

        $this->write('composables/useApiError.ts', $content);
    }
}
