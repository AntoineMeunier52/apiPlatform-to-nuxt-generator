<?php

declare(strict_types=1);

namespace App\Generator\Naming;

use App\Generator\Config\NamingConfiguration;
use App\Generator\DTO\Normalized\NormalizedOperation;
use App\Generator\DTO\Normalized\NormalizedResource;
use App\Generator\DTO\Normalized\OperationType;

/**
 * Service de nommage strict pour éviter les collisions
 */
readonly class NamingStrategy
{
    public function __construct(
        private NamingConfiguration $config,
    ) {}

    /**
     * Génère le nom d'un type TypeScript
     *
     * @param string $context 'collection_output' | 'item_output' | 'create_input' | 'update_input' | 'replace_input' | 'query'
     */
    public function getTypeName(NormalizedResource $resource, string $context): string
    {
        $suffix = $this->config->getTypeSuffix($context);

        return $resource->shortName . $suffix;
    }

    /**
     * Génère le nom d'une fonction API
     */
    public function getFunctionName(NormalizedResource $resource, NormalizedOperation $operation): string
    {
        $resourceName = $resource->shortName;

        // Pour les opérations standard
        if (!$operation->isCustom) {
            $prefix = $this->getFunctionPrefixForOperation($operation);

            // Pluraliser pour les collections
            if ($operation->isCollection) {
                return $prefix . $this->pluralize($resourceName);
            }

            return $prefix . $resourceName;
        }

        // Pour les opérations custom
        return $this->getCustomOperationFunctionName($resource, $operation);
    }

    /**
     * Génère le nom du type query pour une collection
     */
    public function getQueryTypeName(NormalizedResource $resource): string
    {
        return $resource->shortName . 'Collection' . $this->config->getTypeSuffix('query');
    }

    /**
     * Génère un nom de type pour un groupe de sérialisation spécifique
     *
     * @param string[] $groups
     */
    public function getTypeNameForGroups(NormalizedResource $resource, array $groups): string
    {
        if (empty($groups)) {
            return $resource->shortName;
        }

        // Extraire le suffixe du groupe (ex: 'user:read' -> 'Read')
        $groupSuffixes = [];
        foreach ($groups as $group) {
            $parts = explode(':', $group);
            if (\count($parts) > 1) {
                $groupSuffixes[] = $this->toPascalCase($parts[\count($parts) - 1]);
            }
        }

        if (empty($groupSuffixes)) {
            return $resource->shortName;
        }

        // Dédupliquer et trier pour avoir un nom stable
        $groupSuffixes = array_unique($groupSuffixes);
        sort($groupSuffixes);

        return $resource->shortName . implode('', $groupSuffixes);
    }

    /**
     * Convertit un nom en camelCase
     */
    public function toCamelCase(string $name): string
    {
        $pascalCase = $this->toPascalCase($name);

        return lcfirst($pascalCase);
    }

    /**
     * Convertit un nom en PascalCase
     */
    public function toPascalCase(string $name): string
    {
        // Remplacer les séparateurs par des espaces
        $name = str_replace(['-', '_', '.', '/', '\\'], ' ', $name);

        // Capitaliser chaque mot
        $name = ucwords($name);

        // Supprimer les espaces
        return str_replace(' ', '', $name);
    }

    /**
     * Convertit un nom en kebab-case
     */
    public function toKebabCase(string $name): string
    {
        // Insérer un tiret avant chaque majuscule
        $name = preg_replace('/([a-z])([A-Z])/', '$1-$2', $name) ?? $name;

        // Remplacer les underscores et espaces par des tirets
        $name = str_replace(['_', ' '], '-', $name);

        return strtolower($name);
    }

    /**
     * Pluralise un nom de ressource (simple, basé sur l'anglais)
     */
    public function pluralize(string $name): string
    {
        // Règles de base pour l'anglais
        if (str_ends_with($name, 'y') && !preg_match('/[aeiou]y$/i', $name)) {
            return substr($name, 0, -1) . 'ies';
        }

        if (str_ends_with($name, 's') || str_ends_with($name, 'x') || str_ends_with($name, 'ch') || str_ends_with($name, 'sh')) {
            return $name . 'es';
        }

        return $name . 's';
    }

    /**
     * Gets the TypeScript type name for a given class name.
     * Example: App\Entity\User -> User
     */
    public function getTypeNameForClass(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * Generates an output type name from operation metadata.
     * Example: App\Entity\User, GET, false -> UserDetail
     * Example: App\Entity\User, GET_COLLECTION, true -> UserList
     */
    public function generateOutputTypeName(string $className, OperationType $operationType, bool $isCollection): string
    {
        $shortName = $this->getTypeNameForClass($className);

        if ($isCollection) {
            return $shortName . $this->config->getTypeSuffix('collection_output');
        }

        return $shortName . $this->config->getTypeSuffix('item_output');
    }

    /**
     * Generates an input type name from operation metadata.
     * Example: App\Entity\User, POST -> UserCreateInput
     * Example: App\Entity\User, PATCH -> UserUpdateInput
     */
    public function generateInputTypeName(string $className, OperationType $operationType): string
    {
        $shortName = $this->getTypeNameForClass($className);

        return match ($operationType) {
            OperationType::POST => $shortName . $this->config->getTypeSuffix('create_input'),
            OperationType::PATCH => $shortName . $this->config->getTypeSuffix('update_input'),
            OperationType::PUT => $shortName . $this->config->getTypeSuffix('replace_input'),
            default => $shortName . 'Input',
        };
    }

    /**
     * Generates a function name from operation metadata.
     * Example: Program, GET_COLLECTION -> getPrograms
     * Example: Program, GET -> getProgram
     */
    public function generateFunctionName(NormalizedOperation $operation): string
    {
        $resourceName = $this->getTypeNameForClass($operation->class);

        // Handle custom operations (non-standard HTTP methods)
        if ($operation->isCustom) {
            return $this->generateCustomFunctionName($operation, $resourceName);
        }

        // For standard operations with a non-canonical URI (e.g., a second POST with a different path),
        // fall back to semantic naming to avoid duplicate function names.
        if ($this->hasNonCanonicalUri($operation, $resourceName)) {
            return $this->generateCustomFunctionName($operation, $resourceName);
        }

        // Standard CRUD operations with canonical URI
        $prefix = $this->getFunctionPrefixForOperation($operation);

        if ($operation->isCollection) {
            return $prefix . $this->pluralize($resourceName);
        }

        return $prefix . $resourceName;
    }

    /**
     * Checks if an operation has a non-canonical URI for its type.
     *
     * Canonical URIs are the default API Platform paths:
     * - Collection ops: /api/{snake_plural} (e.g., /api/workout_exercises)
     * - Item ops:       /api/{snake_plural}/{var} (e.g., /api/workout_exercises/{id})
     *
     * If the URI doesn't match, the operation is a secondary endpoint that needs
     * a unique name derived from its URI.
     *
     * Note: Applies the same URI normalization as PathParamResolver::cleanUriTemplate()
     * since raw URI templates may lack the /api prefix and contain {._format} extensions.
     */
    private function hasNonCanonicalUri(NormalizedOperation $operation, string $resourceName): bool
    {
        // Apply same cleanup as PathParamResolver::cleanUriTemplate:
        // 1. Strip {._format} extension
        $uri = preg_replace('/\{\._format\}/', '', $operation->uriTemplate) ?? $operation->uriTemplate;
        $uri = rtrim($uri, '/');
        // 2. Ensure /api prefix (raw templates may omit it)
        if (!str_starts_with($uri, '/api')) {
            $uri = '/api' . $uri;
        }

        // Derive the expected base path: WorkoutExercise → workout_exercise → /api/workout_exercises
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $resourceName) ?? $resourceName);
        $basePath = '/api/' . $this->pluralize($snake);

        // Canonical collection-style URI: /api/resource_plural (POST and GET_COLLECTION)
        if ($uri === $basePath) {
            return false;
        }

        // Canonical item-style URI: /api/resource_plural/{single_var} (GET/PATCH/PUT/DELETE)
        if (preg_match('#^' . preg_quote($basePath, '#') . '/\{[^/]+\}$#', $uri)) {
            return false;
        }

        return true;
    }

    /**
     * Generates a function name for custom operations.
     */
    private function generateCustomFunctionName(NormalizedOperation $operation, string $resourceName): string
    {
        // Priority 1: Explicit custom operation name (isCustom=true path)
        if ($operation->customOperationName !== null && $operation->customOperationName !== '') {
            $actionName = $this->toCamelCase($operation->customOperationName);
            return $actionName . $resourceName;
        }

        // Priority 2: User-defined operation name (non-auto-generated).
        // Auto-generated names start with '_api_'; user-defined names don't.
        // Handles non-canonical standard operations (extra DELETE, POST, GET, etc.)
        // that have an explicit `name:` attribute set by the developer.
        if (!str_starts_with($operation->name, '_api_')) {
            $actionName = $this->extractActionFromOperationName($operation->name, $resourceName);
            return $this->toCamelCase($actionName) . $resourceName;
        }

        // Priority 3: Semantic name from URI
        $semanticName = $this->extractSemanticName($operation->uriTemplate, $operation->httpMethod);
        if ($semanticName !== null) {
            return $this->toCamelCase($semanticName) . $resourceName;
        }

        // Fallback to method + resource name
        return strtolower($operation->httpMethod) . $resourceName;
    }

    /**
     * Extracts an action name from a user-defined operation name by stripping the resource prefix.
     * Example: 'training_session_clear_exercises' with resource 'TrainingSession' → 'clear_exercises'
     */
    private function extractActionFromOperationName(string $operationName, string $resourceName): string
    {
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $resourceName) ?? $resourceName);
        $prefix = $snake . '_';
        if (str_starts_with($operationName, $prefix)) {
            return substr($operationName, strlen($prefix));
        }
        return $operationName;
    }

    /**
     * Generates the output type name for an operation.
     *
     * When an explicit output DTO class is specified (operation.output.class !== operation.class),
     * use the DTO short name directly (e.g. ExerciseLogAnalyticsOutput → ExerciseLogAnalyticsOutput).
     * Otherwise, derive the name from the entity class + serialization group suffix.
     */
    public function generateOutputTypeNameForOperation(NormalizedOperation $operation): string
    {
        // If operation uses an explicit output DTO class different from the entity, use its short name
        if ($operation->output !== null && $operation->output->class !== $operation->class) {
            return $this->getTypeNameForClass($operation->output->class);
        }

        $shortName = $this->getTypeNameForClass($operation->class);
        $groups = $operation->getNormalizationGroups();

        if (!empty($groups)) {
            $suffix = $this->extractNonStandardGroupSuffix($groups);
            if ($suffix !== null) {
                return $shortName . $suffix;
            }
        }

        if ($operation->isCollection) {
            return $shortName . $this->config->getTypeSuffix('collection_output');
        }

        return $shortName . $this->config->getTypeSuffix('item_output');
    }

    /**
     * Generates the input type name for an operation.
     *
     * When an explicit input DTO class is specified (operation.input.class !== operation.class),
     * use the DTO short name directly (e.g. ExerciseLogBulkInput → ExerciseLogBulkInput).
     * Otherwise, derive the name from the entity class + serialization group suffix.
     */
    public function generateInputTypeNameForOperation(NormalizedOperation $operation): string
    {
        // If operation uses an explicit input DTO class different from the entity, use its short name
        if ($operation->input !== null && $operation->input->class !== $operation->class) {
            return $this->getTypeNameForClass($operation->input->class);
        }

        $shortName = $this->getTypeNameForClass($operation->class);
        $groups = $operation->getDenormalizationGroups();

        if (!empty($groups)) {
            $suffix = $this->extractNonStandardGroupSuffix($groups);
            if ($suffix !== null) {
                return $shortName . $suffix . 'Input';
            }
        }

        return match ($operation->operationType) {
            OperationType::POST => $shortName . $this->config->getTypeSuffix('create_input'),
            OperationType::PATCH => $shortName . $this->config->getTypeSuffix('update_input'),
            OperationType::PUT => $shortName . $this->config->getTypeSuffix('replace_input'),
            default => $shortName . 'Input',
        };
    }

    /**
     * Extracts a PascalCase suffix from non-standard serialization groups.
     * Returns null for standard CRUD groups (list, read, create, update, replace),
     * which keep their canonical type names.
     */
    private function extractNonStandardGroupSuffix(array $groups): ?string
    {
        $standardSuffixes = ['list', 'read', 'create', 'update', 'replace'];

        $suffixes = [];
        foreach ($groups as $group) {
            $parts = explode(':', $group);
            if (\count($parts) > 1) {
                $lastPart = $parts[\count($parts) - 1];
                if (in_array($lastPart, $standardSuffixes, true)) {
                    return null; // At least one standard group → use standard naming
                }
                $suffixes[] = $this->toPascalCase($lastPart);
            }
        }

        if (empty($suffixes)) {
            return null;
        }

        $suffixes = array_unique($suffixes);
        sort($suffixes);
        return implode('', $suffixes);
    }

    /**
     * Generates a query type name from a class name.
     * Example: App\Entity\User -> UserQuery
     */
    public function generateQueryTypeName(string $className): string
    {
        $shortName = $this->getTypeNameForClass($className);
        return $shortName . $this->config->getTypeSuffix('query');
    }

    /**
     * Obtient le préfixe de fonction pour une opération
     */
    private function getFunctionPrefixForOperation(NormalizedOperation $operation): string
    {
        return match ($operation->operationType) {
            OperationType::GET_COLLECTION => $this->config->getFunctionPrefix('get_collection'),
            OperationType::GET => $this->config->getFunctionPrefix('get_item'),
            OperationType::POST => $this->config->getFunctionPrefix('post'),
            OperationType::PATCH => $this->config->getFunctionPrefix('patch'),
            OperationType::PUT => $this->config->getFunctionPrefix('put'),
            OperationType::DELETE => $this->config->getFunctionPrefix('delete'),
            OperationType::CUSTOM => '', // Géré séparément
        };
    }

    /**
     * Génère le nom d'une fonction pour une opération custom
     */
    private function getCustomOperationFunctionName(NormalizedResource $resource, NormalizedOperation $operation): string
    {
        $resourceName = $resource->shortName;

        // Priorité 1: Nom explicite de l'opération API Platform
        if ($operation->customOperationName !== null && $operation->customOperationName !== '') {
            $actionName = $this->toCamelCase($operation->customOperationName);

            return $actionName . $resourceName;
        }

        // Priorité 2: Extraction sémantique depuis uriTemplate
        $semanticName = $this->extractSemanticName($operation->uriTemplate, $operation->httpMethod);
        if ($semanticName !== null) {
            return $this->toCamelCase($semanticName) . $resourceName;
        }

        // Priorité 3: Fallback method + path segments
        return $this->generateFallbackName($operation, $resourceName);
    }

    /**
     * Extrait un nom sémantique depuis l'URI template
     *
     * POST /users/{id}/activate → 'activate'
     * POST /users/{id}/send-verification → 'sendVerification'
     */
    private function extractSemanticName(string $uriTemplate, string $method): ?string
    {
        // Supprimer le préfixe /api si présent
        $path = preg_replace('#^/api#', '', $uriTemplate) ?? $uriTemplate;

        // Supprimer les variables d'URI
        $path = preg_replace('#/\{[^}]+\}#', '', $path) ?? $path;

        // Extraire le dernier segment
        $segments = array_filter(explode('/', $path));
        if (empty($segments)) {
            return null;
        }

        $lastSegment = array_pop($segments);

        // Si le dernier segment ressemble au nom de la ressource, ce n'est pas un nom d'action
        // On suppose que les noms de ressources sont au pluriel
        if (preg_match('/^[a-z]+s$/i', $lastSegment)) {
            return null;
        }

        return $this->toCamelCase($lastSegment);
    }

    /**
     * Génère un nom de fallback basé sur la méthode HTTP et les segments de chemin
     */
    private function generateFallbackName(NormalizedOperation $operation, string $resourceName): string
    {
        $method = strtolower($operation->httpMethod);

        // Extraire les segments significatifs de l'URI
        $path = preg_replace('#^/api#', '', $operation->uriTemplate) ?? $operation->uriTemplate;
        $path = preg_replace('#/\{[^}]+\}#', '', $path) ?? $path;
        $segments = array_filter(explode('/', $path));

        // Construire le nom
        $parts = [$method];
        foreach ($segments as $segment) {
            $parts[] = $this->toPascalCase($segment);
        }

        return implode('', $parts);
    }
}
