---
id: customization
title: カスタマイズガイド
sidebar_label: カスタマイズガイド
---

# カスタマイズガイド

Laravel Spectrumは高度にカスタマイズ可能です。このガイドでは、独自のアナライザー、ジェネレーター、フォーマッターの作成方法を説明します。

## 🎯 カスタマイズの概要

Laravel Spectrumは以下の要素をカスタマイズできます：

- **アナライザー**: コードから情報を抽出する処理
- **ジェネレーター**: スキーマや例データを生成する処理
- **フォーマッター**: 出力形式を制御する処理
- **ミドルウェア**: 処理パイプラインのフック

## 🔍 カスタムアナライザー

### 基本的なアナライザー

```php
namespace App\Spectrum\Analyzers;

use LaravelSpectrum\Contracts\Analyzer;
use LaravelSpectrum\Support\AnalysisResult;

class CustomAnnotationAnalyzer implements Analyzer
{
    /**
     * コントローラーメソッドを解析
     */
    public function analyze(string $controller, string $method): AnalysisResult
    {
        $reflection = new \ReflectionMethod($controller, $method);
        $docComment = $reflection->getDocComment();
        
        if (!$docComment) {
            return new AnalysisResult();
        }
        
        // カスタムアノテーションを解析
        $result = new AnalysisResult();
        
        if (preg_match('/@deprecated\s+(.+)/', $docComment, $matches)) {
            $result->addMetadata('deprecated', true);
            $result->addMetadata('deprecation_reason', $matches[1]);
        }
        
        if (preg_match('/@rateLimit\s+(\d+)/', $docComment, $matches)) {
            $result->addMetadata('x-rate-limit', (int) $matches[1]);
        }
        
        if (preg_match('/@tags\s+(.+)/', $docComment, $matches)) {
            $tags = array_map('trim', explode(',', $matches[1]));
            $result->addMetadata('tags', $tags);
        }
        
        return $result;
    }
}
```

### 高度なアナライザー

```php
namespace App\Spectrum\Analyzers;

use LaravelSpectrum\Analyzers\BaseAnalyzer;
use LaravelSpectrum\Support\TypeInference;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class CustomResponseAnalyzer extends BaseAnalyzer
{
    private TypeInference $typeInference;
    
    public function __construct(TypeInference $typeInference)
    {
        parent::__construct();
        $this->typeInference = $typeInference;
    }
    
    public function analyzeResponse(string $controller, string $method): array
    {
        $ast = $this->parseController($controller);
        $visitor = new ResponseVisitor($method);
        
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
        
        return $this->processResponses($visitor->getResponses());
    }
    
    private function processResponses(array $responses): array
    {
        $processed = [];
        
        foreach ($responses as $response) {
            $statusCode = $response['status'] ?? 200;
            $processed[$statusCode] = [
                'description' => $response['description'] ?? $this->getDefaultDescription($statusCode),
                'content' => $this->generateContent($response),
            ];
        }
        
        return $processed;
    }
}

class ResponseVisitor extends NodeVisitorAbstract
{
    private string $targetMethod;
    private array $responses = [];
    
    public function __construct(string $targetMethod)
    {
        $this->targetMethod = $targetMethod;
    }
    
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod && 
            $node->name->toString() === $this->targetMethod) {
            $this->analyzeMethod($node);
        }
        
        return null;
    }
    
    private function analyzeMethod(Node\Stmt\ClassMethod $node)
    {
        // メソッド内のreturn文を解析
        // response()->json()、リソースクラス、など
    }
    
    public function getResponses(): array
    {
        return $this->responses;
    }
}
```

### アナライザーの登録

```php
// AppServiceProvider.php
use LaravelSpectrum\Facades\Spectrum;

public function boot()
{
    Spectrum::addAnalyzer('custom_annotation', CustomAnnotationAnalyzer::class);
    Spectrum::addAnalyzer('custom_response', CustomResponseAnalyzer::class);
}
```

## 🏗️ カスタムジェネレーター

### スキーマジェネレーター

```php
namespace App\Spectrum\Generators;

use LaravelSpectrum\Contracts\SchemaGenerator;

class CustomSchemaGenerator implements SchemaGenerator
{
    /**
     * カスタムスキーマを生成
     */
    public function generateSchema($data): array
    {
        if ($data instanceof \App\Models\CustomModel) {
            return $this->generateCustomModelSchema($data);
        }
        
        return [
            'type' => 'object',
            'properties' => $this->extractProperties($data),
            'required' => $this->extractRequiredFields($data),
            'x-custom' => $this->getCustomMetadata($data),
        ];
    }
    
    private function generateCustomModelSchema($model): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];
        
        // モデルの属性を解析
        foreach ($model->getFillable() as $attribute) {
            $schema['properties'][$attribute] = $this->inferType($model, $attribute);
        }
        
        // リレーションを含める
        foreach ($model->getRelations() as $relation => $value) {
            $schema['properties'][$relation] = $this->generateRelationSchema($value);
        }
        
        return $schema;
    }
    
    private function inferType($model, string $attribute): array
    {
        $casts = $model->getCasts();
        
        if (isset($casts[$attribute])) {
            return $this->castToOpenApiType($casts[$attribute]);
        }
        
        // データベースのカラム型から推測
        $columnType = $model->getConnection()
            ->getDoctrineColumn($model->getTable(), $attribute)
            ->getType()
            ->getName();
            
        return $this->columnTypeToOpenApiType($columnType);
    }
}
```

### 例データジェネレーター

```php
namespace App\Spectrum\Generators;

use LaravelSpectrum\Contracts\ExampleGenerator;
use Faker\Factory as Faker;

class CustomExampleGenerator implements ExampleGenerator
{
    private $faker;
    private array $customRules = [];
    
    public function __construct()
    {
        $this->faker = Faker::create(config('spectrum.example_generation.faker_locale'));
        $this->loadCustomRules();
    }
    
    /**
     * スキーマから例を生成
     */
    public function generate(array $schema, ?string $fieldName = null): mixed
    {
        // カスタムルールをチェック
        if ($fieldName && isset($this->customRules[$fieldName])) {
            return call_user_func($this->customRules[$fieldName], $this->faker);
        }
        
        // フィールド名から推測
        if ($fieldName) {
            $example = $this->generateByFieldName($fieldName);
            if ($example !== null) {
                return $example;
            }
        }
        
        // スキーマタイプに基づいて生成
        return $this->generateBySchema($schema);
    }
    
    private function generateByFieldName(string $fieldName): mixed
    {
        $fieldName = strtolower($fieldName);
        
        $patterns = [
            '/email/' => fn() => $this->faker->safeEmail(),
            '/phone/' => fn() => $this->faker->phoneNumber(),
            '/name$/' => fn() => $this->faker->name(),
            '/first_?name/' => fn() => $this->faker->firstName(),
            '/last_?name/' => fn() => $this->faker->lastName(),
            '/company/' => fn() => $this->faker->company(),
            '/address/' => fn() => $this->faker->address(),
            '/city/' => fn() => $this->faker->city(),
            '/country/' => fn() => $this->faker->country(),
            '/postal_?code|zip/' => fn() => $this->faker->postcode(),
            '/url|link/' => fn() => $this->faker->url(),
            '/uuid/' => fn() => $this->faker->uuid(),
            '/price|amount|cost/' => fn() => $this->faker->randomFloat(2, 100, 10000),
            '/description|bio|about/' => fn() => $this->faker->paragraph(),
            '/title|subject/' => fn() => $this->faker->sentence(),
            '/slug/' => fn() => $this->faker->slug(),
            '/token|key|secret/' => fn() => bin2hex(random_bytes(16)),
            '/password/' => fn() => 'password123',
            '/avatar|image|photo/' => fn() => $this->faker->imageUrl(),
            '/date/' => fn() => $this->faker->date(),
            '/time/' => fn() => $this->faker->time(),
            '/year/' => fn() => $this->faker->year(),
            '/month/' => fn() => $this->faker->month(),
            '/day/' => fn() => $this->faker->dayOfMonth(),
        ];
        
        foreach ($patterns as $pattern => $generator) {
            if (preg_match($pattern, $fieldName)) {
                return $generator();
            }
        }
        
        return null;
    }
    
    private function generateBySchema(array $schema): mixed
    {
        $type = $schema['type'] ?? 'string';
        
        switch ($type) {
            case 'integer':
                $min = $schema['minimum'] ?? 1;
                $max = $schema['maximum'] ?? 1000;
                return $this->faker->numberBetween($min, $max);
                
            case 'number':
                $min = $schema['minimum'] ?? 0;
                $max = $schema['maximum'] ?? 1000;
                return $this->faker->randomFloat(2, $min, $max);
                
            case 'boolean':
                return $this->faker->boolean();
                
            case 'array':
                return $this->generateArray($schema);
                
            case 'object':
                return $this->generateObject($schema);
                
            case 'string':
            default:
                return $this->generateString($schema);
        }
    }
    
    private function loadCustomRules(): void
    {
        $this->customRules = config('spectrum.example_generation.custom_generators', []);
    }
}
```

## 🎨 カスタムフォーマッター

### エクスポートフォーマッター

```php
namespace App\Spectrum\Formatters;

use LaravelSpectrum\Contracts\ExportFormatter;

class AsyncApiFormatter implements ExportFormatter
{
    /**
     * OpenAPIをAsyncAPI形式に変換
     */
    public function format(array $openapi): array
    {
        return [
            'asyncapi' => '2.6.0',
            'info' => $openapi['info'],
            'servers' => $this->transformServers($openapi['servers'] ?? []),
            'channels' => $this->transformPaths($openapi['paths'] ?? []),
            'components' => $this->transformComponents($openapi['components'] ?? []),
        ];
    }
    
    private function transformPaths(array $paths): array
    {
        $channels = [];
        
        foreach ($paths as $path => $operations) {
            $channelName = $this->pathToChannelName($path);
            $channels[$channelName] = $this->transformOperations($operations);
        }
        
        return $channels;
    }
    
    private function pathToChannelName(string $path): string
    {
        // /api/users/{id} -> user.{id}
        $channel = str_replace('/api/', '', $path);
        $channel = str_replace('/', '.', $channel);
        return $channel;
    }
    
    private function transformOperations(array $operations): array
    {
        $channel = [];
        
        foreach ($operations as $method => $operation) {
            $messageType = $this->getMessageType($method);
            
            $channel[$messageType] = [
                'summary' => $operation['summary'] ?? '',
                'description' => $operation['description'] ?? '',
                'message' => $this->transformToMessage($operation),
            ];
        }
        
        return $channel;
    }
}
```



## 🎭 ミドルウェアとフック

### カスタムミドルウェア

```php
namespace App\Spectrum\Middleware;

use LaravelSpectrum\Contracts\Middleware;

class SecurityHeadersMiddleware implements Middleware
{
    /**
     * ドキュメント生成パイプラインを処理
     */
    public function handle($openapi, \Closure $next)
    {
        // 前処理
        $this->addSecurityHeaders($openapi);
        
        // 次のミドルウェアに渡す
        $openapi = $next($openapi);
        
        // 後処理
        $this->validateSecurity($openapi);
        
        return $openapi;
    }
    
    private function addSecurityHeaders(array &$openapi): void
    {
        // グローバルセキュリティヘッダーを追加
        $openapi['x-security-headers'] = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
        ];
        
        // 各エンドポイントにセキュリティ要件を追加
        foreach ($openapi['paths'] as &$path) {
            foreach ($path as &$operation) {
                if (is_array($operation) && !isset($operation['security'])) {
                    $operation['x-requires-auth'] = true;
                }
            }
        }
    }
}
```

### フックの使用

```php
// AppServiceProvider.php
use LaravelSpectrum\Facades\Spectrum;

public function boot()
{
    // 解析前のフック
    Spectrum::beforeAnalysis(function ($routes) {
        Log::info('Starting analysis of ' . count($routes) . ' routes');
    });
    
    // 解析後のフック
    Spectrum::afterAnalysis(function ($analyzedRoutes) {
        // カスタム処理
        foreach ($analyzedRoutes as &$route) {
            $route['x-analyzed-at'] = now()->toISOString();
        }
        
        return $analyzedRoutes;
    });
    
    // 生成前のフック
    Spectrum::beforeGeneration(function ($data) {
        // バリデーション
        if (empty($data['routes'])) {
            throw new \Exception('No routes found for documentation');
        }
    });
    
    // 生成後のフック
    Spectrum::afterGeneration(function ($openapi) {
        // カスタムメタデータを追加
        $openapi['x-generated-by'] = 'Laravel Spectrum Custom';
        $openapi['x-generation-date'] = now()->toISOString();
        
        return $openapi;
    });
}
```

## 📦 カスタムパッケージの作成

### パッケージ構造

```
your-spectrum-extension/
├── src/
│   ├── Analyzers/
│   │   └── CustomAnalyzer.php
│   ├── Generators/
│   │   └── CustomGenerator.php
│   ├── Formatters/
│   │   └── CustomFormatter.php
│   └── YourExtensionServiceProvider.php
├── config/
│   └── your-extension.php
├── composer.json
└── README.md
```

### ServiceProvider

```php
namespace YourVendor\SpectrumExtension;

use Illuminate\Support\ServiceProvider;
use LaravelSpectrum\Facades\Spectrum;

class YourExtensionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/your-extension.php',
            'spectrum-extension'
        );
    }
    
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/your-extension.php' => config_path('spectrum-extension.php'),
            ], 'config');
        }
        
        // カスタムコンポーネントを登録
        Spectrum::addAnalyzer('custom', Analyzers\CustomAnalyzer::class);
        Spectrum::addGenerator('custom', Generators\CustomGenerator::class);
        Spectrum::addFormatter('custom', Formatters\CustomFormatter::class);
        
        // イベントリスナーを登録
        $this->registerEventListeners();
    }
}
```

## 💡 ベストプラクティス

### 1. 単一責任の原則

各カスタムコンポーネントは1つの責任のみを持つようにしてください：

```php
// ✅ 良い例
class EmailFieldGenerator
{
    public function generate(string $fieldName): string
    {
        return $this->faker->safeEmail();
    }
}

// ❌ 悪い例
class UniversalGenerator
{
    public function generate($anything): mixed
    {
        // 複雑すぎる処理
    }
}
```

### 2. インターフェースの活用

```php
interface CustomAnalyzer
{
    public function supports(string $controller, string $method): bool;
    public function analyze(string $controller, string $method): array;
}
```

### 3. テスタビリティ

```php
class TestableAnalyzer
{
    private ParserInterface $parser;
    
    public function __construct(ParserInterface $parser)
    {
        $this->parser = $parser;
    }
    
    // 依存性注入でテストしやすく
}
```

## 📚 関連ドキュメント

- [APIリファレンス](./api-reference.md) - 利用可能なAPI
- [プラグイン開発](./plugin-development.md) - プラグインの詳細
- [貢献ガイド](./contributing.md) - コントリビューション方法