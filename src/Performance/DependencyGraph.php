<?php

declare(strict_types=1);

namespace LaravelSpectrum\Performance;

/**
 * @phpstan-type NodeData array<string, mixed>
 * @phpstan-type GraphNode array{id: string, data: NodeData, dependencies: list<string>, dependents: list<string>}
 * @phpstan-type RouteArray array{httpMethods: list<string>, uri: string, controller?: string, formRequest?: string, resource?: string}
 */
class DependencyGraph
{
    /** @var array<string, GraphNode> */
    private array $nodes = [];

    /**
     * Add a node to the graph
     *
     * @param  NodeData  $data
     */
    public function addNode(string $id, array $data = []): void
    {
        $this->nodes[$id] = [
            'id' => $id,
            'data' => $data,
            'dependencies' => [],
            'dependents' => [],
        ];
    }

    /**
     * Add an edge (dependency) between nodes
     */
    public function addEdge(string $from, string $to): void
    {
        if (! isset($this->nodes[$from])) {
            $this->addNode($from);
        }

        if (! isset($this->nodes[$to])) {
            $this->addNode($to);
        }

        $this->nodes[$from]['dependencies'][] = $to;
        $this->nodes[$to]['dependents'][] = $from;
    }

    /**
     * Get all nodes affected by changes to the given nodes
     *
     * @param  list<string>  $changedNodes
     * @return list<string>
     */
    public function getAffectedNodes(array $changedNodes): array
    {
        $affected = [];
        $visited = [];

        foreach ($changedNodes as $node) {
            $this->collectAffectedNodes($node, $affected, $visited);
        }

        return array_unique($affected);
    }

    /**
     * @param  list<string>  $affected
     * @param  list<string>  $visited
     */
    private function collectAffectedNodes(string $node, array &$affected, array &$visited): void
    {
        if (in_array($node, $visited)) {
            return;
        }

        $visited[] = $node;
        $affected[] = $node;

        if (isset($this->nodes[$node])) {
            // このノードに依存しているすべてのノードも影響を受ける
            foreach ($this->nodes[$node]['dependents'] as $dependent) {
                $this->collectAffectedNodes($dependent, $affected, $visited);
            }
        }
    }

    /**
     * Build dependency graph from routes
     *
     * @param  array<int, RouteArray>  $routes
     */
    public function buildFromRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $routeId = $this->getRouteId($route);
            $this->addNode($routeId, $route);

            // コントローラーへの依存
            if (isset($route['controller'])) {
                $this->addEdge($routeId, 'controller:'.$route['controller']);
            }

            // FormRequestへの依存
            if (isset($route['formRequest'])) {
                $this->addEdge($routeId, 'request:'.$route['formRequest']);
            }

            // Resourceへの依存
            if (isset($route['resource'])) {
                $this->addEdge($routeId, 'resource:'.$route['resource']);
            }
        }
    }

    /** @param  RouteArray  $route */
    private function getRouteId(array $route): string
    {
        return 'route:'.implode(':', $route['httpMethods']).':'.$route['uri'];
    }
}
