<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\HasErrorCollection;

class RouteAnalyzer implements HasErrors
{
    use HasErrorCollection;

    protected array $excludedMiddleware = ['web', 'api'];

    protected DocumentationCache $cache;

    public function __construct(DocumentationCache $cache, ?ErrorCollector $errorCollector = null)
    {
        $this->initializeErrorCollector($errorCollector);
        $this->cache = $cache;
    }

    /**
     * APIルートを解析して構造化された配列を返す
     */
    public function analyze(bool $useCache = true): array
    {
        if (! $useCache || ! $this->cache->isEnabled()) {
            return $this->performAnalysis();
        }

        return $this->cache->rememberRoutes(function () {
            return $this->performAnalysis();
        });
    }

    /**
     * Laravelのルートコレクションを強制的にリロード
     */
    public function reloadRoutes(): void
    {
        // 現在のルートコレクションをバックアップ
        $router = app('router');
        $oldRoutes = clone $router->getRoutes();

        try {
            // 新しいルートコレクションを作成
            $newRouteCollection = new \Illuminate\Routing\RouteCollection;
            $router->setRoutes($newRouteCollection);

            // ルートファイルを再読み込み
            $this->loadRouteFiles();

            // 成功したら新しいルートコレクションを使用
            Route::getRoutes()->refreshNameLookups();
            Route::getRoutes()->refreshActionLookups();
        } catch (\Throwable $e) {
            // エラーが発生した場合は元のルートコレクションに戻す
            $router->setRoutes($oldRoutes);

            if (function_exists('logger')) {
                logger()->error('Failed to reload routes', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * ルートファイルを再読み込み
     */
    protected function loadRouteFiles(): void
    {
        // ルートファイルを読み込む前に、既存のルート定義をクリア
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        // ルートファイルのパスを収集
        $routeFiles = [];

        // api.phpを優先的に読み込む
        $apiRoutePath = base_path('routes/api.php');
        if (file_exists($apiRoutePath)) {
            $routeFiles[] = $apiRoutePath;
        }

        // web.phpも読み込む（APIルートが含まれている場合があるため）
        $webRoutePath = base_path('routes/web.php');
        if (file_exists($webRoutePath)) {
            $routeFiles[] = $webRoutePath;
        }

        // カスタムルートファイルも読み込む
        $customRoutes = config('spectrum.route_files', []);
        foreach ($customRoutes as $routeFile) {
            if (file_exists($routeFile)) {
                $routeFiles[] = $routeFile;
            }
        }

        // ルートファイルを読み込む
        foreach ($routeFiles as $routeFile) {
            // ファイルのキャッシュをクリア
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($routeFile, true);
            }

            // ルートファイルを読み込む（エラーハンドリング付き）
            try {
                // ファイルが存在することを再確認
                if (file_exists($routeFile)) {
                    // ルートファイルを読み込む
                    require $routeFile;
                }
            } catch (\Throwable $e) {
                $this->logError(
                    "Failed to load route file {$routeFile}: {$e->getMessage()}",
                    AnalyzerErrorType::RouteLoadingError,
                    ['file' => $routeFile]
                );
            }
        }
    }

    /**
     * 実際のルート解析処理
     */
    protected function performAnalysis(): array
    {
        // Artisanコマンド実行時にAPIルートファイルが読み込まれていない場合があるため、
        // 必要に応じてルートファイルを明示的に読み込む
        // ただし、テスト環境やすでにルートが存在する場合はスキップ
        $currentRoutes = Route::getRoutes();
        $hasRoutes = false;
        foreach ($currentRoutes as $route) {
            if ($this->isApiRoute($route)) {
                $hasRoutes = true;
                break;
            }
        }

        // APIルートが見つからない場合のみルートファイルを再読み込み
        if (! $hasRoutes && ! app()->runningUnitTests()) {
            $this->loadRouteFiles();
        }

        $routes = [];

        foreach (Route::getRoutes() as $route) {
            try {
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
            } catch (\Exception $e) {
                $this->logError(
                    "Failed to analyze route {$route->uri()}: {$e->getMessage()}",
                    AnalyzerErrorType::AnalysisError,
                    [
                        'uri' => $route->uri(),
                        'methods' => $route->methods(),
                        'action' => $route->getActionName(),
                    ]
                );

                continue;
            }
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
