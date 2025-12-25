<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\Fixtures\Controllers\PostController;
use LaravelSpectrum\Tests\Fixtures\Controllers\ProfileController;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Snapshots\MatchesSnapshots;

/**
 * Snapshot tests to detect unexpected changes in OpenAPI output.
 *
 * These tests capture the generated OpenAPI specification as JSON snapshots.
 * When the output changes unexpectedly, the test fails, helping catch
 * unintended regressions in the documentation generator.
 *
 * To update snapshots after intentional changes, run:
 *   vendor/bin/phpunit -d --update-snapshots
 */
class OpenApiSnapshotTest extends TestCase
{
    use MatchesSnapshots;

    protected function setUp(): void
    {
        parent::setUp();
        app(DocumentationCache::class)->clear();
        config(['spectrum.route_patterns' => ['api/snapshot/*']]);
        // Use consistent config for reproducible snapshots
        config(['spectrum.title' => 'Snapshot Test API']);
        config(['spectrum.version' => '1.0.0']);
        config(['spectrum.description' => 'API for snapshot testing']);
        config(['app.url' => 'https://api.example.com']);
    }

    protected function tearDown(): void
    {
        app(DocumentationCache::class)->clear();
        parent::tearDown();
    }

    #[Test]
    public function basic_crud_routes_generate_consistent_output(): void
    {
        Route::prefix('api/snapshot')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::get('users/{user}', [UserController::class, 'show']);
        });

        $openapi = $this->generateOpenApi();

        // Remove dynamic fields that change between runs
        $openapi = $this->normalizeForSnapshot($openapi);

        $this->assertMatchesJsonSnapshot($openapi);
    }

    #[Test]
    public function authenticated_routes_generate_consistent_output(): void
    {
        Route::prefix('api/snapshot')->middleware('auth:sanctum')->group(function () {
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
        });

        $openapi = $this->generateOpenApi();
        $openapi = $this->normalizeForSnapshot($openapi);

        $this->assertMatchesJsonSnapshot($openapi);
    }

    #[Test]
    public function nested_resource_routes_generate_consistent_output(): void
    {
        Route::prefix('api/snapshot')->group(function () {
            Route::get('posts', [PostController::class, 'index']);
            Route::get('posts/{post}', [PostController::class, 'show']);
            Route::get('posts/{post}/comments', [PostController::class, 'comments']);
            Route::post('posts/{post}/comments', [PostController::class, 'storeComment']);
        });

        $openapi = $this->generateOpenApi();
        $openapi = $this->normalizeForSnapshot($openapi);

        $this->assertMatchesJsonSnapshot($openapi);
    }

    #[Test]
    public function openapi_31_generates_consistent_output(): void
    {
        config(['spectrum.openapi.version' => '3.1.0']);

        Route::prefix('api/snapshot')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
        });

        $openapi = $this->generateOpenApi();
        $openapi = $this->normalizeForSnapshot($openapi);

        $this->assertMatchesJsonSnapshot($openapi);
    }

    #[Test]
    public function tag_groups_generate_consistent_output(): void
    {
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User'],
                'Content' => ['Post'],
            ],
            'spectrum.tag_descriptions' => [
                'User' => 'User management endpoints',
                'Post' => 'Post management endpoints',
            ],
        ]);

        Route::prefix('api/snapshot')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::get('posts', [PostController::class, 'index']);
        });

        $openapi = $this->generateOpenApi();
        $openapi = $this->normalizeForSnapshot($openapi);

        $this->assertMatchesJsonSnapshot($openapi);
    }

    #[Test]
    public function empty_routes_generate_consistent_output(): void
    {
        // No routes registered for api/snapshot/* pattern

        $openapi = $this->generateOpenApi();
        $openapi = $this->normalizeForSnapshot($openapi);

        $this->assertMatchesJsonSnapshot($openapi);
    }

    /**
     * Normalize the OpenAPI spec for deterministic snapshot comparison.
     *
     * - Sorts paths, schemas, and tags alphabetically for consistent ordering
     * - Removes Faker-generated example objects
     * - Replaces datetime values with placeholders
     *
     * @param  array  $openapi  The OpenAPI specification
     * @return array The normalized specification
     */
    private function normalizeForSnapshot(array $openapi): array
    {
        // Sort paths for consistent ordering
        if (isset($openapi['paths']) && is_array($openapi['paths'])) {
            ksort($openapi['paths']);
        }

        // Sort component schemas if present
        if (isset($openapi['components']['schemas']) && is_array($openapi['components']['schemas'])) {
            ksort($openapi['components']['schemas']);
        }

        // Sort tags alphabetically by name
        if (isset($openapi['tags']) && is_array($openapi['tags'])) {
            usort($openapi['tags'], fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
        }

        // Recursively normalize dynamic values (Faker-generated examples, timestamps)
        return $this->normalizeDynamicValues($openapi);
    }

    /**
     * Recursively normalize dynamic values in the spec.
     *
     * @param  mixed  $data  The data structure to normalize (array or scalar)
     * @return mixed The normalized data with dynamic values replaced
     */
    private function normalizeDynamicValues(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $this->normalizeValue($data);
        }

        $result = [];
        foreach ($data as $key => $value) {
            // Remove Faker-generated example objects entirely for consistency
            if ($key === 'examples' && isset($value['default']['value'])) {
                continue;
            }

            $result[$key] = $this->normalizeDynamicValues($value);
        }

        return $result;
    }

    /**
     * Normalize individual values that may be dynamic.
     *
     * Replaces datetime strings with placeholders to ensure consistent snapshots.
     *
     * @param  mixed  $value  The value to normalize
     * @return mixed The normalized value (datetimes become placeholders)
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // Normalize datetime patterns (e.g., "2025-12-25 01:56:52")
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return '{{DATETIME}}';
        }

        // Normalize ISO datetime patterns (e.g., "2025-12-25T01:56:52Z")
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
            return '{{ISO_DATETIME}}';
        }

        return $value;
    }
}
