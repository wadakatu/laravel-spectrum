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

        $environments = $this->parseEnvironments((string) $this->option('environments'));
        $isSingleFile = (bool) $this->option('single-file');
        $openapiArray = $openapi->toArray();
        $servers = $openapiArray['servers'] ?? [];
        $security = array_values($openapiArray['components']['securitySchemes'] ?? []);

        // Export collection
        $collection = $this->exporter->export($openapi);

        if ($isSingleFile) {
            $collection['x-laravel-spectrum-environments'] = $this->buildEmbeddedEnvironments(
                $servers,
                $security,
                $environments
            );
        }

        $collectionPath = $outputDir.'/postman_collection.json';
        File::put($collectionPath, json_encode($collection, JSON_PRETTY_PRINT));

        $this->info("✅ Collection exported to: {$collectionPath}");

        if ($isSingleFile) {
            $this->info('✅ Environments embedded into collection: '.implode(', ', $environments));
        } else {
            foreach ($environments as $env) {
                $environment = $this->exporter->exportEnvironment($servers, $security, $env);
                $envPath = $outputDir."/postman_environment_{$env}.json";
                File::put($envPath, json_encode($environment, JSON_PRETTY_PRINT));

                $this->info("✅ Environment '{$env}' exported to: {$envPath}");
            }
        }

        // Display import instructions
        $this->displayImportInstructions($collectionPath, $outputDir, $environments, $isSingleFile);

        return 0;
    }

    /**
     * @return array<int, string>
     */
    private function parseEnvironments(string $environments): array
    {
        $parsed = array_values(array_filter(array_map('trim', explode(',', $environments))));

        if ($parsed === []) {
            return ['local'];
        }

        return $parsed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $servers
     * @param  array<int, array<string, mixed>>  $security
     * @param  array<int, string>  $environments
     * @return array<string, array<string, mixed>>
     */
    private function buildEmbeddedEnvironments(array $servers, array $security, array $environments): array
    {
        $embeddedEnvironments = [];

        foreach ($environments as $env) {
            $embeddedEnvironments[$env] = $this->exporter->exportEnvironment($servers, $security, $env);
        }

        return $embeddedEnvironments;
    }

    private function displayImportInstructions(string $collectionPath, string $outputDir, array $environments, bool $isSingleFile): void
    {
        $this->newLine();
        $this->info('📚 Import Instructions:');
        $this->line('');
        $this->line('1. Open Postman');
        $this->line('2. Click "Import" button');
        if ($isSingleFile) {
            $this->line('3. Select the following file:');
            $this->line("   - Collection: {$collectionPath}");
            $this->line('');
            $this->line('4. Use embedded environment variables from the collection');
            $this->line('5. Set your authentication tokens in the collection variables');
            $this->line('6. Start testing your API! 🎉');

            // Newman execution example
            $this->newLine();
            $this->info('🏃 Run with Newman (CLI):');
            $this->line('');
            $this->line('npm install -g newman');
            $this->line("newman run {$collectionPath}");

            return;
        }

        $this->line('3. Select the following files:');
        $this->line("   - Collection: {$collectionPath}");

        foreach ($environments as $env) {
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
