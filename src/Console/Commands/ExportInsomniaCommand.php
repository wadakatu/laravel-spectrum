<?php

namespace LaravelSpectrum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Exporters\InsomniaExporter;
use LaravelSpectrum\Generators\OpenApiGenerator;

class ExportInsomniaCommand extends Command
{
    protected $signature = 'spectrum:export:insomnia
                            {--output= : Output file path}';

    protected $description = 'Export API documentation as Insomnia collection';

    private InsomniaExporter $exporter;

    private RouteAnalyzer $routeAnalyzer;

    private OpenApiGenerator $openApiGenerator;

    public function __construct(
        InsomniaExporter $exporter,
        RouteAnalyzer $routeAnalyzer,
        OpenApiGenerator $openApiGenerator
    ) {
        parent::__construct();

        $this->exporter = $exporter;
        $this->routeAnalyzer = $routeAnalyzer;
        $this->openApiGenerator = $openApiGenerator;
    }

    public function handle(): int
    {
        $this->info('🚀 Exporting Insomnia collection...');

        // Generate OpenAPI document
        $routes = $this->routeAnalyzer->analyze();
        $openapi = $this->openApiGenerator->generate($routes);

        // Output file path
        $outputOption = $this->option('output');

        if ($outputOption) {
            // If output is a directory, append the filename
            if (is_dir($outputOption)) {
                $outputPath = rtrim($outputOption, '/').'/insomnia_collection.json';
            } else {
                $outputPath = $outputOption;
            }
        } else {
            $outputPath = storage_path('app/spectrum/insomnia/insomnia_collection.json');
        }

        File::ensureDirectoryExists(dirname($outputPath));

        // Export collection
        $collection = $this->exporter->export($openapi);
        File::put($outputPath, json_encode($collection, JSON_PRETTY_PRINT));

        $this->info("✅ Collection exported to: {$outputPath}");

        // Display import instructions
        $this->displayImportInstructions($outputPath);

        return 0;
    }

    private function displayImportInstructions(string $outputPath): void
    {
        $this->newLine();
        $this->info('📚 Import Instructions:');
        $this->line('');
        $this->line('1. Open Insomnia');
        $this->line('2. Go to Application → Preferences → Data → Import Data');
        $this->line('3. Select "From File"');
        $this->line("4. Choose: {$outputPath}");
        $this->line('5. Configure your environment variables');
        $this->line('6. Start testing your API! 🎉');

        // Git Sync info
        $this->newLine();
        $this->info('🔄 Git Sync (Team Collaboration):');
        $this->line('');
        $this->line('1. Enable Git Sync in Insomnia');
        $this->line('2. Commit the exported file to your repository');
        $this->line('3. Team members can pull and sync automatically');
    }
}
