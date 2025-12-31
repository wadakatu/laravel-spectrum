<?php

namespace LaravelSpectrum\Tests\Unit;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\Fixtures\Controllers\CommentController;
use LaravelSpectrum\Tests\Fixtures\Controllers\HybridController;
use LaravelSpectrum\Tests\Fixtures\Controllers\InvokableTestController;
use LaravelSpectrum\Tests\Fixtures\Controllers\PageController;
use LaravelSpectrum\Tests\Fixtures\Controllers\ProfileController;
use LaravelSpectrum\Tests\Fixtures\Controllers\SearchController;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RouteAnalyzerTest extends TestCase
{
    protected RouteAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock cache that always calls the callback
        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberRoutes')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $this->analyzer = new RouteAnalyzer($cache);
    }

    #[Test]
    public function it_can_detect_api_routes()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index'])->name('users.index');
        Route::post('api/users', [UserController::class, 'store'])->name('users.store');
        Route::get('web/about', [PageController::class, 'about']);

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertCount(2, $routes);
        $this->assertEquals('api/users', $routes[0]['uri']);
    }

    #[Test]
    public function it_extracts_route_parameters()
    {
        // Arrange
        Route::get('api/users/{user}', [UserController::class, 'show']);
        Route::put('api/posts/{post}/comments/{comment?}', [CommentController::class, 'update']);

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertCount(1, $routes[0]['parameters']);
        $this->assertEquals('user', $routes[0]['parameters'][0]['name']);
        $this->assertTrue($routes[0]['parameters'][0]['required']);

        $this->assertCount(2, $routes[1]['parameters']);
        $this->assertFalse($routes[1]['parameters'][1]['required']);
    }

    #[Test]
    public function it_filters_routes_by_configured_patterns()
    {
        // Arrange
        config(['spectrum.route_patterns' => ['api/v1/*']]);
        Route::get('api/v1/users', [UserController::class, 'index']);
        Route::get('api/v2/users', [UserController::class, 'index']);

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertCount(1, $routes);
        $this->assertStringContainsString('api/v1', $routes[0]['uri']);
    }

    #[Test]
    public function it_extracts_http_methods()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/users', [UserController::class, 'store']);
        Route::match(['get', 'post'], 'api/search', [SearchController::class, 'search']);

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertEquals(['GET', 'HEAD'], $routes[0]['httpMethods']);
        $this->assertEquals(['POST'], $routes[1]['httpMethods']);
        $this->assertContains('GET', $routes[2]['httpMethods']);
        $this->assertContains('POST', $routes[2]['httpMethods']);
    }

    #[Test]
    public function it_extracts_middleware()
    {
        // Arrange
        Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
            Route::get('api/profile', [ProfileController::class, 'show']);
        });

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertContains('auth:sanctum', $routes[0]['middleware']);
        $this->assertContains('throttle:api', $routes[0]['middleware']);
    }

    #[Test]
    public function it_ignores_closure_routes()
    {
        // Arrange
        Route::get('api/test', function () {
            return 'test';
        });
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertCount(1, $routes);
        $this->assertEquals('api/users', $routes[0]['uri']);
    }

    #[Test]
    public function it_handles_invokable_controller_with_invoke_method(): void
    {
        // Arrange - Register an invokable controller route
        Route::post('api/invokable', InvokableTestController::class);

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertCount(1, $routes);
        $this->assertEquals('api/invokable', $routes[0]['uri']);
        $this->assertEquals(InvokableTestController::class, $routes[0]['controller']);
        // The method should be __invoke, not the class name
        $this->assertEquals('__invoke', $routes[0]['method']);
    }

    #[Test]
    public function it_preserves_method_name_for_regular_controllers(): void
    {
        // Arrange - Regular controller with explicit method
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/invokable', InvokableTestController::class);

        // Act
        $routes = $this->analyzer->analyze();

        // Assert - Regular controller keeps its method name
        $this->assertCount(2, $routes);

        // Find the regular controller route
        $regularRoute = collect($routes)->firstWhere('uri', 'api/users');
        $this->assertNotNull($regularRoute);
        $this->assertEquals('index', $regularRoute['method']);
        $this->assertNotEquals('__invoke', $regularRoute['method']);

        // Find the invokable controller route
        $invokableRoute = collect($routes)->firstWhere('uri', 'api/invokable');
        $this->assertNotNull($invokableRoute);
        $this->assertEquals('__invoke', $invokableRoute['method']);
    }

    #[Test]
    public function it_does_not_use_invoke_for_explicit_method_on_hybrid_controller(): void
    {
        // Arrange - Controller with both __invoke and regular methods
        // When called with explicit method, should NOT use __invoke
        Route::get('api/items', [HybridController::class, 'list']);

        // Act
        $routes = $this->analyzer->analyze();

        // Assert - Method should be 'list', not '__invoke'
        $this->assertCount(1, $routes);
        $this->assertEquals('api/items', $routes[0]['uri']);
        $this->assertEquals(HybridController::class, $routes[0]['controller']);
        $this->assertEquals('list', $routes[0]['method']);
        // Critical assertion: must NOT be __invoke even though controller has __invoke
        $this->assertNotEquals('__invoke', $routes[0]['method']);
    }
}
