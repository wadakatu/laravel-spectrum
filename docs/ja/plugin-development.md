---
id: plugin-development
title: プラグイン開発ガイド
sidebar_label: プラグイン開発ガイド
---

# プラグイン開発ガイド

Laravel Spectrumのプラグインシステムを使用して、独自の解析機能やカスタマイズを追加できます。

## 🎯 プラグインの基本構造

### プラグインインターフェース

```php
namespace LaravelSpectrum\Contracts;

interface PluginInterface
{
    /**
     * プラグインの初期化
     */
    public function boot(): void;
    
    /**
     * プラグインの名前を取得
     */
    public function getName(): string;
    
    /**
     * プラグインのバージョンを取得
     */
    public function getVersion(): string;
    
    /**
     * プラグインの説明を取得
     */
    public function getDescription(): string;
    
    /**
     * プラグインが提供する解析器を取得
     */
    public function getAnalyzers(): array;
    
    /**
     * プラグインが提供するジェネレーターを取得
     */
    public function getGenerators(): array;
}
```

### 基本的なプラグイン実装

```php
namespace MyCompany\SpectrumPlugins;

use LaravelSpectrum\Contracts\PluginInterface;
use LaravelSpectrum\Plugin\AbstractPlugin;

class CustomAuthPlugin extends AbstractPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'Custom Auth Plugin';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getDescription(): string
    {
        return 'カスタム認証システムのサポートを追加';
    }
    
    public function boot(): void
    {
        // プラグインの初期化処理
        $this->registerAnalyzers();
        $this->registerGenerators();
        $this->registerConfig();
    }
    
    public function getAnalyzers(): array
    {
        return [
            CustomAuthAnalyzer::class,
        ];
    }
    
    public function getGenerators(): array
    {
        return [
            CustomAuthSchemaGenerator::class,
        ];
    }
}
```

## 📦 カスタムアナライザーの作成

### Analyzerインターフェース

```php
namespace LaravelSpectrum\Contracts;

interface AnalyzerInterface
{
    /**
     * 解析を実行
     */
    public function analyze($target): array;
    
    /**
     * この解析器がターゲットをサポートするか判定
     */
    public function supports($target): bool;
    
    /**
     * 解析器の優先度（高いほど先に実行）
     */
    public function getPriority(): int;
}
```

### カスタムアナライザーの実装

```php
namespace MyCompany\SpectrumPlugins\Analyzers;

use LaravelSpectrum\Contracts\AnalyzerInterface;
use LaravelSpectrum\Analyzers\BaseAnalyzer;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class CustomAuthAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function analyze($route): array
    {
        $controller = $route->getController();
        $method = $route->getActionMethod();
        
        // コントローラーメソッドのASTを解析
        $ast = $this->parseFile($controller);
        $visitor = new CustomAuthVisitor($method);
        
        $this->traverseAST($ast, [$visitor]);
        
        return [
            'auth_type' => $visitor->getAuthType(),
            'permissions' => $visitor->getRequiredPermissions(),
            'roles' => $visitor->getRequiredRoles(),
            'custom_guards' => $visitor->getCustomGuards(),
        ];
    }
    
    public function supports($target): bool
    {
        // ルートオブジェクトをサポート
        return $target instanceof \Illuminate\Routing\Route;
    }
    
    public function getPriority(): int
    {
        return 100;
    }
}

class CustomAuthVisitor extends NodeVisitorAbstract
{
    private string $targetMethod;
    private array $authData = [];
    
    public function __construct(string $targetMethod)
    {
        $this->targetMethod = $targetMethod;
    }
    
    public function enterNode(Node $node)
    {
        // カスタム認証アノテーションを検出
        if ($node instanceof Node\Stmt\ClassMethod && 
            $node->name->toString() === $this->targetMethod) {
            
            // @requiresPermission アノテーション
            if ($permissions = $this->extractAnnotation($node, '@requiresPermission')) {
                $this->authData['permissions'] = $permissions;
            }
            
            // @requiresRole アノテーション
            if ($roles = $this->extractAnnotation($node, '@requiresRole')) {
                $this->authData['roles'] = $roles;
            }
        }
        
        // メソッド内でのチェック
        if ($node instanceof Node\Expr\MethodCall) {
            if ($this->isAuthCheck($node)) {
                $this->extractAuthInfo($node);
            }
        }
    }
    
    private function extractAnnotation(Node $node, string $annotation): ?array
    {
        $docComment = $node->getDocComment();
        if (!$docComment) {
            return null;
        }
        
        preg_match_all('/' . preg_quote($annotation) . '\s+([^\n]+)/', $docComment->getText(), $matches);
        return $matches[1] ?? null;
    }
    
    // getter methods...
}
```

## 🎨 カスタムジェネレーターの作成

### Generatorインターフェース

```php
namespace LaravelSpectrum\Contracts;

interface GeneratorInterface
{
    /**
     * OpenAPIコンポーネントを生成
     */
    public function generate(array $analysisData): array;
    
    /**
     * ジェネレーターがデータをサポートするか判定
     */
    public function supports(array $analysisData): bool;
    
    /**
     * 生成されるコンポーネントのタイプ
     */
    public function getComponentType(): string;
}
```

### カスタムジェネレーターの実装

```php
namespace MyCompany\SpectrumPlugins\Generators;

use LaravelSpectrum\Contracts\GeneratorInterface;
use LaravelSpectrum\Generators\BaseGenerator;

class CustomAuthSchemaGenerator extends BaseGenerator implements GeneratorInterface
{
    public function generate(array $analysisData): array
    {
        if (!isset($analysisData['custom_auth'])) {
            return [];
        }
        
        $authData = $analysisData['custom_auth'];
        $schema = [];
        
        // セキュリティスキーマの生成
        if ($authData['auth_type'] === 'custom_token') {
            $schema['securitySchemes']['customToken'] = [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-Custom-Token',
                'description' => 'カスタムトークン認証',
            ];
        }
        
        // パーミッションベースのスキーマ
        if (!empty($authData['permissions'])) {
            $schema['securitySchemes']['permissions'] = [
                'type' => 'oauth2',
                'flows' => [
                    'implicit' => [
                        'authorizationUrl' => config('app.url') . '/oauth/authorize',
                        'scopes' => array_combine(
                            $authData['permissions'],
                            array_map(fn($p) => "Permission: {$p}", $authData['permissions'])
                        ),
                    ],
                ],
            ];
        }
        
        return $schema;
    }
    
    public function supports(array $analysisData): bool
    {
        return isset($analysisData['custom_auth']);
    }
    
    public function getComponentType(): string
    {
        return 'securitySchemes';
    }
}
```

## 🔄 プラグインのライフサイクル

### イベントフック

```php
namespace MyCompany\SpectrumPlugins;

use LaravelSpectrum\Events\AnalysisStarted;
use LaravelSpectrum\Events\AnalysisCompleted;
use LaravelSpectrum\Events\GenerationStarted;
use LaravelSpectrum\Events\GenerationCompleted;

class EventAwarePlugin extends AbstractPlugin
{
    public function boot(): void
    {
        // 解析開始前
        $this->on(AnalysisStarted::class, function ($event) {
            $this->logger->info('Analysis started for: ' . $event->getTarget());
        });
        
        // 解析完了後
        $this->on(AnalysisCompleted::class, function ($event) {
            $results = $event->getResults();
            $this->postProcessResults($results);
        });
        
        // 生成開始前
        $this->on(GenerationStarted::class, function ($event) {
            $this->prepareGeneration($event->getAnalysisData());
        });
        
        // 生成完了後
        $this->on(GenerationCompleted::class, function ($event) {
            $openApiDoc = $event->getDocument();
            $this->enhanceDocument($openApiDoc);
        });
    }
    
    protected function postProcessResults(array &$results): void
    {
        // 解析結果の後処理
        if (isset($results['routes'])) {
            foreach ($results['routes'] as &$route) {
                $route['custom_metadata'] = $this->generateMetadata($route);
            }
        }
    }
    
    protected function enhanceDocument(array &$document): void
    {
        // ドキュメントの拡張
        $document['x-custom-extension'] = [
            'plugin' => $this->getName(),
            'version' => $this->getVersion(),
            'generated_at' => now()->toISOString(),
        ];
    }
}
```

## 🚀 プラグインの設定

### 設定ファイル

```php
namespace MyCompany\SpectrumPlugins;

class ConfigurablePlugin extends AbstractPlugin
{
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'custom_auth' => [
                'header_name' => 'X-Custom-Token',
                'token_prefix' => 'Bearer',
                'validate_permissions' => true,
            ],
            'analysis' => [
                'deep_scan' => false,
                'cache_results' => true,
                'cache_ttl' => 3600,
            ],
        ];
    }
    
    public function boot(): void
    {
        // 設定の登録
        $this->publishes([
            __DIR__ . '/../config/custom-auth-plugin.php' => config_path('spectrum-plugins/custom-auth.php'),
        ], 'config');
        
        // 設定の読み込み
        $this->mergeConfigFrom(
            __DIR__ . '/../config/custom-auth-plugin.php',
            'spectrum-plugins.custom-auth'
        );
    }
    
    protected function isDeepScanEnabled(): bool
    {
        return $this->config('analysis.deep_scan', false);
    }
}
```

### プラグイン設定ファイルの例

```php
// config/spectrum-plugins/custom-auth.php
return [
    'enabled' => env('SPECTRUM_CUSTOM_AUTH_ENABLED', true),
    
    'custom_auth' => [
        'header_name' => env('CUSTOM_AUTH_HEADER', 'X-Custom-Token'),
        'token_prefix' => env('CUSTOM_AUTH_PREFIX', 'Bearer'),
        'validate_permissions' => true,
        'permission_cache_ttl' => 3600,
    ],
    
    'analysis' => [
        'deep_scan' => env('SPECTRUM_DEEP_SCAN', false),
        'cache_results' => true,
        'cache_ttl' => 3600,
        'excluded_paths' => [
            'vendor/*',
            'tests/*',
        ],
    ],
    
    'generators' => [
        'include_examples' => true,
        'include_descriptions' => true,
        'custom_fields' => [
            'x-rate-limit' => true,
            'x-auth-required' => true,
        ],
    ],
];
```

## 📦 プラグインのパッケージング

### composer.json

```json
{
    "name": "mycompany/spectrum-custom-auth-plugin",
    "description": "Laravel Spectrum用カスタム認証プラグイン",
    "type": "laravel-spectrum-plugin",
    "require": {
        "php": "^8.1",
        "wadakatu/laravel-spectrum": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "MyCompany\\SpectrumPlugins\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "MyCompany\\SpectrumPlugins\\CustomAuthPluginServiceProvider"
            ]
        },
        "spectrum": {
            "plugin-class": "MyCompany\\SpectrumPlugins\\CustomAuthPlugin"
        }
    }
}
```

### サービスプロバイダー

```php
namespace MyCompany\SpectrumPlugins;

use Illuminate\Support\ServiceProvider;
use LaravelSpectrum\PluginManager;

class CustomAuthPluginServiceProvider extends ServiceProvider
{
    public function register()
    {
        // プラグインの登録
        $this->app->booted(function () {
            $pluginManager = $this->app->make(PluginManager::class);
            $pluginManager->register(new CustomAuthPlugin());
        });
    }
    
    public function boot()
    {
        // ビューの公開
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'custom-auth-plugin');
        
        // 設定の公開
        $this->publishes([
            __DIR__ . '/../config/plugin.php' => config_path('spectrum-plugins/custom-auth.php'),
        ], 'config');
        
        // マイグレーションの公開
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

## 💡 ベストプラクティス

### 1. プラグインの独立性
- 他のプラグインに依存しない設計
- 名前空間の衝突を避ける
- 設定は名前空間を分ける

### 2. パフォーマンスの考慮
```php
class PerformantPlugin extends AbstractPlugin
{
    protected $cache;
    
    public function boot(): void
    {
        $this->cache = app('cache.store');
    }
    
    protected function analyzeWithCache(string $key, callable $analyzer)
    {
        return $this->cache->remember(
            "spectrum.plugin.{$this->getName()}.{$key}",
            $this->config('cache_ttl', 3600),
            $analyzer
        );
    }
}
```

### 3. エラーハンドリング
```php
public function analyze($target): array
{
    try {
        return $this->doAnalysis($target);
    } catch (\Exception $e) {
        $this->logger->error("Plugin analysis failed: " . $e->getMessage());
        
        // フォールバック
        return $this->getFallbackResults();
    }
}
```

## 📚 関連ドキュメント

- [カスタマイズ](./customization.md) - Laravel Spectrumのカスタマイズ
- [APIリファレンス](./api-reference.md) - 利用可能なAPI
- [貢献ガイド](./contributing.md) - プラグインの公開方法