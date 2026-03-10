<?php

declare(strict_types=1);

namespace App\Command;

use App\Generator\Config\GeneratorConfiguration;
use App\Generator\Normalizer\MetadataNormalizer;
use App\Generator\TypeScript\ResourceTypeGenerator;
use App\Generator\TypeScript\ResourceQueryGenerator;
use App\Generator\TypeScript\ResourceApiGenerator;
use App\Generator\TypeScript\ComposableGenerator;
use App\Generator\TypeScript\IndexGenerator;
use App\Generator\Writer\FileWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates TypeScript interface from API Platform resources.
 *
 * Usage:
 *   php bin/console app:generate-nuxt-interface
 *   php bin/console app:generate-nuxt-interface --output=/path/to/nuxt/project
 *   php bin/console app:generate-nuxt-interface --no-clean
 */
#[AsCommand(
    name: 'app:generate-nuxt-interface',
    description: 'Generates TypeScript types and API functions from API Platform resources',
)]
class GenerateNuxtInterfaceCommand extends Command
{
    public function __construct(
        private readonly GeneratorConfiguration $config,
        private readonly FileWriter $fileWriter,
        private readonly MetadataNormalizer $metadataNormalizer,
        private readonly ResourceTypeGenerator $typeGenerator,
        private readonly ResourceQueryGenerator $queryGenerator,
        private readonly ResourceApiGenerator $apiGenerator,
        private readonly ComposableGenerator $composableGenerator,
        private readonly IndexGenerator $indexGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output path (defaults to configuration value)',
            )
            ->addOption(
                'no-clean',
                null,
                InputOption::VALUE_NONE,
                'Do not clean generated directory before generating',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Nuxt Interface Generator');

        try {
            // Determine output path
            $outputPath = $input->getOption('output') ?? $this->config->outputPath;

            if (!$outputPath) {
                $io->error('Output path not specified. Set NUXT_GENERATOR_OUTPUT env var or use --output option.');
                return Command::FAILURE;
            }

            $io->section('Configuration');
            $io->writeln("Output path: <info>{$outputPath}</info>");
            $io->writeln("Clean before generate: <info>" . ($this->config->cleanBeforeGenerate ? 'yes' : 'no') . "</info>");

            // Initialize file writer
            $this->fileWriter->initialize($outputPath);

            // Clean if configured (unless --no-clean flag)
            if ($this->config->cleanBeforeGenerate && !$input->getOption('no-clean')) {
                $io->section('Cleaning');
                $io->writeln('Cleaning generated directory...');
                $this->fileWriter->cleanGeneratedDirectory();
                $io->success('Cleaned');
            }

            // Step 1: Extract and normalize metadata
            $io->section('Extracting metadata');
            $io->writeln('Analyzing API Platform resources...');
            $resources = $this->metadataNormalizer->normalizeAllResources();
            $io->success(sprintf('Found %d resources', count($resources)));

            if (empty($resources)) {
                $io->warning('No API Platform resources found. Nothing to generate.');
                return Command::SUCCESS;
            }

            // Display resources
            $io->section('Resources');
            foreach ($resources as $resource) {
                $operationCount = count($resource->operations);
                $io->writeln("  • <info>{$resource->shortName}</info> ({$operationCount} operations)");
            }

            // Step 2: Generate TypeScript types
            $io->section('Generating TypeScript types');
            $this->typeGenerator->generate($resources);
            $io->success('Generated type definitions');

            // Step 3: Generate query types
            $io->section('Generating query types');
            $this->queryGenerator->generate($resources);
            $io->success('Generated query types');

            // Step 4: Generate API functions
            $io->section('Generating API functions');
            $this->apiGenerator->generate($resources);
            $io->success('Generated API functions');

            // Step 5: Generate composables
            $io->section('Generating composables');
            $this->composableGenerator->generate($resources);
            $io->success('Generated composables');

            // Step 6: Generate index files
            $io->section('Generating index files');
            $this->indexGenerator->generate($resources);
            $io->success('Generated index files');

            // Final summary
            $io->section('Summary');
            $io->success([
                'Generation completed successfully!',
                '',
                "Generated files are in: {$outputPath}/generated/",
                '',
                'You can now import the generated API client in your Nuxt app:',
                '',
                "  import { getPrograms, createProgram } from '~/generated'",
                "  import type { Program, ProgramCreateInput } from '~/generated'",
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                'Generation failed:',
                $e->getMessage(),
                '',
                'Stack trace:',
                $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
