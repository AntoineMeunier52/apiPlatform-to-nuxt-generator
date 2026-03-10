<?php

namespace App\Command;

use App\Generator\Extractor\ApiPlatformMetadataExtractor;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'debug:extractor', description: 'Debug resource extractor')]
class DebugExtractorCommand extends Command
{
    public function __construct(
        private readonly ApiPlatformMetadataExtractor $extractor,
        private readonly ResourceNameCollectionFactoryInterface $factory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Debug API Platform Resource Extractor');

        // Test direct via factory
        $io->section('Direct factory test');
        try {
            $collection = $this->factory->create();
            $count = 0;
            $resources = [];
            foreach ($collection as $resourceClass) {
                $count++;
                $resources[] = $resourceClass;
            }
            $io->success("Factory found $count resources");
            foreach ($resources as $r) {
                $io->writeln("  - $r");
            }
        } catch (\Throwable $e) {
            $io->error("Factory error: " . $e->getMessage());
        }

        // Test via our extractor
        $io->section('Extractor test');
        try {
            $resources = $this->extractor->getResourceClasses();
            $io->success("Extractor found " . count($resources) . " resources");
            foreach ($resources as $r) {
                $io->writeln("  - $r");
            }
        } catch (\Throwable $e) {
            $io->error("Extractor error: " . $e->getMessage());
            $io->writeln($e->getTraceAsString());
        }

        return Command::SUCCESS;
    }
}

