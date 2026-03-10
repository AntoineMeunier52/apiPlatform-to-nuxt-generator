<?php

declare(strict_types=1);

namespace App\Generator\Naming;

use App\Generator\DTO\Normalized\NormalizedOperation;

/**
 * Generates human-readable names for custom operations.
 *
 * Custom operations (non-CRUD endpoints) need special naming logic.
 *
 * Examples:
 * - POST /api/programs/{id}/publish -> publishProgram
 * - GET /api/programs/{id}/stats -> getProgramStats
 * - POST /api/users/reset-password -> resetUserPassword
 */
readonly class CustomOperationNamer
{
    /**
     * Generates a function name for a custom operation.
     */
    public function generateCustomOperationName(NormalizedOperation $operation): string
    {
        // If operation has an explicit custom name, use it
        if ($operation->customOperationName !== null) {
            return $this->normalizeName($operation->customOperationName);
        }

        // Extract action from URI template
        $action = $this->extractActionFromUri($operation->uriTemplate, $operation->httpMethod);

        // Get resource short name
        $resourceName = $this->getResourceShortName($operation->class);

        // Combine action + resource
        return $this->combineActionAndResource($action, $resourceName, $operation->httpMethod);
    }

    /**
     * Extracts action name from URI template.
     *
     * Examples:
     * - /api/programs/{id}/publish -> publish
     * - /api/programs/{id}/stats -> stats
     * - /api/users/reset-password -> resetPassword
     */
    private function extractActionFromUri(string $uriTemplate, string $httpMethod): string
    {
        // Remove base path and parameters
        $parts = explode('/', trim($uriTemplate, '/'));

        // Find the last non-parameter part
        $actionParts = [];
        foreach (array_reverse($parts) as $part) {
            // Skip parameters like {id}
            if (str_contains($part, '{')) {
                continue;
            }

            // Skip common resource names (api, programs, users, etc.)
            if ($this->isCommonResourceName($part)) {
                break;
            }

            $actionParts[] = $part;
        }

        if (empty($actionParts)) {
            // Fallback to HTTP method
            return strtolower($httpMethod);
        }

        // Reverse to get correct order
        $actionParts = array_reverse($actionParts);

        // Convert kebab-case to camelCase
        return $this->kebabToCamelCase(implode('-', $actionParts));
    }

    /**
     * Combines action and resource name into a function name.
     */
    private function combineActionAndResource(string $action, string $resourceName, string $httpMethod): string
    {
        // If action already contains the resource name, don't duplicate
        if (stripos($action, $resourceName) !== false) {
            return lcfirst($action);
        }

        // Add HTTP method verb if action is too generic
        $verb = $this->getVerbFromHttpMethod($httpMethod);

        if ($this->isGenericAction($action)) {
            return $verb . ucfirst($resourceName) . ucfirst($action);
        }

        return $action . ucfirst($resourceName);
    }

    /**
     * Gets the resource short name from class.
     */
    private function getResourceShortName(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }

    /**
     * Converts kebab-case to camelCase.
     */
    private function kebabToCamelCase(string $string): string
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Normalizes a custom operation name to camelCase.
     */
    private function normalizeName(string $name): string
    {
        // Remove common prefixes/suffixes
        $name = preg_replace('/^(api_|_api)/', '', $name);

        // Convert snake_case to camelCase
        $name = lcfirst(str_replace('_', '', ucwords($name, '_')));

        return $name;
    }

    /**
     * Checks if a part is a common resource name.
     */
    private function isCommonResourceName(string $part): bool
    {
        $common = ['api', 'v1', 'v2', 'resources', 'items', 'collections'];
        return in_array(strtolower($part), $common, true);
    }

    /**
     * Gets verb from HTTP method.
     */
    private function getVerbFromHttpMethod(string $httpMethod): string
    {
        return match (strtoupper($httpMethod)) {
            'GET' => 'get',
            'POST' => 'create',
            'PUT' => 'replace',
            'PATCH' => 'update',
            'DELETE' => 'delete',
            default => strtolower($httpMethod),
        };
    }

    /**
     * Checks if action is too generic and needs a verb.
     */
    private function isGenericAction(string $action): bool
    {
        $generic = ['action', 'execute', 'process', 'handle', 'do'];
        return in_array(strtolower($action), $generic, true);
    }
}
