<?php

namespace LaravelSpectrum\Tests\Unit;

use Exception;
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class RouteAnalyzerAdvancedTest extends TestCase
{
    protected RouteAnalyzer $analyzer;

    protected DocumentationCache $cache;

    protected ErrorCollector $errorCollector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = Mockery::mock(DocumentationCache::class);
        $this->errorCollector = new ErrorCollector;
        $this->analyzer = new RouteAnalyzer($this->cache, $this->errorCollector);
    }

    #[Test]
    public function it_can_analyze_without_cache()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $routes = $this->analyzer->analyze(false);

        // Assert
        $this->assertCount(1, $routes);
        $this->assertEquals('api/users', $routes[0]['uri']);
    }

    #[Test]
    public function it_can_analyze_when_cache_is_disabled()
    {
        // Arrange
        $this->cache->shouldReceive('isEnabled')->andReturn(false);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $routes = $this->analyzer->analyze(true);

        // Assert
        $this->assertCount(1, $routes);
        $this->assertEquals('api/users', $routes[0]['uri']);
    }

    #[Test]
    public function it_can_reload_routes()
    {
        // Arrange - Create a temporary route file
        $tempFile = sys_get_temp_dir().'/reload_test_'.uniqid().'.php';
        file_put_contents($tempFile, '<?php
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;

Route::get("api/from-file", [UserController::class, "index"]);
');

        // Set up the route files config
        config(['spectrum.route_files' => [$tempFile]]);

        // Add a route that won't be in the file
        Route::get('api/test-before', [UserController::class, 'index']);

        // Act
        $this->analyzer->reloadRoutes();

        // Assert - routes from file should be loaded
        $fromFileExists = false;
        $testBeforeExists = false;

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === 'api/from-file') {
                $fromFileExists = true;
            }
            if ($route->uri() === 'api/test-before') {
                $testBeforeExists = true;
            }
        }

        $this->assertTrue($fromFileExists, 'Route from file should exist after reload');
        $this->assertFalse($testBeforeExists, 'Test route added before reload should not exist');

        // Cleanup
        unlink($tempFile);
    }

    #[Test]
    public function it_handles_reload_routes_errors_gracefully()
    {
        // Arrange
        $analyzer = new RouteAnalyzerWithFailingLoadRoutes($this->cache, $this->errorCollector);
        $router = app('router');
        $originalRoutes = clone $router->getRoutes();
        Route::get('api/test', [UserController::class, 'index']);
        $routeCountBefore = Route::getRoutes()->count();

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Forced failure in loadRouteFiles');

        try {
            $analyzer->reloadRoutes();
        } catch (Exception $e) {
            // Assert that routes were restored
            $this->assertEquals($routeCountBefore, Route::getRoutes()->count());
            throw $e;
        }
    }

    #[Test]
    public function it_loads_route_files_from_config()
    {
        // Arrange
        $tempDir = sys_get_temp_dir().'/spectrum_test_'.uniqid();
        mkdir($tempDir);
        $tempFile = $tempDir.'/test_routes.php';

        $routeContent = <<<'PHP'
<?php
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;

Route::get('api/custom-route', [UserController::class, 'index']);
PHP;

        file_put_contents($tempFile, $routeContent);
        config(['spectrum.route_files' => [$tempFile]]);

        // Act
        $this->analyzer->reloadRoutes();
        $routes = $this->analyzer->analyze(false);

        // Assert
        $customRouteFound = false;
        foreach ($routes as $route) {
            if ($route['uri'] === 'api/custom-route') {
                $customRouteFound = true;
                break;
            }
        }
        $this->assertTrue($customRouteFound, 'Custom route should be loaded');

        // Cleanup
        unlink($tempFile);
        rmdir($tempDir);
    }

    #[Test]
    public function it_handles_missing_route_files_gracefully()
    {
        // Arrange
        config(['spectrum.route_files' => ['/non/existent/file.php']]);

        // Act
        $this->analyzer->reloadRoutes();
        $routes = $this->analyzer->analyze(false);

        // Assert - should not throw exception and continue working
        $this->assertIsArray($routes);
        $this->assertEmpty($this->errorCollector->getErrors());
    }

    #[Test]
    public function it_handles_route_file_loading_errors()
    {
        // Arrange
        $tempFile = sys_get_temp_dir().'/error_routes_'.uniqid().'.php';
        file_put_contents($tempFile, '<?php throw new Exception("Route loading error");');

        config(['spectrum.route_files' => [$tempFile]]);

        // Act
        $this->analyzer->reloadRoutes();

        // Assert
        $errors = $this->errorCollector->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Failed to load route file', $errors[0]->message);
        $this->assertStringContainsString('Route loading error', $errors[0]->message);

        // Cleanup
        unlink($tempFile);
    }

    #[Test]
    public function it_handles_analysis_errors_for_individual_routes()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Create a problematic route using a mock controller
        $mockController = Mockery::mock();
        Route::get('api/problem', function () {
            throw new Exception('Controller instantiation failed');
        });

        // Replace the route action to simulate a controller error
        $routes = Route::getRoutes();
        foreach ($routes as $route) {
            if ($route->uri() === 'api/problem') {
                $route->setAction([
                    'uses' => 'ProblematicController@method',
                    'controller' => 'ProblematicController@method',
                ]);
            }
        }

        // Act
        $results = $this->analyzer->analyze(false);

        // Assert - the problematic route should be skipped
        $this->assertCount(1, $results);
        $this->assertEquals('api/users', $results[0]['uri']);
    }

    #[Test]
    public function it_extracts_complex_route_parameters()
    {
        // Arrange
        Route::get('api/users/{user}/posts/{post}/comments/{comment?}', [UserController::class, 'show']);
        Route::get('api/products/{product}/variants/{variant?}/prices/{price?}', [UserController::class, 'show']);

        // Act
        $routes = $this->analyzer->analyze(false);

        // Assert
        $this->assertCount(3, $routes[0]['parameters']);
        $this->assertTrue($routes[0]['parameters'][0]['required']);
        $this->assertTrue($routes[0]['parameters'][1]['required']);
        $this->assertFalse($routes[0]['parameters'][2]['required']);

        $this->assertCount(3, $routes[1]['parameters']);
        $this->assertTrue($routes[1]['parameters'][0]['required']);
        $this->assertFalse($routes[1]['parameters'][1]['required']);
        $this->assertFalse($routes[1]['parameters'][2]['required']);
    }

    #[Test]
    public function it_handles_routes_with_where_constraints()
    {
        // Arrange
        Route::get('api/users/{id}', [UserController::class, 'show'])->where('id', '[0-9]+');
        Route::get('api/posts/{slug}', [UserController::class, 'show'])->where('slug', '[a-z-]+');

        // Act
        $routes = $this->analyzer->analyze(false);

        // Assert
        $this->assertCount(1, $routes[0]['parameters']);
        $this->assertEquals('id', $routes[0]['parameters'][0]['name']);

        $this->assertCount(1, $routes[1]['parameters']);
        $this->assertEquals('slug', $routes[1]['parameters'][0]['name']);
    }

    #[Test]
    public function it_extracts_nested_middleware_groups()
    {
        // Arrange
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::middleware(['throttle:60,1'])->group(function () {
                Route::get('api/admin/users', [UserController::class, 'index']);
            });
        });

        // Act
        $routes = $this->analyzer->analyze(false);

        // Assert
        $this->assertContains('auth:sanctum', $routes[0]['middleware']);
        $this->assertContains('throttle:60,1', $routes[0]['middleware']);
    }

    #[Test]
    public function it_handles_route_names_correctly()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index'])->name('api.users.index');
        Route::post('api/users', [UserController::class, 'store'])->name('api.users.store');
        Route::get('api/users/{user}', [UserController::class, 'show']); // No name

        // Act
        $routes = $this->analyzer->analyze(false);

        // Assert
        $this->assertEquals('api.users.index', $routes[0]['name']);
        $this->assertEquals('api.users.store', $routes[1]['name']);
        $this->assertNull($routes[2]['name']);
    }

    #[Test]
    public function it_clears_opcache_when_available()
    {
        // This test verifies that opcache_invalidate is called when available
        // We can't directly test this, but we can ensure the code path executes without error

        // Arrange
        $tempFile = sys_get_temp_dir().'/opcache_test_'.uniqid().'.php';
        file_put_contents($tempFile, '<?php // Empty route file');

        config(['spectrum.route_files' => [$tempFile]]);

        // Act
        $this->analyzer->reloadRoutes();

        // Assert - no exception should be thrown
        $this->assertTrue(true);

        // Cleanup
        unlink($tempFile);
    }

    #[Test]
    public function it_skips_routes_without_valid_controller()
    {
        // Arrange
        // Add various types of routes that should be skipped
        Route::get('api/closure', function () {
            return 'closure';
        });
        Route::view('api/view', 'welcome');
        Route::redirect('api/redirect', '/somewhere');
        Route::get('api/valid', [UserController::class, 'index']);

        // Act
        $routes = $this->analyzer->analyze(false);

        // Assert - only the valid controller route should be included
        $validRoutes = array_filter($routes, function ($route) {
            return $route['uri'] === 'api/valid';
        });

        $this->assertCount(1, $validRoutes);
        $firstValidRoute = reset($validRoutes);
        $this->assertEquals('api/valid', $firstValidRoute['uri']);
        $this->assertEquals(UserController::class, $firstValidRoute['controller']);
    }

    #[Test]
    public function it_uses_cache_when_enabled()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        $this->cache->shouldReceive('isEnabled')->andReturn(true);
        $this->cache->shouldReceive('rememberRoutes')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Act
        $routes = $this->analyzer->analyze(true);

        // Assert
        $this->assertCount(1, $routes);
        $this->assertEquals('api/users', $routes[0]['uri']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

/**
 * Test helper class to force loadRouteFiles to fail
 */
class RouteAnalyzerWithFailingLoadRoutes extends RouteAnalyzer
{
    protected function loadRouteFiles(): void
    {
        throw new Exception('Forced failure in loadRouteFiles');
    }
}
