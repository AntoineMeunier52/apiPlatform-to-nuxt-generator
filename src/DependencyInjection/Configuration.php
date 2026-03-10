<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration definition for nuxt_generator.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('nuxt_generator');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('output_path')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Path to the Nuxt project root (will generate files in {output_path}/generated/)')
                ->end()
                ->arrayNode('defaults')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('clean_before_generate')
                            ->defaultTrue()
                            ->info('Clean generated directory before generating')
                        ->end()
                        ->booleanNode('generate_hydra_helpers')
                            ->defaultTrue()
                            ->info('Generate Hydra pagination helpers')
                        ->end()
                        ->booleanNode('strict_null_checks')
                            ->defaultTrue()
                            ->info('Enable strict null checks in TypeScript')
                        ->end()
                        ->integerNode('default_items_per_page')
                            ->defaultValue(30)
                            ->info('Default items per page for pagination')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('client')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('base_url')
                            ->defaultValue('/api')
                            ->info('Base URL for API calls')
                        ->end()
                        ->scalarNode('credentials')
                            ->defaultValue('include')
                            ->info('Credentials mode for fetch (include, same-origin, omit)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('naming')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('type_suffixes')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('collection_output')
                                    ->defaultValue('List')
                                    ->info('Suffix for collection output types')
                                ->end()
                                ->scalarNode('item_output')
                                    ->defaultValue('Detail')
                                    ->info('Suffix for item output types')
                                ->end()
                                ->scalarNode('create_input')
                                    ->defaultValue('CreateInput')
                                    ->info('Suffix for create input types')
                                ->end()
                                ->scalarNode('update_input')
                                    ->defaultValue('UpdateInput')
                                    ->info('Suffix for update input types (PATCH)')
                                ->end()
                                ->scalarNode('replace_input')
                                    ->defaultValue('ReplaceInput')
                                    ->info('Suffix for replace input types (PUT)')
                                ->end()
                                ->scalarNode('query')
                                    ->defaultValue('Query')
                                    ->info('Suffix for query parameter types')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
