<?php

declare(strict_types=1);

namespace App\Generator\Config;

/**
 * Configuration principale du générateur Nuxt
 */
readonly class GeneratorConfiguration
{
    public function __construct(
        public string $outputPath,
        public bool $cleanBeforeGenerate = true,
        public bool $generateHydraHelpers = true,
        public bool $strictNullChecks = true,
        public int $defaultItemsPerPage = 30,
        public ClientConfiguration $client = new ClientConfiguration(),
        public NamingConfiguration $naming = new NamingConfiguration(),
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $defaults = $config['defaults'] ?? [];

        return new self(
            outputPath: $config['output_path'] ?? throw new \InvalidArgumentException('output_path is required'),
            cleanBeforeGenerate: $defaults['clean_before_generate'] ?? true,
            generateHydraHelpers: $defaults['generate_hydra_helpers'] ?? true,
            strictNullChecks: $defaults['strict_null_checks'] ?? true,
            defaultItemsPerPage: $defaults['default_items_per_page'] ?? 30,
            client: ClientConfiguration::fromArray($config['client'] ?? []),
            naming: NamingConfiguration::fromArray($config['naming'] ?? []),
        );
    }

    /**
     * Retourne le chemin absolu vers le dossier generated
     */
    public function getGeneratedPath(): string
    {
        return rtrim($this->outputPath, '/') . '/generated';
    }
}
