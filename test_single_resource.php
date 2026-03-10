<?php

require 'vendor/autoload.php';

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$extractor = $container->get(App\Generator\Extractor\ApiPlatformMetadataExtractor::class);
$normalizer = $container->get(App\Generator\Normalizer\MetadataNormalizer::class);

$resourceClass = 'App\\Entity\\Templating\\Program';

echo "Testing resource: $resourceClass\n";
echo "====================================\n\n";

// Test 1: Extract metadata
echo "1. Extracting resource metadata...\n";
try {
    $metadata = $extractor->extractResourceMetadata($resourceClass);
    if ($metadata === null) {
        echo "   ❌ Metadata is NULL\n";
    } else {
        echo "   ✓ Metadata found: " . get_class($metadata) . "\n";
        echo "   Count: " . $metadata->count() . "\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 2: Extract operations
echo "\n2. Extracting operations...\n";
try {
    $operations = $extractor->extractOperations($resourceClass);
    echo "   ✓ Found " . count($operations) . " operations\n";
    foreach ($operations as $op) {
        echo "     - " . get_class($op) . "\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 3: Normalize resource
echo "\n3. Normalizing resource...\n";
try {
    $normalized = $normalizer->normalizeResource($resourceClass);
    if ($normalized === null) {
        echo "   ❌ Normalized resource is NULL\n";
    } else {
        echo "   ✓ Normalized successfully\n";
        echo "   Short name: " . $normalized->shortName . "\n";
        echo "   Operations: " . count($normalized->operations) . "\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    echo "   Trace:\n";
    echo $e->getTraceAsString() . "\n";
}

