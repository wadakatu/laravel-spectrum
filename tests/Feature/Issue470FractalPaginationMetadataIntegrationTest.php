<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\Fixtures\Controllers\Issue470FractalPaginationController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class Issue470FractalPaginationMetadataIntegrationTest extends TestCase
{
    #[Test]
    public function it_includes_manual_pagination_metadata_for_fractal_collection_response(): void
    {
        $this->app['config']->set('spectrum.route_patterns', ['api/*']);
        $this->app['config']->set('spectrum.cache.enabled', false);
        $this->app->forgetInstance(DocumentationCache::class);
        $this->app->forgetInstance(RouteAnalyzer::class);

        $this->isolateRoutes(function () {
            Route::get('api/issue470/projects', [Issue470FractalPaginationController::class, 'index']);
        });

        $openapi = $this->generateOpenApi();
        $targetPath = null;
        foreach (array_keys($openapi['paths']) as $path) {
            if (str_contains($path, 'issue470/projects')) {
                $targetPath = $path;
                break;
            }
        }

        $this->assertNotNull($targetPath);
        $responseSchema = $openapi['paths'][$targetPath]['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertSame('object', $responseSchema['type']);
        $this->assertArrayHasKey('data', $responseSchema['properties']);
        $this->assertArrayHasKey('next_cursor', $responseSchema['properties']);
        $this->assertSame('string', $responseSchema['properties']['next_cursor']['type']);
    }
}
