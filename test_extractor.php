<?php

require 'vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Get the extractor service
$extractor = $container->get(App\Generator\Extractor\ApiPlatformMetadataExtractor::class);

echo "Testing ApiPlatformMetadataExtractor...\n";
echo "========================================\n\n";

try {
    $resources = $extractor->getResourceClasses();
    echo "Number of resources found: " . count($resources) . "\n\n";

    foreach ($resources as $resourceClass) {
        echo "Resource: $resourceClass\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

