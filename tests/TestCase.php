<?php

namespace LaravelSpectrum\Tests;

use Illuminate\Routing\RouteCollection;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\SpectrumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
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

        return app(OpenApiGenerator::class)->generate($routes);
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
