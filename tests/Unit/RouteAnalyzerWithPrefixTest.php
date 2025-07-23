<?php

namespace LaravelSpectrum\Tests\Unit;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\Fixtures\Controllers\PostController;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RouteAnalyzerWithPrefixTest extends TestCase
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
    public function it_detects_routes_with_prefix_groups()
    {
        // Arrange - Laravel 11スタイルのプレフィックスグループ
        Route::prefix('api')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('api.users.index');
            Route::post('users', [UserController::class, 'store'])->name('api.users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->name('api.users.show');
        });

        // プレフィックスなしのルート（APIではない）
        Route::get('web/about', function () {
            return 'about';
        })->name('web.about');

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertCount(3, $routes, 'Should detect 3 API routes');

        // 全てのルートがapi/プレフィックスを含むことを確認
        foreach ($routes as $route) {
            $this->assertStringStartsWith('api/', $route['uri']);
        }

        // 具体的なルートの確認
        $this->assertEquals('api/users', $routes[0]['uri']);
        $this->assertEquals('api/users', $routes[1]['uri']);
        $this->assertEquals('api/users/{user}', $routes[2]['uri']);
    }

    #[Test]
    public function it_detects_nested_prefix_groups()
    {
        // Arrange - ネストされたプレフィックスグループ
        Route::prefix('api')->group(function () {
            Route::prefix('v1')->group(function () {
                Route::get('users', [UserController::class, 'index'])->name('api.v1.users.index');
                Route::prefix('admin')->group(function () {
                    Route::get('users', [UserController::class, 'index'])->name('api.v1.admin.users.index');
                });
            });

            Route::prefix('v2')->group(function () {
                Route::get('users', [UserController::class, 'index'])->name('api.v2.users.index');
            });
        });

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertCount(3, $routes);
        $this->assertEquals('api/v1/users', $routes[0]['uri']);
        $this->assertEquals('api/v1/admin/users', $routes[1]['uri']);
        $this->assertEquals('api/v2/users', $routes[2]['uri']);
    }

    #[Test]
    public function it_respects_custom_route_patterns_with_prefix()
    {
        // Arrange
        config(['spectrum.route_patterns' => ['api/v2/*']]);

        Route::prefix('api')->group(function () {
            Route::prefix('v1')->group(function () {
                Route::get('users', [UserController::class, 'index']);
            });
            Route::prefix('v2')->group(function () {
                Route::get('users', [UserController::class, 'index']);
                Route::get('posts', [PostController::class, 'index']);
            });
        });

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertCount(2, $routes);
        $this->assertEquals('api/v2/users', $routes[0]['uri']);
        $this->assertEquals('api/v2/posts', $routes[1]['uri']);
    }

    #[Test]
    public function it_handles_laravel_11_style_api_routing()
    {
        // Arrange - Laravel 11のwithRouting設定をシミュレート
        // 実際のLaravel 11では、bootstrap/app.phpでapiPrefix: 'api'が設定されるが、
        // テストでは手動でプレフィックスを追加
        Route::prefix('api')->middleware('api')->group(function () {
            Route::apiResource('users', UserController::class);
        });

        // Act
        $routes = $this->analyzer->analyze();

        // Assert
        $this->assertGreaterThanOrEqual(4, count($routes)); // index, store, show, update, destroy

        $expectedRoutes = [
            'api/users',           // index, store
            'api/users/{user}',    // show, update, destroy
        ];

        $actualUris = array_unique(array_column($routes, 'uri'));
        foreach ($expectedRoutes as $expectedUri) {
            $this->assertContains($expectedUri, $actualUris);
        }
    }
}
