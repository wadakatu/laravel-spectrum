---
id: api-reference
title: APIリファレンス
sidebar_label: APIリファレンス
---

# APIリファレンス

Laravel Spectrumのプログラマティックな使用方法とAPIリファレンスです。

## 📋 基本的な使用方法

### プログラムからの実行

```php
use LaravelSpectrum\Facades\Spectrum;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Analyzers\RouteAnalyzer;

// ルートを解析
$analyzer = app(RouteAnalyzer::class);
$routes = $analyzer->analyze();

// OpenAPIドキュメントを生成
$generator = app(OpenApiGenerator::class);
$openapi = $generator->generate($routes);

// ファイルに保存
file_put_contents(
    storage_path('app/spectrum/openapi.json'),
    json_encode($openapi, JSON_PRETTY_PRINT)
);
```

### Facadeの使用

```php
use LaravelSpectrum\Facades\Spectrum;

// ドキュメントを生成
$openapi = Spectrum::generate();

// 特定のルートパターンのみ
$openapi = Spectrum::generate(['api/v1/*']);

// オプション付き
$openapi = Spectrum::generate(['api/*'], [
    'includeVendor' => false,
    'useCache' => true,
]);
```

## 🔍 Analyzers

### RouteAnalyzer

ルート情報を解析するメインクラス。

```php
namespace LaravelSpectrum\Analyzers;

class RouteAnalyzer
{
    /**
     * すべてのルートを解析
     *
     * @param array $patterns 含めるルートパターン
     * @param array $excludes 除外するルートパターン
     * @return array
     */
    public function analyze(array $patterns = [], array $excludes = []): array;
    
    /**
     * 単一のルートを解析
     *
     * @param \Illuminate\Routing\Route $route
     * @return array|null
     */
    public function analyzeRoute($route): ?array;
}
```

**使用例：**

```php
$analyzer = app(RouteAnalyzer::class);

// すべてのAPIルートを解析
$routes = $analyzer->analyze(['api/*']);

// 特定のルートを除外
$routes = $analyzer->analyze(['api/*'], ['api/debug/*']);

// 単一ルートの解析
$route = Route::getRoutes()->getByName('users.index');
$analyzed = $analyzer->analyzeRoute($route);
```

### FormRequestAnalyzer

FormRequestクラスからバリデーションルールを抽出。

```php
namespace LaravelSpectrum\Analyzers;

class FormRequestAnalyzer
{
    /**
     * FormRequestを解析
     *
     * @param string $requestClass
     * @return array
     */
    public function analyze(string $requestClass): array;
    
    /**
     * 条件付きルールを含めて解析
     *
     * @param string $requestClass
     * @return array
     */
    public function analyzeWithConditionalRules(string $requestClass): array;
}
```

**使用例：**

```php
$analyzer = app(FormRequestAnalyzer::class);

// 基本的な解析
$parameters = $analyzer->analyze(CreateUserRequest::class);

// 条件付きルールも含めて解析
$result = $analyzer->analyzeWithConditionalRules(UserRequest::class);
// $result = [
//     'parameters' => [...],
//     'conditional_rules' => [...],
// ]
```

### ResourceAnalyzer

APIリソースクラスの構造を解析。

```php
namespace LaravelSpectrum\Analyzers;

class ResourceAnalyzer
{
    /**
     * リソースクラスを解析
     *
     * @param string $resourceClass
     * @return array
     */
    public function analyze(string $resourceClass): array;
    
    /**
     * ネストされたリソースも含めて解析
     *
     * @param string $resourceClass
     * @param int $depth
     * @return array
     */
    public function analyzeWithNested(string $resourceClass, int $depth = 3): array;
}
```

**使用例：**

```php
$analyzer = app(ResourceAnalyzer::class);

// リソースの構造を取得
$structure = $analyzer->analyze(UserResource::class);

// ネストされたリソースも含めて解析（深さ5まで）
$structure = $analyzer->analyzeWithNested(PostResource::class, 5);
```

### QueryParameterAnalyzer

コントローラーメソッドからクエリパラメータを検出。

```php
namespace LaravelSpectrum\Analyzers;

class QueryParameterAnalyzer
{
    /**
     * メソッドからクエリパラメータを検出
     *
     * @param string $controller
     * @param string $method
     * @return array
     */
    public function analyze(string $controller, string $method): array;
}
```

## 🏗️ Generators

### OpenApiGenerator

OpenAPI仕様を生成するメインジェネレーター。

```php
namespace LaravelSpectrum\Generators;

class OpenApiGenerator
{
    /**
     * OpenAPIドキュメントを生成
     *
     * @param array $routes
     * @param array $options
     * @return array
     */
    public function generate(array $routes, array $options = []): array;
    
    /**
     * パスアイテムを生成
     *
     * @param array $route
     * @return array
     */
    public function generatePathItem(array $route): array;
    
    /**
     * オペレーションを生成
     *
     * @param array $route
     * @return array
     */
    public function generateOperation(array $route): array;
}
```

**オプション：**

```php
$options = [
    'title' => 'My API',
    'version' => '2.0.0',
    'description' => 'API Description',
    'servers' => [
        ['url' => 'https://api.example.com'],
    ],
    'includeVendor' => false,
    'generateExamples' => true,
];

$openapi = $generator->generate($routes, $options);
```

### SchemaGenerator

データ構造からJSONスキーマを生成。

```php
namespace LaravelSpectrum\Generators;

class SchemaGenerator
{
    /**
     * バリデーションルールからスキーマを生成
     *
     * @param array $rules
     * @return array
     */
    public function fromValidationRules(array $rules): array;
    
    /**
     * リソース構造からスキーマを生成
     *
     * @param array $structure
     * @return array
     */
    public function fromResourceStructure(array $structure): array;
    
    /**
     * モデルからスキーマを生成
     *
     * @param string $modelClass
     * @return array
     */
    public function fromModel(string $modelClass): array;
}
```

### ExampleGenerator

リアルな例データを生成。

```php
namespace LaravelSpectrum\Generators;

class ExampleGenerator
{
    /**
     * スキーマから例を生成
     *
     * @param array $schema
     * @param string|null $fieldName
     * @return mixed
     */
    public function generateFromSchema(array $schema, ?string $fieldName = null);
    
    /**
     * バリデーションルールから例を生成
     *
     * @param array $rules
     * @param string $fieldName
     * @return mixed
     */
    public function generateFromRules(array $rules, string $fieldName);
}
```

## 🔌 Events

### 利用可能なイベント

```php
// ルート解析前
LaravelSpectrum\Events\BeforeRouteAnalysis::class

// ルート解析後
LaravelSpectrum\Events\AfterRouteAnalysis::class

// ドキュメント生成前
LaravelSpectrum\Events\BeforeDocumentGeneration::class

// ドキュメント生成後
LaravelSpectrum\Events\AfterDocumentGeneration::class

// エラー発生時
LaravelSpectrum\Events\AnalysisError::class
```

### イベントリスナーの登録

```php
// EventServiceProvider.php
protected $listen = [
    \LaravelSpectrum\Events\AfterRouteAnalysis::class => [
        \App\Listeners\LogRouteAnalysis::class,
    ],
    \LaravelSpectrum\Events\AfterDocumentGeneration::class => [
        \App\Listeners\NotifyDocumentGenerated::class,
        \App\Listeners\UploadToS3::class,
    ],
];
```

### カスタムリスナーの例

```php
namespace App\Listeners;

use LaravelSpectrum\Events\AfterDocumentGeneration;

class NotifyDocumentGenerated
{
    public function handle(AfterDocumentGeneration $event)
    {
        $openapi = $event->getOpenApi();
        $stats = $event->getStatistics();
        
        // Slackに通知
        Slack::send("API documentation generated: {$stats['total_routes']} routes");
        
        // メトリクスを記録
        Metrics::record('api_docs_generated', [
            'routes' => $stats['total_routes'],
            'duration' => $stats['duration'],
        ]);
    }
}
```

## 🎨 Contracts

### Analyzer契約

```php
namespace LaravelSpectrum\Contracts;

interface Analyzer
{
    /**
     * 解析を実行
     *
     * @param mixed $target
     * @return array
     */
    public function analyze($target): array;
    
    /**
     * 対象をサポートしているか
     *
     * @param mixed $target
     * @return bool
     */
    public function supports($target): bool;
}
```

### Generator契約

```php
namespace LaravelSpectrum\Contracts;

interface Generator
{
    /**
     * 生成を実行
     *
     * @param array $data
     * @param array $options
     * @return array
     */
    public function generate(array $data, array $options = []): array;
}
```

### ExportFormatter契約

```php
namespace LaravelSpectrum\Contracts;

interface ExportFormatter
{
    /**
     * OpenAPIドキュメントをフォーマット
     *
     * @param array $openapi
     * @return array
     */
    public function format(array $openapi): array;
    
    /**
     * サポートする形式
     *
     * @return string
     */
    public function getFormat(): string;
}
```

## 🛠️ Services

### DocumentationCache

ドキュメント生成のキャッシュ管理。

```php
use LaravelSpectrum\Cache\DocumentationCache;

$cache = app(DocumentationCache::class);

// キャッシュから取得または生成
$data = $cache->remember('routes:all', function () {
    return $this->analyzeAllRoutes();
});

// 特定のキャッシュをクリア
$cache->forget('routes:api/users');

// パターンでクリア
$cache->forgetByPattern('routes:api/*');

// すべてクリア
$cache->clear();
```

### FileWatcher

ファイル変更の監視。

```php
use LaravelSpectrum\Services\FileWatcher;

$watcher = app(FileWatcher::class);

// 監視を開始
$watcher->watch([
    app_path('Http/Controllers'),
    app_path('Http/Requests'),
], function ($path, $changeType) {
    // ファイル変更時の処理
    echo "File {$changeType}: {$path}\n";
});

// 監視を停止
$watcher->stop();
```

## 💡 拡張ポイント

### カスタムアナライザーの追加

```php
use LaravelSpectrum\Facades\Spectrum;

// AppServiceProvider.php
public function boot()
{
    Spectrum::addAnalyzer('custom', CustomAnalyzer::class);
}

// 使用
$result = Spectrum::analyze('custom', $target);
```

### カスタムジェネレーターの追加

> **注意**: カスタムジェネレーター機能は将来のリリースで提供予定です。

```php
// 計画中のカスタムジェネレーターAPIの例
Spectrum::addGenerator('custom', CustomSchemaGenerator::class);

// 使用
$schema = Spectrum::generate('custom', $data);
```

### ミドルウェアの追加

```php
Spectrum::addMiddleware(function ($openapi, $next) {
    // 前処理
    $openapi['x-custom'] = 'value';
    
    $openapi = $next($openapi);
    
    // 後処理
    return $openapi;
});
```

## 🔍 ヘルパー関数

```php
// OpenAPIドキュメントを生成
$openapi = spectrum_generate();

// 特定のルートパターンで生成
$openapi = spectrum_generate(['api/v1/*']);

// ルートを解析
$routes = spectrum_analyze_routes();

// FormRequestを解析
$params = spectrum_analyze_request(CreateUserRequest::class);

// リソースを解析
$schema = spectrum_analyze_resource(UserResource::class);
```

## 📊 デバッグとロギング

### デバッグモード

```php
// config/spectrum.php
'debug' => [
    'enabled' => true,
    'log_level' => 'debug',
    'log_channel' => 'spectrum',
],
```

### カスタムロガー

```php
use LaravelSpectrum\Facades\Spectrum;

Spectrum::setLogger(function ($level, $message, $context) {
    // カスタムロギング処理
    CustomLogger::log($level, $message, $context);
});
```

### パフォーマンス計測

```php
use LaravelSpectrum\Support\PerformanceMonitor;

$monitor = app(PerformanceMonitor::class);
$monitor->start('route_analysis');

// 処理...

$monitor->end('route_analysis');

// 統計を取得
$stats = $monitor->getStatistics();
// [
//     'route_analysis' => [
//         'duration' => 1.234,
//         'memory' => 2048,
//         'count' => 150,
//     ]
// ]
```

## 📚 関連ドキュメント

- [カスタマイズ](./customization.md) - 拡張方法の詳細
- [プラグイン開発](./plugin-development.md) - プラグインの作成
- [貢献ガイド](./contributing.md) - 本体への貢献方法