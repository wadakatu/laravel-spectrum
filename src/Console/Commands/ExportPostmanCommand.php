<?php

namespace LaravelSpectrum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Exporters\PostmanExporter;
use LaravelSpectrum\Generators\OpenApiGenerator;

class ExportPostmanCommand extends Command
{
    protected $signature = 'spectrum:export:postman
                            {--output= : Output directory}
                            {--environments=local : Environments to export (comma-separated)}
                            {--single-file : Export as a single file with embedded environments}';

    protected $description = 'Export API documentation as Postman collection';

    private PostmanExporter $exporter;

    private RouteAnalyzer $routeAnalyzer;

    private OpenApiGenerator $openApiGenerator;

    public function __construct(
        PostmanExporter $exporter,
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
        $this->info('🚀 Exporting Postman collection...');

        // Generate OpenAPI document
        $routes = $this->routeAnalyzer->analyze();
        $openapi = $this->openApiGenerator->generate($routes);

        // Prepare output directory
        $outputDir = $this->option('output') ?? storage_path('app/spectrum/postman');
        File::ensureDirectoryExists($outputDir);

        // Export collection
        $collection = $this->exporter->export($openapi);
        $collectionPath = $outputDir.'/postman_collection.json';
        File::put($collectionPath, json_encode($collection, JSON_PRETTY_PRINT));

        $this->info("✅ Collection exported to: {$collectionPath}");

        // Export environments
        $environments = explode(',', $this->option('environments'));
        $openapiArray = $openapi->toArray();
        $servers = $openapiArray['servers'] ?? [];
        $security = $openapiArray['components']['securitySchemes'] ?? [];

        foreach ($environments as $env) {
            $env = trim($env);
            $environment = $this->exporter->exportEnvironment($servers, $security, $env);
            $envPath = $outputDir."/postman_environment_{$env}.json";
            File::put($envPath, json_encode($environment, JSON_PRETTY_PRINT));

            $this->info("✅ Environment '{$env}' exported to: {$envPath}");
        }

        // Display import instructions
        $this->displayImportInstructions($collectionPath, $outputDir, $environments);

        return 0;
    }

    private function displayImportInstructions(string $collectionPath, string $outputDir, array $environments): void
    {
        $this->newLine();
        $this->info('📚 Import Instructions:');
        $this->line('');
        $this->line('1. Open Postman');
        $this->line('2. Click "Import" button');
        $this->line('3. Select the following files:');
        $this->line("   - Collection: {$collectionPath}");

        foreach ($environments as $env) {
            $env = trim($env);
            $this->line("   - Environment: {$outputDir}/postman_environment_{$env}.json");
        }

        $this->line('');
        $this->line('4. Select an environment from the dropdown');
        $this->line('5. Set your authentication tokens in the environment variables');
        $this->line('6. Start testing your API! 🎉');

        // Newman execution example
        $this->newLine();
        $this->info('🏃 Run with Newman (CLI):');
        $this->line('');
        $this->line('npm install -g newman');
        $this->line("newman run {$collectionPath} -e {$outputDir}/postman_environment_local.json");
    }
}
