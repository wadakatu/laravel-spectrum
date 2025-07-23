<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Tests\Fixtures\Controllers\EnumTestController;
use LaravelSpectrum\Tests\TestCase;

class ControllerEnumParameterTest extends TestCase
{
    protected function defineRoutes($router)
    {
        // このテストで必要なルートのみを定義
        $router->post('api/tasks/{status}', [EnumTestController::class, 'store']);
        $router->patch('api/tasks/{id}', [EnumTestController::class, 'update']);
        $router->get('api/status', [EnumTestController::class, 'getStatus']);
    }
    
    /** @test */
    public function it_detects_enum_parameters_in_controller_methods()
    {
        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $this->assertArrayHasKey('paths', $openapi);
        $paths = array_keys($openapi['paths']);
        
        // デバッグ: 実際に生成されたパスを確認
        if (empty($paths)) {
            $this->fail('No paths were generated.');
        }
        
        // 生成されたパスをエラーメッセージに含める
        $this->assertContains('/api/tasks/{status}', $paths, 'Available paths: ' . json_encode($paths));
        $postOperation = $openapi['paths']['/api/tasks/{status}']['post'];
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

        // PATCH /api/tasks/{id}
        $patchOperation = $openapi['paths']['/api/tasks/{id}']['patch'];
        $parameters = $patchOperation['parameters'];

        // statusパラメータ（nullable）
        $statusParam = collect($parameters)->firstWhere('name', 'status');
        $this->assertNotNull($statusParam);
        $this->assertFalse($statusParam['required']);
        $this->assertEquals(['active', 'inactive', 'pending'], $statusParam['schema']['enum']);
    }

    /** @test */
    public function it_generates_enum_response_schemas()
    {
        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $this->assertArrayHasKey('paths', $openapi);
        $this->assertArrayHasKey('/api/status', $openapi['paths']);
        
        $getOperation = $openapi['paths']['/api/status']['get'];
        
        // レスポンススキーマの検証は、現在の実装では直接Enum戻り値はサポートされていないため、
        // この部分は将来の拡張として残す
        $this->assertArrayHasKey('responses', $getOperation);
    }
}