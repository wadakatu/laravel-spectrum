<?php

namespace LaravelSpectrum\Tests;

use Illuminate\Routing\RouteCollection;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\SpectrumServiceProvider;
use LaravelSpectrum\Tests\Support\ValidatesOpenApi;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use ValidatesOpenApi;

    protected function setUp(): void
    {
        parent::setUp();

        // Some CI combinations can end up with the PHP default limit (128M) during long runs.
        // Enforce unlimited memory for package tests to match tests/bootstrap.php intent.
        ini_set('memory_limit', '-1');
    }

    protected function getPackageProviders($app)
    {
        return [
            SpectrumServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('spectrum.route_patterns', ['api/*']);
    }

    protected function generateOpenApi(): array
    {
        $routes = app(RouteAnalyzer::class)->analyze();

        return app(OpenApiGenerator::class)->generate($routes)->toArray();
    }

    /**
     * 完全にルートをクリアする
     */
    protected function clearAllRoutes(): void
    {
        $router = $this->app['router'];

        // 新しいRouteCollectionで置き換える
        $newCollection = new RouteCollection;

        // private propertyにアクセスしてルートコレクションを置き換える
        $reflection = new \ReflectionProperty($router, 'routes');
        $reflection->setAccessible(true);
        $reflection->setValue($router, $newCollection);

        // キャッシュもクリア
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
    }

    /**
     * 特定のルートのみを分離して設定する
     */
    protected function isolateRoutes(callable $routeDefinitions): void
    {
        $this->clearAllRoutes();
        $routeDefinitions();
    }
}
