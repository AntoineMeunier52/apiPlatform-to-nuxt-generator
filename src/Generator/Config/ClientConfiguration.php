<?php

declare(strict_types=1);

namespace App\Generator\Config;

/**
 * Configuration du client API généré
 */
readonly class ClientConfiguration
{
    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(
        public string $baseUrl = '/api',
        public string $credentials = 'include',
        public array $defaultHeaders = [
            'Content-Type' => 'application/ld+json',
            'Accept' => 'application/ld+json',
        ],
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            baseUrl: $config['base_url'] ?? '/api',
            credentials: $config['credentials'] ?? 'include',
            defaultHeaders: $config['default_headers'] ?? [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        );
    }
}
