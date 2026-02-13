<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\Fixtures\Controllers\CallbackTestController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CallbackIntegrationTest extends TestCase
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
    public function it_generates_callbacks_in_openapi_spec(): void
    {
        Route::post('api/orders', [CallbackTestController::class, 'store']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $this->assertArrayHasKey('paths', $openapi);
        $this->assertArrayHasKey('/api/orders', $openapi['paths']);
        $this->assertArrayHasKey('post', $openapi['paths']['/api/orders']);

        $operation = $openapi['paths']['/api/orders']['post'];
        $this->assertArrayHasKey('callbacks', $operation);
        $this->assertArrayHasKey('onOrderStatusChange', $operation['callbacks']);

        $callback = $operation['callbacks']['onOrderStatusChange'];
        $this->assertArrayHasKey('{$request.body#/callbackUrl}', $callback);

        $pathItem = $callback['{$request.body#/callbackUrl}'];
        $this->assertArrayHasKey('post', $pathItem);

        $callbackOp = $pathItem['post'];
        $this->assertEquals('Notifies when order status changes', $callbackOp['description']);
        $this->assertArrayHasKey('requestBody', $callbackOp);
        $this->assertArrayHasKey('responses', $callbackOp);
    }

    #[Test]
    public function it_generates_multiple_callbacks_on_same_operation(): void
    {
        Route::put('api/orders/{order}', [CallbackTestController::class, 'update']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $operation = $openapi['paths']['/api/orders/{order}']['put'];
        $this->assertArrayHasKey('callbacks', $operation);
        $this->assertArrayHasKey('onPaymentComplete', $operation['callbacks']);
        $this->assertArrayHasKey('onShipmentUpdate', $operation['callbacks']);

        // Verify different HTTP methods
        $this->assertArrayHasKey('post', $operation['callbacks']['onPaymentComplete']['{$request.body#/callbackUrl}']);
        $this->assertArrayHasKey('put', $operation['callbacks']['onShipmentUpdate']['{$request.body#/shipmentCallbackUrl}']);
    }

    #[Test]
    public function it_does_not_include_callbacks_when_none_defined(): void
    {
        Route::get('api/orders', [CallbackTestController::class, 'index']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $operation = $openapi['paths']['/api/orders']['get'];
        $this->assertArrayNotHasKey('callbacks', $operation);
    }

    #[Test]
    public function it_generates_ref_callback_in_spec(): void
    {
        Route::post('api/orders/{order}/refund', [CallbackTestController::class, 'refund']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $operation = $openapi['paths']['/api/orders/{order}/refund']['post'];
        $this->assertArrayHasKey('callbacks', $operation);
        $this->assertArrayHasKey('onRefund', $operation['callbacks']);
        $this->assertEquals(
            ['$ref' => '#/components/callbacks/RefundCallback'],
            $operation['callbacks']['onRefund']
        );
    }

    #[Test]
    public function it_generates_callbacks_in_31_format(): void
    {
        config(['spectrum.openapi.version' => '3.1.0']);

        Route::post('api/orders', [CallbackTestController::class, 'store']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $this->assertEquals('3.1.0', $openapi['openapi']);

        $operation = $openapi['paths']['/api/orders']['post'];
        $this->assertArrayHasKey('callbacks', $operation);
        $this->assertArrayHasKey('onOrderStatusChange', $operation['callbacks']);
    }

    #[Test]
    public function it_includes_callbacks_from_config(): void
    {
        config([
            'spectrum.callbacks' => [
                CallbackTestController::class.'@index' => [
                    [
                        'name' => 'onConfiguredCallback',
                        'expression' => '{$request.body#/notifyUrl}',
                        'method' => 'post',
                        'description' => 'Configured via config file',
                    ],
                ],
            ],
        ]);

        // Re-bind CallbackAnalyzer with updated config
        $this->app->singleton(\LaravelSpectrum\Analyzers\CallbackAnalyzer::class, function () {
            return new \LaravelSpectrum\Analyzers\CallbackAnalyzer(
                configCallbacks: config('spectrum.callbacks', []),
            );
        });

        Route::get('api/orders', [CallbackTestController::class, 'index']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        $operation = $openapi['paths']['/api/orders']['get'];
        $this->assertArrayHasKey('callbacks', $operation);
        $this->assertArrayHasKey('onConfiguredCallback', $operation['callbacks']);
    }
}
