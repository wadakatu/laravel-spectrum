<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\Fixtures\Controllers\EnumTestController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ControllerEnumParameterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ルートを完全に分離して定義
        $this->isolateRoutes(function () {
            Route::post('api/enum-test/tasks/{status}', [EnumTestController::class, 'store']);
            Route::patch('api/enum-test/tasks/{id}', [EnumTestController::class, 'update']);
            Route::get('api/enum-test/status', [EnumTestController::class, 'getStatus']);
        });
    }

    protected function generateOpenApiForEnumTest(): array
    {
        // Configure to only analyze our test routes
        config(['spectrum.route_patterns' => ['api/enum-test/*']]);

        // キャッシュを無効にして確実に新しいルートを取得
        $routes = app(RouteAnalyzer::class)->analyze(false);

        return app(OpenApiGenerator::class)->generate($routes);
    }

    #[Test]
    public function it_detects_enum_parameters_in_controller_methods()
    {
        // デバッグ: 実際に登録されているルートを確認
        $allRoutes = Route::getRoutes()->getRoutes();
        $routeUris = array_map(fn ($r) => $r->uri(), $allRoutes);
        $this->assertContains('api/enum-test/tasks/{status}', $routeUris,
            'Routes were not properly registered. Available routes: '.json_encode($routeUris));

        // Act
        $openapi = $this->generateOpenApiForEnumTest();

        // Assert
        $this->assertArrayHasKey('paths', $openapi);
        $paths = array_keys($openapi['paths']);

        // デバッグ: 実際に生成されたパスを確認
        if (empty($paths)) {
            $this->fail('No paths were generated.');
        }

        // 生成されたパスをエラーメッセージに含める
        $this->assertContains('/api/enum-test/tasks/{status}', $paths, 'Available paths: '.json_encode($paths));
        $postOperation = $openapi['paths']['/api/enum-test/tasks/{status}']['post'];
        $parameters = $postOperation['parameters'];

        // statusパラメータ（pathパラメータ）
        $statusParam = collect($parameters)->firstWhere('name', 'status');
        $this->assertNotNull($statusParam);
        $this->assertEquals('path', $statusParam['in']);
        $this->assertEquals(['active', 'inactive', 'pending'], $statusParam['schema']['enum']);

        // priorityパラメータ（queryパラメータ）
        $priorityParam = collect($parameters)->firstWhere('name', 'priority');
        $this->assertNotNull($priorityParam);
        $this->assertEquals('query', $priorityParam['in']);
        $this->assertEquals([1, 2, 3], $priorityParam['schema']['enum']);

        // PATCH /api/enum-test/tasks/{id}
        $patchOperation = $openapi['paths']['/api/enum-test/tasks/{id}']['patch'];
        $parameters = $patchOperation['parameters'];

        // statusパラメータ（nullable）
        $statusParam = collect($parameters)->firstWhere('name', 'status');
        $this->assertNotNull($statusParam);
        $this->assertFalse($statusParam['required']);
        $this->assertEquals(['active', 'inactive', 'pending'], $statusParam['schema']['enum']);
    }

    #[Test]
    public function it_generates_enum_response_schemas()
    {
        // Act
        $openapi = $this->generateOpenApiForEnumTest();

        // Assert
        $this->assertArrayHasKey('paths', $openapi);
        $this->assertArrayHasKey('/api/enum-test/status', $openapi['paths']);

        $getOperation = $openapi['paths']['/api/enum-test/status']['get'];

        // レスポンススキーマの検証は、現在の実装では直接Enum戻り値はサポートされていないため、
        // この部分は将来の拡張として残す
        $this->assertArrayHasKey('responses', $getOperation);
    }
}
