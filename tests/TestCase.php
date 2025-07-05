<?php

namespace LaravelPrism\Tests;

use LaravelPrism\Analyzers\RouteAnalyzer;
use LaravelPrism\Generators\OpenApiGenerator;
use LaravelPrism\PrismServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PrismServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('prism.route_patterns', ['api/*']);
    }

    protected function generateOpenApi(): array
    {
        $routes = app(RouteAnalyzer::class)->analyze();

        return app(OpenApiGenerator::class)->generate($routes);
    }
}
