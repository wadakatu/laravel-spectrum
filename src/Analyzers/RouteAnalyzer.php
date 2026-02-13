<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\DTO\RouteInfo;
use LaravelSpectrum\DTO\RouteParameterInfo;
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
     *
     * @return array<int, array<string, mixed>>
     */
    public function analyze(bool $useCache = true): array
    {
        return array_map(
            fn (RouteInfo $route) => $route->toArray(),
            $this->analyzeToResult($useCache)
        );
    }

    /**
     * APIルートを解析してRouteInfoの配列を返す
     *
     * @return array<int, RouteInfo>
     */
    public function analyzeToResult(bool $useCache = true): array
    {
        if (! $useCache || ! $this->cache->isEnabled()) {
            return $this->performAnalysis();
        }

        // Cache stores arrays, so we need to convert back to DTOs
        $cached = $this->cache->rememberRoutes(function () {
            return array_map(
                fn (RouteInfo $route) => $route->toArray(),
                $this->performAnalysis()
            );
        });

        return array_map(fn (array $data) => RouteInfo::fromArray($data), $cached);
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
            RouteFacade::getRoutes()->refreshNameLookups();
            RouteFacade::getRoutes()->refreshActionLookups();
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
        RouteFacade::getRoutes()->refreshNameLookups();
        RouteFacade::getRoutes()->refreshActionLookups();

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
     *
     * @return array<int, RouteInfo>
     */
    protected function performAnalysis(): array
    {
        // Artisanコマンド実行時にAPIルートファイルが読み込まれていない場合があるため、
        // 必要に応じてルートファイルを明示的に読み込む
        // ただし、テスト環境やすでにルートが存在する場合はスキップ
        $currentRoutes = RouteFacade::getRoutes()->getRoutes();
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

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
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

                // For invokable controllers, getActionMethod() returns the class name
                // We need to use '__invoke' as the method name
                // Laravel ensures invokable controllers have __invoke method
                $controllerClass = get_class($controller);
                if ($method === $controllerClass && method_exists($controller, '__invoke')) {
                    $method = '__invoke';
                }

                $httpMethods = $this->filterHttpMethods($route->methods());

                if ($httpMethods === []) {
                    continue;
                }

                $routes[] = new RouteInfo(
                    uri: $route->uri(),
                    httpMethods: $httpMethods,
                    controller: get_class($controller),
                    method: $method,
                    name: $route->getName(),
                    middleware: $this->extractMiddleware($route),
                    parameters: $this->extractRouteParametersToDto($route),
                );
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
    protected function isApiRoute(Route $route): bool
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
     * @param  array<int, string>  $httpMethods
     * @return array<int, string>
     */
    protected function filterHttpMethods(array $httpMethods): array
    {
        $excludedMethods = array_map(
            static fn ($method): string => strtoupper((string) $method),
            (array) config('spectrum.excluded_methods', [])
        );

        if ($excludedMethods === []) {
            return $httpMethods;
        }

        return array_values(array_filter(
            $httpMethods,
            fn (string $method): bool => ! in_array(strtoupper($method), $excludedMethods, true)
        ));
    }

    /**
     * ルートパラメータを抽出（配列形式）
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractRouteParameters(Route $route): array
    {
        return array_map(
            fn (RouteParameterInfo $param) => $param->toArray(),
            $this->extractRouteParametersToDto($route)
        );
    }

    /**
     * ルートパラメータを抽出（DTO形式）
     *
     * @return array<int, RouteParameterInfo>
     */
    protected function extractRouteParametersToDto(Route $route): array
    {
        preg_match_all('/\{([^}]+)\}/', $route->uri(), $matches);

        // Get where constraints from the route
        $wheres = $route->wheres;

        $parameters = [];
        foreach ($matches[1] as $param) {
            $isOptional = Str::endsWith($param, '?');
            $name = rtrim($param, '?');

            // Build schema based on where constraints
            $schema = $this->buildSchemaFromWhereConstraint($name, $wheres);

            $parameters[] = new RouteParameterInfo(
                name: $name,
                required: ! $isOptional,
                in: 'path',
                schema: $schema,
            );
        }

        return $parameters;
    }

    /**
     * Build OpenAPI schema from where constraint pattern.
     *
     * @param  array<string, string>  $wheres
     * @return array<string, mixed>
     */
    protected function buildSchemaFromWhereConstraint(string $paramName, array $wheres): array
    {
        // Default schema
        $schema = ['type' => 'string'];

        // Check if there's a where constraint for this parameter
        if (! isset($wheres[$paramName])) {
            return $schema;
        }

        $pattern = $wheres[$paramName];

        // Check for common patterns and map to OpenAPI types/formats
        return $this->mapPatternToSchema($pattern);
    }

    /**
     * Map regex pattern to OpenAPI schema.
     *
     * @return array<string, mixed>
     */
    protected function mapPatternToSchema(string $pattern): array
    {
        // Integer patterns: [0-9]+, \d+, etc.
        if ($this->isIntegerPattern($pattern)) {
            return ['type' => 'integer'];
        }

        // UUID pattern
        if ($this->isUuidPattern($pattern)) {
            return ['type' => 'string', 'format' => 'uuid'];
        }

        // For other patterns, return string with pattern
        return [
            'type' => 'string',
            'pattern' => '^'.$pattern.'$',
        ];
    }

    /**
     * Check if pattern matches integer constraints.
     */
    protected function isIntegerPattern(string $pattern): bool
    {
        // Common integer patterns
        $integerPatterns = [
            '/^\[0-9\]\+$/',           // [0-9]+
            '/^\\\\d\+$/',              // \d+
            '/^\[0-9\]\*$/',           // [0-9]*
            '/^\[0-9\]\{1,\d*\}$/',    // [0-9]{1,} or [0-9]{1,10}
        ];

        foreach ($integerPatterns as $intPattern) {
            if (preg_match($intPattern, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if pattern matches UUID format.
     *
     * @param  string  $pattern  The regex pattern to check
     */
    protected function isUuidPattern(string $pattern): bool
    {
        // UUID regex pattern variants (including Laravel's whereUuid() which uses \d)
        $uuidPatterns = [
            '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
            '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}', // Laravel's whereUuid()
            '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}',
            '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        ];

        foreach ($uuidPatterns as $uuidPattern) {
            if (strcasecmp($pattern, $uuidPattern) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * ミドルウェアを抽出
     */
    protected function extractMiddleware(Route $route): array
    {
        return array_values(array_diff(
            $route->gatherMiddleware(),
            $this->excludedMiddleware
        ));
    }
}
