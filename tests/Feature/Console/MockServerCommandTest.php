<?php

namespace LaravelSpectrum\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use LaravelSpectrum\Tests\TestCase;

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
    }

    public function test_command_requires_open_api_spec(): void
    {
        // Delete all default spec paths that the command searches
        $defaultPaths = [
            storage_path('app/spectrum/openapi.json'),
            base_path('openapi.json'),
            base_path('docs/openapi.json'),
        ];

        foreach ($defaultPaths as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        $this->artisan('spectrum:mock')
            ->expectsOutput('OpenAPI specification file not found.')
            ->expectsOutput('Generate it first with: php artisan spectrum:generate')
            ->assertExitCode(1);
    }

    public function test_command_rejects_yaml_spec_format(): void
    {
        // Create YAML spec file
        $yamlSpecPath = base_path('custom-openapi.yaml');
        File::put($yamlSpecPath, "openapi: '3.0.0'\ninfo:\n  title: Test API\n  version: '1.0.0'\n");

        $this->artisan('spectrum:mock', ['--spec' => $yamlSpecPath])
            ->expectsOutput('YAML format is not yet supported. Please use JSON format.')
            ->assertExitCode(1);

        // Cleanup
        File::delete($yamlSpecPath);
    }

    public function test_command_handles_nonexistent_spec_path(): void
    {
        $this->artisan('spectrum:mock', ['--spec' => '/nonexistent/path.json'])
            ->expectsOutput('OpenAPI specification file not found.')
            ->assertExitCode(1);
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
}
