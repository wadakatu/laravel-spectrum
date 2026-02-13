<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Console\Commands\OptimizedGenerateCommand;
use LaravelSpectrum\DTO\OpenApiSpec;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Performance\ChunkProcessor;
use LaravelSpectrum\Performance\DependencyGraph;
use LaravelSpectrum\Performance\MemoryManager;
use LaravelSpectrum\Performance\ParallelProcessor;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OptimizedGenerateCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function constructor_creates_default_dependencies(): void
    {
        $command = new OptimizedGenerateCommand;

        $this->assertInstanceOf(OptimizedGenerateCommand::class, $command);
    }

    #[Test]
    public function constructor_accepts_injected_dependencies(): void
    {
        $memoryManager = Mockery::mock(MemoryManager::class);
        $chunkProcessor = Mockery::mock(ChunkProcessor::class);
        $parallelProcessor = Mockery::mock(ParallelProcessor::class);
        $dependencyGraph = Mockery::mock(DependencyGraph::class);
        $routeAnalyzer = Mockery::mock(RouteAnalyzer::class);
        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);

        $command = new OptimizedGenerateCommand(
            $memoryManager,
            $chunkProcessor,
            $parallelProcessor,
            $dependencyGraph,
            $routeAnalyzer,
            $openApiGenerator
        );

        $this->assertInstanceOf(OptimizedGenerateCommand::class, $command);

        // Verify dependencies are stored via reflection
        $reflection = new \ReflectionClass($command);

        $memoryManagerProp = $reflection->getProperty('memoryManager');
        $memoryManagerProp->setAccessible(true);
        $this->assertSame($memoryManager, $memoryManagerProp->getValue($command));

        $chunkProcessorProp = $reflection->getProperty('chunkProcessor');
        $chunkProcessorProp->setAccessible(true);
        $this->assertSame($chunkProcessor, $chunkProcessorProp->getValue($command));

        $parallelProcessorProp = $reflection->getProperty('parallelProcessor');
        $parallelProcessorProp->setAccessible(true);
        $this->assertSame($parallelProcessor, $parallelProcessorProp->getValue($command));

        $dependencyGraphProp = $reflection->getProperty('dependencyGraph');
        $dependencyGraphProp->setAccessible(true);
        $this->assertSame($dependencyGraph, $dependencyGraphProp->getValue($command));

        $routeAnalyzerProp = $reflection->getProperty('routeAnalyzer');
        $routeAnalyzerProp->setAccessible(true);
        $this->assertSame($routeAnalyzer, $routeAnalyzerProp->getValue($command));

        $openApiGeneratorProp = $reflection->getProperty('openApiGenerator');
        $openApiGeneratorProp->setAccessible(true);
        $this->assertSame($openApiGenerator, $openApiGeneratorProp->getValue($command));
    }

    #[Test]
    public function constructor_creates_defaults_when_null_passed(): void
    {
        $command = new OptimizedGenerateCommand(null, null, null, null, null, null);

        $reflection = new \ReflectionClass($command);

        $memoryManagerProp = $reflection->getProperty('memoryManager');
        $memoryManagerProp->setAccessible(true);
        $this->assertInstanceOf(MemoryManager::class, $memoryManagerProp->getValue($command));

        $chunkProcessorProp = $reflection->getProperty('chunkProcessor');
        $chunkProcessorProp->setAccessible(true);
        $this->assertInstanceOf(ChunkProcessor::class, $chunkProcessorProp->getValue($command));

        $parallelProcessorProp = $reflection->getProperty('parallelProcessor');
        $parallelProcessorProp->setAccessible(true);
        $this->assertInstanceOf(ParallelProcessor::class, $parallelProcessorProp->getValue($command));

        $dependencyGraphProp = $reflection->getProperty('dependencyGraph');
        $dependencyGraphProp->setAccessible(true);
        $this->assertInstanceOf(DependencyGraph::class, $dependencyGraphProp->getValue($command));
    }

    #[Test]
    public function format_bytes_formats_correctly(): void
    {
        // Use reflection to test private method
        $command = new OptimizedGenerateCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatBytes');
        $method->setAccessible(true);

        $this->assertEquals('0 B', $method->invoke($command, 0));
        $this->assertEquals('512 B', $method->invoke($command, 512));
        $this->assertEquals('1 KB', $method->invoke($command, 1024));
        $this->assertEquals('1.5 KB', $method->invoke($command, 1536));
        $this->assertEquals('1 MB', $method->invoke($command, 1048576));
        $this->assertEquals('1 GB', $method->invoke($command, 1073741824));
    }

    #[Test]
    public function format_bytes_handles_negative_values(): void
    {
        $command = new OptimizedGenerateCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatBytes');
        $method->setAccessible(true);

        // Negative bytes (can happen with memory diff)
        $this->assertEquals('-512 B', $method->invoke($command, -512));
    }

    #[Test]
    public function combine_paths_merges_legacy_and_openapi_formats(): void
    {
        $command = new OptimizedGenerateCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('combinePaths');
        $method->setAccessible(true);

        $paths = [
            ['path' => '/api/users', 'methods' => ['get' => ['summary' => 'List users']]],
            ['/api/users' => ['post' => ['summary' => 'Create user']]],
            ['/api/posts' => ['get' => ['summary' => 'List posts']]],
        ];

        $result = $method->invoke($command, $paths);

        $this->assertArrayHasKey('/api/users', $result);
        $this->assertArrayHasKey('/api/posts', $result);
        $this->assertArrayHasKey('get', $result['/api/users']);
        $this->assertArrayHasKey('post', $result['/api/users']);
    }

    #[Test]
    public function combine_paths_handles_empty_array(): void
    {
        $command = new OptimizedGenerateCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('combinePaths');
        $method->setAccessible(true);

        $result = $method->invoke($command, []);

        $this->assertEmpty($result);
    }

    #[Test]
    public function combine_paths_skips_invalid_entries(): void
    {
        $command = new OptimizedGenerateCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('combinePaths');
        $method->setAccessible(true);

        $paths = [
            ['path' => '/api/users', 'methods' => ['get' => []]],
            ['invalid' => 'entry'],  // Missing 'path' and 'methods'
            ['path' => '/api/posts'],  // Missing 'methods'
        ];

        $result = $method->invoke($command, $paths);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('/api/users', $result);
    }

    #[Test]
    public function assemble_openapi_spec_merges_paths_components_and_metadata(): void
    {
        $command = new OptimizedGenerateCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('assembleOpenApiSpec');
        $method->setAccessible(true);

        $specs = [
            [
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Spec A',
                    'version' => '1.0.0',
                    'description' => 'First chunk',
                ],
                'paths' => [
                    '/api/users' => ['get' => ['operationId' => 'users.index']],
                ],
                'components' => [
                    'schemas' => [
                        'User' => ['type' => 'object'],
                    ],
                    'securitySchemes' => [
                        'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                    ],
                ],
                'security' => [
                    ['bearerAuth' => []],
                ],
                'tags' => [
                    ['name' => 'User', 'description' => 'User operations'],
                ],
                'x-tagGroups' => [
                    ['name' => 'Core', 'tags' => ['User']],
                ],
            ],
            [
                'openapi' => '3.1.0',
                'paths' => [
                    '/api/users' => ['post' => ['operationId' => 'users.store']],
                    '/api/posts' => ['get' => ['operationId' => 'posts.index']],
                ],
                'components' => [
                    'schemas' => [
                        'Post' => ['type' => 'object'],
                    ],
                    'callbacks' => [
                        'notifyEvent' => ['{$request.body#/callback}' => []],
                    ],
                ],
                'security' => [
                    ['bearerAuth' => []],
                    ['apiKeyAuth' => []],
                ],
                'tags' => [
                    ['name' => 'Post', 'description' => 'Post operations'],
                    ['name' => 'User', 'description' => 'Updated description'],
                ],
                'x-tagGroups' => [
                    ['name' => 'Core', 'tags' => ['Post']],
                    ['name' => 'Extra', 'tags' => ['Post']],
                ],
                'jsonSchemaDialect' => 'https://json-schema.org/draft/2020-12/schema',
            ],
        ];

        $result = $method->invoke($command, $specs);

        $this->assertArrayHasKey('openapi', $result);
        $this->assertEquals('3.1.0', $result['openapi']);
        $this->assertArrayHasKey('info', $result);
        $this->assertEquals('Spec A', $result['info']['title']);
        $this->assertEquals('1.0.0', $result['info']['version']);
        $this->assertEquals('First chunk', $result['info']['description']);
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('/api/users', $result['paths']);
        $this->assertArrayHasKey('/api/posts', $result['paths']);
        $this->assertArrayHasKey('get', $result['paths']['/api/users']);
        $this->assertArrayHasKey('post', $result['paths']['/api/users']);
        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('schemas', $result['components']);
        $this->assertArrayHasKey('securitySchemes', $result['components']);
        $this->assertArrayHasKey('User', $result['components']['schemas']);
        $this->assertArrayHasKey('Post', $result['components']['schemas']);
        $this->assertArrayHasKey('callbacks', $result['components']);
        $this->assertCount(2, $result['security']);
        $this->assertCount(2, $result['tags']);
        $this->assertEquals('Updated description', collect($result['tags'])->firstWhere('name', 'User')['description']);
        $this->assertCount(2, $result['x-tagGroups']);
        $this->assertEquals(['User', 'Post'], collect($result['x-tagGroups'])->firstWhere('name', 'Core')['tags']);
        $this->assertEquals('https://json-schema.org/draft/2020-12/schema', $result['jsonSchemaDialect']);
    }

    #[Test]
    public function assemble_openapi_spec_with_empty_specs_uses_spectrum_config(): void
    {
        $command = new OptimizedGenerateCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('assembleOpenApiSpec');
        $method->setAccessible(true);

        config([
            'spectrum.title' => 'Configured API',
            'spectrum.version' => '9.9.9',
            'spectrum.description' => 'Configured description',
            'spectrum.openapi.version' => '3.1.0',
        ]);

        $result = $method->invoke($command, []);

        $this->assertArrayHasKey('openapi', $result);
        $this->assertEquals('3.1.0', $result['openapi']);
        $this->assertEquals('Configured API', $result['info']['title']);
        $this->assertEquals('9.9.9', $result['info']['version']);
        $this->assertEquals('Configured description', $result['info']['description']);
        $this->assertArrayHasKey('paths', $result);
        $this->assertEmpty($result['paths']);
    }

    #[Test]
    public function assemble_openapi_spec_preserves_base_components_from_generator(): void
    {
        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);
        $openApiGenerator->shouldReceive('generate')
            ->once()
            ->with([])
            ->andReturn(OpenApiSpec::fromArray([
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Base API',
                    'version' => '1.0.0',
                    'description' => 'Base description',
                ],
                'servers' => [
                    ['url' => 'https://example.com', 'description' => 'Default server'],
                ],
                'paths' => [],
                'components' => [
                    'schemas' => [],
                    'securitySchemes' => [
                        'customAuth' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key'],
                    ],
                ],
            ]));

        $command = new OptimizedGenerateCommand(
            openApiGenerator: $openApiGenerator
        );
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('assembleOpenApiSpec');
        $method->setAccessible(true);

        $result = $method->invoke($command, [[
            'paths' => [
                '/api/users' => ['get' => ['operationId' => 'users.index']],
            ],
            'components' => [
                'schemas' => [
                    'User' => ['type' => 'object'],
                ],
            ],
        ]]);

        $this->assertArrayHasKey('/api/users', $result['paths']);
        $this->assertArrayHasKey('customAuth', $result['components']['securitySchemes']);
        $this->assertArrayHasKey('User', $result['components']['schemas']);
    }

    #[Test]
    public function command_has_correct_signature(): void
    {
        $command = new OptimizedGenerateCommand;

        $this->assertEquals('spectrum:generate:optimized', $command->getName());
        $this->assertStringContainsString('Generate API documentation', $command->getDescription());
    }

    #[Test]
    public function command_signature_includes_expected_options(): void
    {
        $command = new OptimizedGenerateCommand;
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('output'));
        $this->assertTrue($definition->hasOption('parallel'));
        $this->assertTrue($definition->hasOption('chunk-size'));
        $this->assertTrue($definition->hasOption('incremental'));
        $this->assertTrue($definition->hasOption('memory-limit'));
        $this->assertTrue($definition->hasOption('workers'));
    }

    #[Test]
    public function handle_via_artisan_returns_zero_when_no_routes(): void
    {
        $routeAnalyzer = Mockery::mock(RouteAnalyzer::class);
        $routeAnalyzer->shouldReceive('analyze')->andReturn([]);

        $this->app->instance(RouteAnalyzer::class, $routeAnalyzer);

        $this->artisan('spectrum:generate:optimized')
            ->expectsOutputToContain('No API routes found')
            ->assertExitCode(0);
    }

    #[Test]
    public function handle_via_artisan_processes_routes(): void
    {
        $routes = [
            [
                'uri' => '/api/users',
                'httpMethods' => ['GET'],
                'action' => 'UserController@index',
            ],
        ];

        $routeAnalyzer = Mockery::mock(RouteAnalyzer::class);
        $routeAnalyzer->shouldReceive('analyze')->andReturn($routes);

        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);
        $openApiGenerator->shouldReceive('generate')
            ->andReturn(OpenApiSpec::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0', 'description' => 'Test description'],
                'servers' => [['url' => 'https://example.com', 'description' => 'Test server']],
                'paths' => [
                    '/api/users' => ['get' => []],
                ],
                'components' => [
                    'schemas' => [],
                    'securitySchemes' => [],
                ],
            ]));

        $this->app->instance(RouteAnalyzer::class, $routeAnalyzer);
        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        $this->artisan('spectrum:generate:optimized')
            ->expectsOutputToContain('Found 1 routes to process')
            ->assertExitCode(0);
    }

    #[Test]
    public function handle_via_artisan_accepts_parallel_option(): void
    {
        // Keep route count low to avoid environment-dependent parallel execution during unit tests.
        $routes = [];
        for ($i = 1; $i <= 10; $i++) {
            $routes[] = [
                'uri' => "/api/resource{$i}",
                'httpMethods' => ['GET'],
                'action' => "Controller{$i}@index",
            ];
        }

        $routeAnalyzer = Mockery::mock(RouteAnalyzer::class);
        $routeAnalyzer->shouldReceive('analyze')->andReturn($routes);

        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);
        $openApiGenerator->shouldReceive('generate')
            ->andReturn(OpenApiSpec::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0', 'description' => 'Test description'],
                'servers' => [['url' => 'https://example.com', 'description' => 'Test server']],
                'paths' => [
                    '/api/test' => ['get' => []],
                ],
                'components' => [
                    'schemas' => [],
                    'securitySchemes' => [],
                ],
            ]));

        $this->app->instance(RouteAnalyzer::class, $routeAnalyzer);
        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        $this->artisan('spectrum:generate:optimized', ['--parallel' => true])
            ->expectsOutputToContain('Found 10 routes to process')
            ->assertExitCode(0);
    }

    #[Test]
    public function handle_via_artisan_returns_one_on_exception(): void
    {
        $routeAnalyzer = Mockery::mock(RouteAnalyzer::class);
        $routeAnalyzer->shouldReceive('analyze')
            ->andThrow(new \RuntimeException('Test exception'));

        $this->app->instance(RouteAnalyzer::class, $routeAnalyzer);

        $this->artisan('spectrum:generate:optimized')
            ->expectsOutputToContain('Error: Test exception')
            ->assertExitCode(1);
    }

    protected function getPackageProviders($app): array
    {
        return [
            \LaravelSpectrum\SpectrumServiceProvider::class,
        ];
    }
}
