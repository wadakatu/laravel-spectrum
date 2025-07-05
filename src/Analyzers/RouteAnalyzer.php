<?php

namespace LaravelPrism\Analyzers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class RouteAnalyzer
{
    protected array $excludedMiddleware = ['web', 'api'];
    
    /**
     * APIルートを解析して構造化された配列を返す
     */
    public function analyze(): array
    {
        $routes = [];
        
        foreach (Route::getRoutes() as $route) {
            // APIルートのみを対象とする
            if (!$this->isApiRoute($route)) {
                continue;
            }
            
            $controller = $route->getController();
            $method = $route->getActionMethod();
            
            // コントローラーメソッドが存在しない場合はスキップ
            if (!$controller || $method === 'Closure') {
                continue;
            }
            
            $routes[] = [
                'uri' => $route->uri(),
                'httpMethods' => $route->methods(),
                'controller' => get_class($controller),
                'method' => $method,
                'name' => $route->getName(),
                'middleware' => $this->extractMiddleware($route),
                'parameters' => $this->extractRouteParameters($route),
            ];
        }
        
        return $routes;
    }
    
    /**
     * APIルートかどうかを判定
     */
    protected function isApiRoute($route): bool
    {
        $uri = $route->uri();
        $configPatterns = config('prism.route_patterns', ['api/*']);
        
        foreach ($configPatterns as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ルートパラメータを抽出
     */
    protected function extractRouteParameters($route): array
    {
        preg_match_all('/\{([^}]+)\}/', $route->uri(), $matches);
        
        $parameters = [];
        foreach ($matches[1] as $param) {
            $isOptional = Str::endsWith($param, '?');
            $name = rtrim($param, '?');
            
            $parameters[] = [
                'name' => $name,
                'required' => !$isOptional,
                'in' => 'path',
                'type' => 'string', // デフォルト、後で型推論で上書き可能
            ];
        }
        
        return $parameters;
    }
    
    /**
     * ミドルウェアを抽出
     */
    protected function extractMiddleware($route): array
    {
        return array_values(array_diff(
            $route->gatherMiddleware(),
            $this->excludedMiddleware
        ));
    }
}