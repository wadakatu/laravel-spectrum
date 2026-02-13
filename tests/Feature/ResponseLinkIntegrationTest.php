<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\ResponseLinkAnalyzer;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\Fixtures\Controllers\ResponseLinkTestController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ResponseLinkIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(DocumentationCache::class)->clear();
    }

    protected function tearDown(): void
    {
        app(DocumentationCache::class)->clear();
        parent::tearDown();
    }

    #[Test]
    public function it_generates_response_links_from_attribute(): void
    {
        Route::post('api/users', [ResponseLinkTestController::class, 'store']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $response201 = $openapi['paths']['/api/users']['post']['responses']['201'];

        $this->assertArrayHasKey('links', $response201);
        $this->assertArrayHasKey('GetUserById', $response201['links']);
        $this->assertEquals('usersShow', $response201['links']['GetUserById']['operationId']);
        $this->assertEquals(
            '$response.body#/id',
            $response201['links']['GetUserById']['parameters']['user']
        );
    }

    #[Test]
    public function it_generates_response_links_from_config(): void
    {
        config([
            'spectrum.response_links' => [
                ResponseLinkTestController::class.'@index' => [
                    [
                        'statusCode' => 200,
                        'name' => 'GetFirstUser',
                        'operationId' => 'usersShow',
                        'parameters' => ['user' => '$response.body#/data/0/id'],
                    ],
                ],
            ],
        ]);

        // Re-bind analyzer with updated config
        $this->app->singleton(ResponseLinkAnalyzer::class, function () {
            return new ResponseLinkAnalyzer(
                configLinks: config('spectrum.response_links', []),
            );
        });

        Route::get('api/users', [ResponseLinkTestController::class, 'index']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $response200 = $openapi['paths']['/api/users']['get']['responses']['200'];
        $this->assertArrayHasKey('links', $response200);
        $this->assertArrayHasKey('GetFirstUser', $response200['links']);
        $this->assertEquals('usersShow', $response200['links']['GetFirstUser']['operationId']);
    }

    #[Test]
    public function it_generates_multiple_links_with_operation_ref_and_operation_id(): void
    {
        Route::get('api/users/{user}', [ResponseLinkTestController::class, 'show']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $response200 = $openapi['paths']['/api/users/{user}']['get']['responses']['200'];
        $this->assertArrayHasKey('links', $response200);
        $this->assertArrayHasKey('GetUserComments', $response200['links']);
        $this->assertArrayHasKey('GetUserPosts', $response200['links']);
        $this->assertArrayHasKey('operationRef', $response200['links']['GetUserComments']);
        $this->assertArrayHasKey('operationId', $response200['links']['GetUserPosts']);
    }

    #[Test]
    public function it_keeps_response_links_in_openapi_31_mode(): void
    {
        config(['spectrum.openapi.version' => '3.1.0']);
        Route::post('api/users', [ResponseLinkTestController::class, 'store']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $this->assertEquals('3.1.0', $openapi['openapi']);
        $response201 = $openapi['paths']['/api/users']['post']['responses']['201'];
        $this->assertArrayHasKey('links', $response201);
        $this->assertArrayHasKey('GetUserById', $response201['links']);
    }
}
