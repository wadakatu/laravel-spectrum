<?php

namespace LaravelSpectrum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaravelSpectrum\Generators\DynamicExampleGenerator;
use LaravelSpectrum\MockServer\AuthenticationSimulator;
use LaravelSpectrum\MockServer\MockServer;
use LaravelSpectrum\MockServer\RequestHandler;
use LaravelSpectrum\MockServer\ResponseGenerator;
use LaravelSpectrum\MockServer\RouteResolver;
use LaravelSpectrum\MockServer\ValidationSimulator;

class MockServerCommand extends Command
{
    protected $signature = 'spectrum:mock
                            {--host=127.0.0.1 : The host to bind to}
                            {--port=8081 : The port to listen on}
                            {--spec= : Path to OpenAPI specification file}
                            {--delay= : Response delay in milliseconds}
                            {--scenario=success : Default response scenario}';

    protected $description = 'Start a mock server based on OpenAPI documentation';

    public function handle(): int
    {
        $this->info('🚀 Starting Laravel Spectrum Mock Server...');

        // OpenAPI仕様の読み込み
        $openapi = $this->loadOpenApiSpec();

        if (! $openapi) {
            $this->error('Failed to load OpenAPI specification.');

            return 1;
        }

        // サーバー設定
        $host = $this->option('host');
        $port = (int) $this->option('port');

        // 依存関係の構築
        $exampleGenerator = new DynamicExampleGenerator;
        $validator = new ValidationSimulator;
        $authenticator = new AuthenticationSimulator;
        $responseGenerator = new ResponseGenerator($exampleGenerator);
        $routeResolver = new RouteResolver;

        $requestHandler = new RequestHandler(
            $validator,
            $authenticator,
            $responseGenerator
        );

        // レスポンス遅延の設定
        if ($delay = $this->option('delay')) {
            // Note: setResponseDelay method would need to be implemented if delay is needed
            // $requestHandler->setResponseDelay((int) $delay);
        }

        // モックサーバーの起動
        $server = new MockServer(
            $openapi,
            $requestHandler,
            $routeResolver,
            $host,
            $port
        );

        $this->displayStartupInfo($openapi, $host, $port);

        try {
            $server->start();
        } catch (\Exception $e) {
            $this->error('Failed to start server: '.$e->getMessage());

            return 1;
        }

        return 0;
    }

    private function loadOpenApiSpec(): ?array
    {
        $specPath = $this->option('spec');

        if (! $specPath) {
            // デフォルトパスを試す
            $defaultPaths = [
                storage_path('app/spectrum/openapi.json'),
                base_path('openapi.json'),
                base_path('docs/openapi.json'),
            ];

            foreach ($defaultPaths as $path) {
                if (File::exists($path)) {
                    $specPath = $path;
                    break;
                }
            }
        }

        if (! $specPath || ! File::exists($specPath)) {
            $this->error('OpenAPI specification file not found.');
            $this->line('Generate it first with: php artisan spectrum:generate');

            return null;
        }

        $content = File::get($specPath);
        $this->info("📄 Loading spec from: {$specPath}");

        // JSONまたはYAMLをパース
        if (str_ends_with($specPath, '.yaml') || str_ends_with($specPath, '.yml')) {
            // For now, only support JSON. YAML support can be added with symfony/yaml
            $this->error('YAML format is not yet supported. Please use JSON format.');

            return null;
        }

        return json_decode($content, true);
    }

    private function displayStartupInfo(array $openapi, string $host, int $port): void
    {
        $this->newLine();
        $this->info('🎭 Mock Server Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['API Title', $openapi['info']['title'] ?? 'Unknown'],
                ['API Version', $openapi['info']['version'] ?? '1.0.0'],
                ['Server URL', "http://{$host}:{$port}"],
                ['Total Endpoints', (string) count($openapi['paths'] ?? [])],
                ['Default Scenario', $this->option('scenario')],
            ]
        );

        $this->newLine();
        $this->info('📋 Available Endpoints:');

        $endpoints = [];
        foreach ($openapi['paths'] ?? [] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
                    $endpoints[] = [
                        strtoupper($method),
                        $path,
                        $operation['summary'] ?? '-',
                    ];
                }
            }
        }

        $this->table(['Method', 'Path', 'Description'], array_slice($endpoints, 0, 10));

        if (count($endpoints) > 10) {
            $this->line('... and '.(count($endpoints) - 10).' more endpoints');
        }

        $this->newLine();
        $this->info('🎯 Usage Examples:');
        $this->line("  curl http://{$host}:{$port}/api/users");
        $this->line("  curl -X POST http://{$host}:{$port}/api/users -H 'Content-Type: application/json' -d '{\"name\":\"John\"}'");
        $this->line("  curl http://{$host}:{$port}/api/users/123?_scenario=not_found");

        $this->newLine();
        $this->info('💡 Tips:');
        $this->line('  - Use ?_scenario=<scenario> to trigger different responses');
        $this->line('  - Available scenarios: success, not_found, error, forbidden');
        $this->line('  - Add Authorization header for authenticated endpoints');
        $this->line('  - All validation rules from your API are active');

        $this->newLine();
    }
}
