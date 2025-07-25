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
        File::delete(storage_path('app/spectrum/test-openapi.json'));

        parent::tearDown();
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
        $this->markTestSkipped('Cannot test server startup in unit tests');
    }

    public function test_command_accepts_custom_port(): void
    {
        $this->markTestSkipped('Cannot test server startup in unit tests');
    }

    public function test_command_accepts_custom_host(): void
    {
        $this->markTestSkipped('Cannot test server startup in unit tests');
    }

    public function test_command_displays_startup_info(): void
    {
        $this->markTestSkipped('Cannot test server startup in unit tests');
    }

    public function test_command_displays_available_endpoints(): void
    {
        $this->markTestSkipped('Cannot test server startup in unit tests');
    }

    public function test_command_displays_usage_examples(): void
    {
        $this->markTestSkipped('Cannot test server startup in unit tests');
    }

    public function test_command_displays_tips(): void
    {
        $this->markTestSkipped('Cannot test server startup in unit tests');
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
}
