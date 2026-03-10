<?php

declare(strict_types=1);

namespace App\Generator\Config;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Factory for creating GeneratorConfiguration from Symfony config.
 */
class GeneratorConfigurationFactory
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {}

    /**
     * Creates GeneratorConfiguration from config array.
     *
     * @param array<string, mixed> $config
     */
    public function create(array $config = []): GeneratorConfiguration
    {
        // Fallback to parameter bag if config is empty
        if (empty($config)) {
            $config = $this->parameterBag->get('nuxt_generator');
        }

        return new GeneratorConfiguration(
            outputPath: $config['output_path'] ?? '',
            cleanBeforeGenerate: $config['defaults']['clean_before_generate'] ?? true,
            generateHydraHelpers: $config['defaults']['generate_hydra_helpers'] ?? true,
            strictNullChecks: $config['defaults']['strict_null_checks'] ?? true,
            defaultItemsPerPage: $config['defaults']['default_items_per_page'] ?? 30,
            client: new ClientConfiguration(
                baseUrl: $config['client']['base_url'] ?? '/api',
                credentials: $config['client']['credentials'] ?? 'include',
            ),
            naming: new NamingConfiguration(
                typeSuffixes: $config['naming']['type_suffixes'] ?? [],
            ),
        );
    }
}
