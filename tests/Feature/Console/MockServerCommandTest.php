<?php

namespace LaravelSpectrum\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use LaravelSpectrum\Console\Commands\MockServerCommand;
use LaravelSpectrum\MockServer\MockServer;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Workerman\Worker;

class MockServerCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test OpenAPI spec
        $this->createTestOpenApiSpec();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (File::exists(storage_path('app/spectrum/test-openapi.json'))) {
            File::delete(storage_path('app/spectrum/test-openapi.json'));
        }
        if (File::exists(storage_path('app/spectrum/openapi.json'))) {
            File::delete(storage_path('app/spectrum/openapi.json'));
        }

        parent::tearDown();
        Mockery::close();
    }

    public function test_command_requires_open_api_spec(): void
    {
        // Delete the default spec file
        File::delete(storage_path('app/spectrum/openapi.json'));

        $this->artisan('spectrum:mock')
            ->expectsOutput('OpenAPI specification file not found.')
            ->expectsOutput('Generate it first with: php artisan spectrum:generate')
            ->assertExitCode(1);
    }

    // Note: These tests are skipped because they would actually start a server
    // We'll only test the command loading and validation logic

    public function test_command_loads_open_api_spec(): void
    {
        // Create OpenAPI spec file in the default location
        $this->createDefaultOpenApiSpec();

        // Mock the MockServer to prevent actual server startup
        $this->mockMockServer();

        $this->artisan('spectrum:mock')
            ->expectsOutputToContain('Loading spec from:')
            ->expectsOutputToContain('Mock Server Configuration:')
            ->assertExitCode(0);
    }

    public function test_command_accepts_custom_port(): void
    {
        $this->createDefaultOpenApiSpec();
        
        // Mock the MockServer to prevent actual server startup
        $this->mockMockServer();

        $this->artisan('spectrum:mock', ['--port' => 9999])
            ->expectsOutput('🎭 Mock Server Configuration:')
            ->expectsOutputToContain('http://127.0.0.1:9999')
            ->assertExitCode(0);
    }

    public function test_command_accepts_custom_host(): void
    {
        $this->createDefaultOpenApiSpec();
        
        // Mock the MockServer to prevent actual server startup
        $this->mockMockServer();

        $this->artisan('spectrum:mock', ['--host' => '0.0.0.0'])
            ->expectsOutput('🎭 Mock Server Configuration:')
            ->expectsOutputToContain('http://0.0.0.0:8081')
            ->assertExitCode(0);
    }

    public function test_command_displays_startup_info(): void
    {
        $this->createDefaultOpenApiSpec();
        
        // Mock the MockServer to prevent actual server startup
        $this->mockMockServer();

        $this->artisan('spectrum:mock')
            ->expectsOutput('🚀 Starting Laravel Spectrum Mock Server...')
            ->expectsOutputToContain('📄 Loading spec from:')
            ->expectsOutput('🎭 Mock Server Configuration:')
            ->assertExitCode(0);
    }

    public function test_command_displays_available_endpoints(): void
    {
        $this->createDefaultOpenApiSpec();
        
        // Mock the MockServer to prevent actual server startup
        $this->mockMockServer();

        $this->artisan('spectrum:mock')
            ->expectsOutput('📋 Available Endpoints:')
            ->expectsTable(
                ['Method', 'Path', 'Description'],
                [
                    ['GET', '/api/users', 'Get all users'],
                    ['POST', '/api/users', 'Create a user'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_command_displays_usage_examples(): void
    {
        $this->createDefaultOpenApiSpec();
        
        // Mock the MockServer to prevent actual server startup
        $this->mockMockServer();

        $this->artisan('spectrum:mock')
            ->expectsOutput('🎯 Usage Examples:')
            ->expectsOutputToContain('curl http://127.0.0.1:8081/api/users')
            ->expectsOutputToContain('curl -X POST http://127.0.0.1:8081/api/users')
            ->assertExitCode(0);
    }

    public function test_command_displays_tips(): void
    {
        $this->createDefaultOpenApiSpec();
        
        // Mock the MockServer to prevent actual server startup
        $this->mockMockServer();

        $this->artisan('spectrum:mock')
            ->expectsOutput('💡 Tips:')
            ->expectsOutputToContain('Use ?_scenario=<scenario> to trigger different responses')
            ->expectsOutputToContain('Available scenarios: success, not_found, error, forbidden')
            ->assertExitCode(0);
    }
    
    public function test_command_handles_server_startup_failure(): void
    {
        $this->createDefaultOpenApiSpec();
        
        // Mock the MockServer to simulate startup failure
        $mockServer = Mockery::mock('overload:' . MockServer::class);
        $mockServer->shouldReceive('start')
            ->once()
            ->andThrow(new \Exception('Port already in use'));

        $this->artisan('spectrum:mock')
            ->expectsOutput('Failed to start server: Port already in use')
            ->assertExitCode(1);
    }
    
    public function test_command_accepts_custom_spec_path(): void
    {
        // Create custom spec file
        $customSpecPath = base_path('custom-openapi.json');
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Custom API',
                'version' => '2.0.0',
            ],
            'paths' => [],
        ];
        File::put($customSpecPath, json_encode($spec, JSON_PRETTY_PRINT));
        
        // Mock the MockServer to prevent actual server startup
        $this->mockMockServer();

        $this->artisan('spectrum:mock', ['--spec' => $customSpecPath])
            ->expectsOutputToContain('Loading spec from: ' . $customSpecPath)
            ->expectsOutput('🎭 Mock Server Configuration:')
            ->expectsOutputToContain('Custom API')
            ->expectsOutputToContain('2.0.0')
            ->assertExitCode(0);
            
        // Cleanup
        File::delete($customSpecPath);
    }

    private function createTestOpenApiSpec(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'summary' => 'Get all users',
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                            ],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Create a user',
                        'responses' => [
                            '201' => [
                                'description' => 'Created',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        File::ensureDirectoryExists(storage_path('app/spectrum'));
        File::put(
            storage_path('app/spectrum/test-openapi.json'),
            json_encode($spec, JSON_PRETTY_PRINT)
        );
    }
    
    private function createDefaultOpenApiSpec(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'summary' => 'Get all users',
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                            ],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Create a user',
                        'responses' => [
                            '201' => [
                                'description' => 'Created',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        File::ensureDirectoryExists(storage_path('app/spectrum'));
        File::put(
            storage_path('app/spectrum/openapi.json'),
            json_encode($spec, JSON_PRETTY_PRINT)
        );
    }
    
    private function mockMockServer(): void
    {
        // Mock the MockServer class to prevent actual server startup
        $mockServer = Mockery::mock('overload:' . MockServer::class);
        $mockServer->shouldReceive('start')->once();
    }
}
