<?php

declare(strict_types=1);

namespace App\Generator\Config;

/**
 * Configuration du nommage des types et fonctions générés
 */
readonly class NamingConfiguration
{
    /**
     * @param array<string, string> $typeSuffixes
     * @param array<string, string> $functionPrefixes
     * @param array<int, string> $customOperationNamingPriority
     */
    public function __construct(
        public array $typeSuffixes = [
            'collection_output' => 'List',
            'item_output' => 'Detail',
            'create_input' => 'CreateInput',
            'update_input' => 'UpdateInput',
            'replace_input' => 'ReplaceInput',
            'query' => 'Query',
        ],
        public array $functionPrefixes = [
            'get_collection' => 'list',
            'get_item' => 'get',
            'post' => 'create',
            'patch' => 'update',
            'put' => 'replace',
            'delete' => 'delete',
        ],
        public array $customOperationNamingPriority = [
            'explicit_name',
            'uri_semantic',
            'method_path_fallback',
        ],
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            typeSuffixes: $config['type_suffixes'] ?? [
                'collection_output' => 'List',
                'item_output' => 'Detail',
                'create_input' => 'CreateInput',
                'update_input' => 'UpdateInput',
                'replace_input' => 'ReplaceInput',
                'query' => 'Query',
            ],
            functionPrefixes: $config['function_prefixes'] ?? [
                'get_collection' => 'list',
                'get_item' => 'get',
                'post' => 'create',
                'patch' => 'update',
                'put' => 'replace',
                'delete' => 'delete',
            ],
            customOperationNamingPriority: $config['custom_operation_naming']['priority'] ?? [
                'explicit_name',
                'uri_semantic',
                'method_path_fallback',
            ],
        );
    }

    public function getTypeSuffix(string $context): string
    {
        return $this->typeSuffixes[$context] ?? '';
    }

    public function getFunctionPrefix(string $operationType): string
    {
        return $this->functionPrefixes[$operationType] ?? '';
    }
}
