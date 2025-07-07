<?php

namespace LaravelSpectrum\Tests;

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
}
