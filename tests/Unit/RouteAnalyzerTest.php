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

    #[Test]
    public function it_detects_integer_where_constraint(): void
    {
        // Arrange - Route with numeric constraint
        Route::get('api/users/{user}', [UserController::class, 'show'])
            ->where('user', '[0-9]+');

        // Act
        $routes = $this->analyzer->analyze();

        // Assert - Parameter should have integer type
        $this->assertCount(1, $routes);
        $this->assertCount(1, $routes[0]['parameters']);
        $this->assertEquals('user', $routes[0]['parameters'][0]['name']);
        $this->assertEquals('integer', $routes[0]['parameters'][0]['schema']['type']);
    }

    #[Test]
    public function it_detects_uuid_where_constraint(): void
    {
        // Arrange - Route with UUID constraint
        Route::get('api/orders/{order}', [UserController::class, 'show'])
            ->where('order', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');

        // Act
        $routes = $this->analyzer->analyze();

        // Assert - Parameter should have uuid format
        $this->assertCount(1, $routes);
        $this->assertCount(1, $routes[0]['parameters']);
        $this->assertEquals('order', $routes[0]['parameters'][0]['name']);
        $this->assertEquals('string', $routes[0]['parameters'][0]['schema']['type']);
        $this->assertEquals('uuid', $routes[0]['parameters'][0]['schema']['format']);
    }

    #[Test]
    public function it_detects_custom_pattern_constraint(): void
    {
        // Arrange - Route with custom pattern
        Route::get('api/products/{slug}', [UserController::class, 'show'])
            ->where('slug', '[a-z0-9-]+');

        // Act
        $routes = $this->analyzer->analyze();

        // Assert - Parameter should have pattern property
        $this->assertCount(1, $routes);
        $this->assertCount(1, $routes[0]['parameters']);
        $this->assertEquals('slug', $routes[0]['parameters'][0]['name']);
        $this->assertEquals('string', $routes[0]['parameters'][0]['schema']['type']);
        $this->assertEquals('^[a-z0-9-]+$', $routes[0]['parameters'][0]['schema']['pattern']);
    }

    #[Test]
    public function it_detects_multiple_where_constraints(): void
    {
        // Arrange - Route with multiple constraints
        Route::get('api/posts/{post}/comments/{comment}', [CommentController::class, 'show'])
            ->where(['post' => '[0-9]+', 'comment' => '[0-9]+']);

        // Act
        $routes = $this->analyzer->analyze();

        // Assert - Both parameters should have integer type
        $this->assertCount(1, $routes);
        $this->assertCount(2, $routes[0]['parameters']);
        $this->assertEquals('post', $routes[0]['parameters'][0]['name']);
        $this->assertEquals('integer', $routes[0]['parameters'][0]['schema']['type']);
        $this->assertEquals('comment', $routes[0]['parameters'][1]['name']);
        $this->assertEquals('integer', $routes[0]['parameters'][1]['schema']['type']);
    }

    #[Test]
    public function it_detects_alpha_where_constraint(): void
    {
        // Arrange - Route with alphabetic constraint
        Route::get('api/categories/{category}', [UserController::class, 'show'])
            ->whereAlpha('category');

        // Act
        $routes = $this->analyzer->analyze();

        // Assert - Parameter should have pattern for alphabetic
        $this->assertCount(1, $routes);
        $this->assertCount(1, $routes[0]['parameters']);
        $this->assertEquals('category', $routes[0]['parameters'][0]['name']);
        $this->assertEquals('string', $routes[0]['parameters'][0]['schema']['type']);
        $this->assertArrayHasKey('pattern', $routes[0]['parameters'][0]['schema']);
    }

    #[Test]
    public function it_detects_ulid_where_constraint(): void
    {
        // Arrange - Route with ULID constraint
        Route::get('api/items/{item}', [UserController::class, 'show'])
            ->whereUlid('item');

        // Act
        $routes = $this->analyzer->analyze();

        // Assert - Parameter should have ulid format
        $this->assertCount(1, $routes);
        $this->assertCount(1, $routes[0]['parameters']);
        $this->assertEquals('item', $routes[0]['parameters'][0]['name']);
        $this->assertEquals('string', $routes[0]['parameters'][0]['schema']['type']);
        // ULID uses pattern since there's no standard OpenAPI format for ULID
        $this->assertArrayHasKey('pattern', $routes[0]['parameters'][0]['schema']);
    }
}
