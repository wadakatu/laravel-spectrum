<?php

namespace Tests\Unit\Performance;

use LaravelSpectrum\Performance\DependencyGraph;
use PHPUnit\Framework\TestCase;

class DependencyGraphTest extends TestCase
{
    private DependencyGraph $graph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graph = new DependencyGraph;
    }

    public function test_add_node_stores_node_data(): void
    {
        $this->graph->addNode('node1', ['name' => 'Test Node']);

        // ノードが正しく追加されたことを確認（getAffectedNodesを使用して間接的に確認）
        $affected = $this->graph->getAffectedNodes(['node1']);
        $this->assertEquals(['node1'], $affected);
    }

    public function test_add_edge_creates_node_if_not_exists(): void
    {
        $this->graph->addEdge('node1', 'node2');

        // 両方のノードが作成され、エッジが設定されたことを確認
        $affected = $this->graph->getAffectedNodes(['node2']);
        $this->assertContains('node1', $affected);
        $this->assertContains('node2', $affected);
    }

    public function test_get_affected_nodes_with_simple_dependency(): void
    {
        // A -> B という依存関係を作成
        $this->graph->addEdge('A', 'B');

        // Bが変更された場合、Aも影響を受ける
        $affected = $this->graph->getAffectedNodes(['B']);
        $this->assertCount(2, $affected);
        $this->assertContains('A', $affected);
        $this->assertContains('B', $affected);
    }

    public function test_get_affected_nodes_with_chained_dependencies(): void
    {
        // A -> B -> C という依存関係を作成
        $this->graph->addEdge('A', 'B');
        $this->graph->addEdge('B', 'C');

        // Cが変更された場合、B、Aも影響を受ける
        $affected = $this->graph->getAffectedNodes(['C']);
        $this->assertCount(3, $affected);
        $this->assertContains('A', $affected);
        $this->assertContains('B', $affected);
        $this->assertContains('C', $affected);
    }

    public function test_get_affected_nodes_with_multiple_dependencies(): void
    {
        // 複数の依存関係を作成
        // A -> C
        // B -> C
        $this->graph->addEdge('A', 'C');
        $this->graph->addEdge('B', 'C');

        // Cが変更された場合、AとBも影響を受ける
        $affected = $this->graph->getAffectedNodes(['C']);
        $this->assertCount(3, $affected);
        $this->assertContains('A', $affected);
        $this->assertContains('B', $affected);
        $this->assertContains('C', $affected);
    }

    public function test_get_affected_nodes_with_circular_dependency(): void
    {
        // 循環依存を作成: A -> B -> C -> A
        $this->graph->addEdge('A', 'B');
        $this->graph->addEdge('B', 'C');
        $this->graph->addEdge('C', 'A');

        // Aが変更された場合、すべてのノードが影響を受ける
        $affected = $this->graph->getAffectedNodes(['A']);
        $this->assertCount(3, $affected);
        $this->assertContains('A', $affected);
        $this->assertContains('B', $affected);
        $this->assertContains('C', $affected);
    }

    public function test_get_affected_nodes_with_multiple_changed_nodes(): void
    {
        // 複数の独立したグラフを作成
        $this->graph->addEdge('A', 'B');
        $this->graph->addEdge('C', 'D');

        // BとDが変更された場合
        $affected = $this->graph->getAffectedNodes(['B', 'D']);
        $this->assertCount(4, $affected);
        $this->assertContains('A', $affected);
        $this->assertContains('B', $affected);
        $this->assertContains('C', $affected);
        $this->assertContains('D', $affected);
    }

    public function test_get_affected_nodes_with_non_existent_node(): void
    {
        $this->graph->addEdge('A', 'B');

        // 存在しないノードを指定
        $affected = $this->graph->getAffectedNodes(['NonExistent']);
        $this->assertEquals(['NonExistent'], $affected);
    }

    public function test_build_from_routes_creates_correct_dependencies(): void
    {
        $routes = [
            [
                'httpMethods' => ['GET'],
                'uri' => '/api/users',
                'controller' => 'UserController',
                'formRequest' => 'UserRequest',
                'resource' => 'UserResource',
            ],
            [
                'httpMethods' => ['POST'],
                'uri' => '/api/posts',
                'controller' => 'PostController',
            ],
        ];

        $this->graph->buildFromRoutes($routes);

        // UserControllerが変更された場合、対応するルートも影響を受ける
        $affected = $this->graph->getAffectedNodes(['controller:UserController']);
        $this->assertContains('route:GET:/api/users', $affected);

        // UserRequestが変更された場合
        $affected = $this->graph->getAffectedNodes(['request:UserRequest']);
        $this->assertContains('route:GET:/api/users', $affected);

        // UserResourceが変更された場合
        $affected = $this->graph->getAffectedNodes(['resource:UserResource']);
        $this->assertContains('route:GET:/api/users', $affected);
    }

    public function test_build_from_routes_with_multiple_http_methods(): void
    {
        $routes = [
            [
                'httpMethods' => ['GET', 'POST'],
                'uri' => '/api/items',
                'controller' => 'ItemController',
            ],
        ];

        $this->graph->buildFromRoutes($routes);

        // 複数のHTTPメソッドが正しく処理される
        $affected = $this->graph->getAffectedNodes(['controller:ItemController']);
        $this->assertContains('route:GET:POST:/api/items', $affected);
    }

    public function test_empty_graph_returns_only_changed_nodes(): void
    {
        // 空のグラフで変更されたノードのみが返される
        $affected = $this->graph->getAffectedNodes(['A', 'B']);
        $this->assertEquals(['A', 'B'], $affected);
    }

    public function test_adding_duplicate_edges(): void
    {
        // 同じエッジを複数回追加
        $this->graph->addEdge('A', 'B');
        $this->graph->addEdge('A', 'B');

        // 重複したエッジが正しく処理される
        $affected = $this->graph->getAffectedNodes(['B']);
        $this->assertContains('A', $affected);
    }
}
