<?php

namespace LaravelSpectrum\Analyzers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LaravelSpectrum\Cache\DocumentationCache;

class RouteAnalyzer
{
    protected array $excludedMiddleware = ['web', 'api'];

    protected DocumentationCache $cache;

    public function __construct(DocumentationCache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * APIルートを解析して構造化された配列を返す
     */
    public function analyze(): array
    {
        return $this->cache->rememberRoutes(function () {
            return $this->performAnalysis();
        });
    }

    /**
     * Laravelのルートコレクションを強制的にリロード
     */
    public function reloadRoutes(): void
    {
        // ルートコレクションをクリア
        $router = app('router');

        // ルートコレクションのリセット
        $routeCollection = $router->getRoutes();
        $reflection = new \ReflectionProperty($routeCollection, 'routes');
        $reflection->setAccessible(true);
        $reflection->setValue($routeCollection, []);

        $reflection = new \ReflectionProperty($routeCollection, 'allRoutes');
        $reflection->setAccessible(true);
        $reflection->setValue($routeCollection, []);

        $reflection = new \ReflectionProperty($routeCollection, 'nameList');
        $reflection->setAccessible(true);
        $reflection->setValue($routeCollection, []);

        $reflection = new \ReflectionProperty($routeCollection, 'actionList');
        $reflection->setAccessible(true);
        $reflection->setValue($routeCollection, []);

        // ルートファイルを再読み込み
        $this->loadRouteFiles();
    }

    /**
     * ルートファイルを再読み込み
     */
    protected function loadRouteFiles(): void
    {
        // api.phpを優先的に読み込む
        $apiRoutePath = base_path('routes/api.php');
        if (file_exists($apiRoutePath)) {
            require $apiRoutePath;
        }

        // web.phpも読み込む（APIルートが含まれている場合があるため）
        $webRoutePath = base_path('routes/web.php');
        if (file_exists($webRoutePath)) {
            require $webRoutePath;
        }

        // カスタムルートファイルも読み込む
        $customRoutes = config('spectrum.route_files', []);
        foreach ($customRoutes as $routeFile) {
            if (file_exists($routeFile)) {
                require $routeFile;
            }
        }
    }

    /**
     * 実際のルート解析処理
     */
    protected function performAnalysis(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            // APIルートのみを対象とする
            if (! $this->isApiRoute($route)) {
                continue;
            }

            // クロージャールートをスキップ
            $action = $route->getAction();
            if (isset($action['uses']) && $action['uses'] instanceof \Closure) {
                continue;
            }

            $controller = $route->getController();
            $method = $route->getActionMethod();

            // コントローラーメソッドが存在しない場合はスキップ
            if (! $controller || $method === 'Closure' || ! is_object($controller)) {
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
        $configPatterns = config('spectrum.route_patterns', ['api/*']);

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
                'required' => ! $isOptional,
                'in' => 'path',
                'schema' => [
                    'type' => 'string', // デフォルト、後で型推論で上書き可能
                ],
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
