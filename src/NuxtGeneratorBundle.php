<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle for Nuxt interface generator.
 */
class NuxtGeneratorBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}

