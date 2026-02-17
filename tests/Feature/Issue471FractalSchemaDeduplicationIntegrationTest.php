<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\Fixtures\Controllers\Issue471FractalSchemaDeduplicationController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class Issue471FractalSchemaDeduplicationIntegrationTest extends TestCase
{
    #[Test]
    public function it_reuses_component_schema_references_for_shared_fractal_transformer(): void
    {
        $this->app['config']->set('spectrum.route_patterns', ['api/*']);
        $this->app['config']->set('spectrum.cache.enabled', false);
        $this->app->forgetInstance(DocumentationCache::class);
        $this->app->forgetInstance(RouteAnalyzer::class);

        $this->isolateRoutes(function () {
            Route::get('api/issue471/projects', [Issue471FractalSchemaDeduplicationController::class, 'index']);
            Route::get('api/issue471/projects/{project}', [Issue471FractalSchemaDeduplicationController::class, 'show']);
        });

        $openapi = $this->generateOpenApi();
        $components = $openapi['components']['schemas'];

        $listPath = null;
        $showPath = null;
        foreach (array_keys($openapi['paths']) as $path) {
            if (str_ends_with($path, '/issue471/projects')) {
                $listPath = $path;
            }

            if (str_contains($path, '/issue471/projects/{')) {
                $showPath = $path;
            }
        }

        $this->assertNotNull($listPath);
        $this->assertNotNull($showPath);

        $listResponseSchema = $openapi['paths'][$listPath]['get']['responses']['200']['content']['application/json']['schema'];
        $showResponseSchema = $openapi['paths'][$showPath]['get']['responses']['200']['content']['application/json']['schema'];

        $listSchemaRef = $listResponseSchema['properties']['data']['items']['$ref'] ?? null;
        $showSchemaRef = $showResponseSchema['properties']['data']['$ref'] ?? null;

        $this->assertNotNull($listSchemaRef);
        $this->assertNotNull($showSchemaRef);
        $this->assertSame($showSchemaRef, $listSchemaRef);

        $schemaName = str_replace('#/components/schemas/', '', $showSchemaRef);

        $this->assertArrayHasKey($schemaName, $components);
        $this->assertArrayHasKey('project_users', $components[$schemaName]['properties']);
    }
}
