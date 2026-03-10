<?php

namespace App\Command;

use App\Generator\Extractor\ApiPlatformMetadataExtractor;
use App\Generator\Normalizer\MetadataNormalizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'debug:normalizer', description: 'Debug metadata normalizer')]
class DebugNormalizerCommand extends Command
{
    public function __construct(
        private readonly MetadataNormalizer $normalizer,
        private readonly ApiPlatformMetadataExtractor $extractor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Debug Metadata Normalizer');

        // First get raw resources
        $io->section('Raw resources from extractor');
        $rawResources = $this->extractor->getResourceClasses();
        $io->writeln("Found " . count($rawResources) . " raw resources");

        // Test each resource individually
        foreach ($rawResources as $resourceClass) {
            $io->writeln("\nTesting: $resourceClass");
            try {
                $normalized = $this->normalizer->normalizeResource($resourceClass);
                if ($normalized === null) {
                    $io->writeln("  ❌ Returned NULL");
                } else {
                    $io->writeln("  ✓ Normalized successfully");
                    $io->writeln("    Short name: " . $normalized->shortName);
                    $io->writeln("    Operations: " . count($normalized->operations));
                }
            } catch (\Throwable $e) {
                $io->writeln("  ❌ Error: " . $e->getMessage());
            }
        }

        // Then try normalizeAll
        $io->section('NormalizeAllResources()');
        try {
            $resources = $this->normalizer->normalizeAllResources();
            $io->success("Normalizer found " . count($resources) . " resources");

            foreach ($resources as $resource) {
                $io->section($resource->shortName);
                $io->writeln("Class: " . $resource->className);
                $io->writeln("Operations: " . count($resource->operations));
                foreach ($resource->operations as $op) {
                    $io->writeln("  - " . $op->name . " (" . $op->operationType->value . ")");
                }
            }
        } catch (\Throwable $e) {
            $io->error("Error: " . $e->getMessage());
            $io->writeln($e->getTraceAsString());
        }

        return Command::SUCCESS;
    }
}

