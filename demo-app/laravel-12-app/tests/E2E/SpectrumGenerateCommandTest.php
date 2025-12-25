<?php

namespace Tests\E2E;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * E2E tests for the spectrum:generate command.
 *
 * These tests verify that the Laravel Spectrum package works correctly
 * in a real Laravel application environment, testing the full workflow
 * from route analysis to OpenAPI generation.
 */
class SpectrumGenerateCommandTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = storage_path('app/spectrum');

        if (File::isDirectory($this->outputPath)) {
            File::cleanDirectory($this->outputPath);
        }
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->outputPath)) {
            File::cleanDirectory($this->outputPath);
        }

        parent::tearDown();
    }

    public function test_generate_command_creates_openapi_file(): void
    {
        $exitCode = Artisan::call('spectrum:generate');

        $this->assertEquals(0, $exitCode);
        $this->assertFileExists($this->outputPath.'/openapi.json');
    }

    public function test_generated_spec_is_valid_json(): void
    {
        Artisan::call('spectrum:generate');

        $content = File::get($this->outputPath.'/openapi.json');
        $spec = json_decode($content, true);

        $this->assertNotNull($spec, 'Generated file should contain valid JSON');
        $this->assertIsArray($spec);
    }

    public function test_generated_spec_has_required_openapi_fields(): void
    {
        Artisan::call('spectrum:generate');

        $spec = $this->getGeneratedSpec();

        // Required OpenAPI 3.x fields
        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);

        // Info section required fields
        $this->assertArrayHasKey('title', $spec['info']);
        $this->assertArrayHasKey('version', $spec['info']);

        // Version should be 3.0.x or 3.1.x
        $this->assertMatchesRegularExpression('/^3\.(0|1)\.\d+$/', $spec['openapi']);
    }

    public function test_generated_spec_contains_api_routes(): void
    {
        Artisan::call('spectrum:generate');

        $spec = $this->getGeneratedSpec();

        // Demo app has api routes defined
        $this->assertNotEmpty($spec['paths'], 'Paths should not be empty');

        // Check for some expected routes from api.php
        $paths = array_keys($spec['paths']);

        // At least one API path should exist
        $apiPaths = array_filter($paths, fn ($path) => str_starts_with($path, '/api'));
        $this->assertNotEmpty($apiPaths, 'Should have at least one /api path');
    }

    public function test_generated_spec_includes_authentication_routes(): void
    {
        Artisan::call('spectrum:generate');

        $spec = $this->getGeneratedSpec();
        $paths = array_keys($spec['paths']);

        // Check for auth routes
        $authPaths = array_filter($paths, fn ($path) => str_contains($path, 'auth'));
        $this->assertNotEmpty($authPaths, 'Should include authentication routes');
    }

    public function test_generated_spec_includes_crud_operations(): void
    {
        Artisan::call('spectrum:generate');

        $spec = $this->getGeneratedSpec();

        // Find users resource path
        $userPaths = array_filter(
            array_keys($spec['paths']),
            fn ($path) => str_contains($path, '/users')
        );

        $this->assertNotEmpty($userPaths, 'Should include user routes');

        // Check for CRUD methods on a users path
        foreach ($userPaths as $path) {
            $methods = array_keys($spec['paths'][$path]);
            // At least GET or POST should exist
            $hasCrudMethod = ! empty(array_intersect(['get', 'post', 'put', 'patch', 'delete'], $methods));
            if ($hasCrudMethod) {
                return; // Test passes
            }
        }

        $this->fail('Should have at least one CRUD method on user routes');
    }

    public function test_generated_spec_includes_request_bodies(): void
    {
        Artisan::call('spectrum:generate');

        $spec = $this->getGeneratedSpec();

        // Find POST routes and check for request bodies
        $hasRequestBody = false;
        foreach ($spec['paths'] as $path => $methods) {
            if (isset($methods['post']['requestBody'])) {
                $hasRequestBody = true;
                $requestBody = $methods['post']['requestBody'];

                $this->assertArrayHasKey('content', $requestBody);
                $this->assertArrayHasKey('application/json', $requestBody['content']);
                break;
            }
        }

        $this->assertTrue($hasRequestBody, 'Should have at least one POST route with request body');
    }

    public function test_generated_spec_includes_responses(): void
    {
        Artisan::call('spectrum:generate');

        $spec = $this->getGeneratedSpec();

        // All operations should have responses
        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
                    $this->assertArrayHasKey(
                        'responses',
                        $operation,
                        "Operation {$method} on {$path} should have responses"
                    );
                }
            }
        }
    }

    public function test_generated_spec_includes_path_parameters(): void
    {
        Artisan::call('spectrum:generate');

        $spec = $this->getGeneratedSpec();

        // Find paths with parameters (e.g., /users/{id})
        $parameterizedPaths = array_filter(
            array_keys($spec['paths']),
            fn ($path) => str_contains($path, '{')
        );

        $this->assertNotEmpty($parameterizedPaths, 'Should have routes with path parameters');

        // Check that parameters are properly defined
        foreach ($parameterizedPaths as $path) {
            $operations = $spec['paths'][$path];
            foreach ($operations as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
                    $this->assertArrayHasKey(
                        'parameters',
                        $operation,
                        "Parameterized path {$path} should have parameters defined"
                    );
                }
            }
        }
    }

    public function test_generate_command_with_yaml_format(): void
    {
        $exitCode = Artisan::call('spectrum:generate', ['--format' => 'yaml']);

        $this->assertEquals(0, $exitCode);
        $this->assertFileExists($this->outputPath.'/openapi.yaml');
    }

    public function test_generate_command_respects_route_patterns_config(): void
    {
        // The demo app config should filter routes
        Artisan::call('spectrum:generate');

        $spec = $this->getGeneratedSpec();

        // All paths should be API paths based on config
        foreach (array_keys($spec['paths']) as $path) {
            $this->assertStringStartsWith(
                '/api',
                $path,
                'All paths should start with /api based on route_patterns config'
            );
        }
    }

    public function test_generate_command_handles_malformed_routes_gracefully(): void
    {
        // The command should complete successfully even with routes that
        // have unusual configurations
        $exitCode = Artisan::call('spectrum:generate');

        // Command should succeed - malformed/unusual routes are skipped or handled gracefully
        $this->assertEquals(0, $exitCode);
        $this->assertFileExists($this->outputPath.'/openapi.json');
    }

    public function test_generate_command_with_no_cache_option(): void
    {
        // First generate with default caching
        Artisan::call('spectrum:generate');
        $this->assertFileExists($this->outputPath.'/openapi.json');

        // Generate again with --no-cache option
        $exitCode = Artisan::call('spectrum:generate', ['--no-cache' => true]);

        $this->assertEquals(0, $exitCode);
        $this->assertFileExists($this->outputPath.'/openapi.json');
    }

    /**
     * Get the generated OpenAPI spec as an array.
     *
     * @pre spectrum:generate command has been executed
     */
    private function getGeneratedSpec(): array
    {
        $content = File::get($this->outputPath.'/openapi.json');

        return json_decode($content, true);
    }
}
