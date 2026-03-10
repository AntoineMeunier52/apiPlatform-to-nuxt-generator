<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Loads nuxt_generator configuration.
 */
class NuxtGeneratorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store processed config as parameter
        $container->setParameter('nuxt_generator', $config);
    }

    public function getAlias(): string
    {
        return 'nuxt_generator';
    }
}
