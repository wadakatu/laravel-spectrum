# Customization Guide

Laravel Spectrum is highly customizable. This guide explains how to create custom analyzers, generators, and formatters.

## 🎯 Customization Overview

You can customize the following elements in Laravel Spectrum:

- **Analyzers**: Process to extract information from code
- **Generators**: Process to generate schemas and example data
- **Formatters**: Process to control output format
- **Middleware**: Hooks in the processing pipeline

## 🔍 Custom Analyzers

### Basic Analyzer

```php
namespace App\Spectrum\Analyzers;

use LaravelSpectrum\Contracts\Analyzer;
use LaravelSpectrum\Support\AnalysisResult;

class CustomAnnotationAnalyzer implements Analyzer
{
    /**
     * Analyze controller method
     */
    public function analyze(string $controller, string $method): AnalysisResult
    {
        $reflection = new \ReflectionMethod($controller, $method);
        $docComment = $reflection->getDocComment();
        
        if (!$docComment) {
            return new AnalysisResult();
        }
        
        // Analyze custom annotations
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

### Advanced Analyzer

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
        // Analyze return statements in the method
        // response()->json(), resource classes, etc.
    }
    
    public function getResponses(): array
    {
        return $this->responses;
    }
}
```

### Registering Analyzers

```php
// AppServiceProvider.php
use LaravelSpectrum\Facades\Spectrum;

public function boot()
{
    Spectrum::addAnalyzer('custom_annotation', CustomAnnotationAnalyzer::class);
    Spectrum::addAnalyzer('custom_response', CustomResponseAnalyzer::class);
}
```

## 🏗️ Custom Generators

### Schema Generator

```php
namespace App\Spectrum\Generators;

use LaravelSpectrum\Contracts\SchemaGenerator;

class CustomSchemaGenerator implements SchemaGenerator
{
    /**
     * Generate custom schema
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
        
        // Analyze model attributes
        foreach ($model->getFillable() as $attribute) {
            $schema['properties'][$attribute] = $this->inferType($model, $attribute);
        }
        
        // Include relations
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
        
        // Infer from database column type
        $columnType = $model->getConnection()
            ->getDoctrineColumn($model->getTable(), $attribute)
            ->getType()
            ->getName();
            
        return $this->columnTypeToOpenApiType($columnType);
    }
}
```

### Example Data Generator

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
     * Generate example from schema
     */
    public function generate(array $schema, ?string $fieldName = null): mixed
    {
        // Check custom rules
        if ($fieldName && isset($this->customRules[$fieldName])) {
            return call_user_func($this->customRules[$fieldName], $this->faker);
        }
        
        // Infer from field name
        if ($fieldName) {
            $example = $this->generateByFieldName($fieldName);
            if ($example !== null) {
                return $example;
            }
        }
        
        // Generate based on schema type
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

## 🎨 Custom Formatters

### Export Formatter

```php
namespace App\Spectrum\Formatters;

use LaravelSpectrum\Contracts\ExportFormatter;

class AsyncApiFormatter implements ExportFormatter
{
    /**
     * Convert OpenAPI to AsyncAPI format
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



## 🎭 Middleware and Hooks

### Custom Middleware

```php
namespace App\Spectrum\Middleware;

use LaravelSpectrum\Contracts\Middleware;

class SecurityHeadersMiddleware implements Middleware
{
    /**
     * Process documentation generation pipeline
     */
    public function handle($openapi, \Closure $next)
    {
        // Pre-processing
        $this->addSecurityHeaders($openapi);
        
        // Pass to next middleware
        $openapi = $next($openapi);
        
        // Post-processing
        $this->validateSecurity($openapi);
        
        return $openapi;
    }
    
    private function addSecurityHeaders(array &$openapi): void
    {
        // Add global security headers
        $openapi['x-security-headers'] = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
        ];
        
        // Add security requirements to each endpoint
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

### Using Hooks

```php
// AppServiceProvider.php
use LaravelSpectrum\Facades\Spectrum;

public function boot()
{
    // Before analysis hook
    Spectrum::beforeAnalysis(function ($routes) {
        Log::info('Starting analysis of ' . count($routes) . ' routes');
    });
    
    // After analysis hook
    Spectrum::afterAnalysis(function ($analyzedRoutes) {
        // Custom processing
        foreach ($analyzedRoutes as &$route) {
            $route['x-analyzed-at'] = now()->toISOString();
        }
        
        return $analyzedRoutes;
    });
    
    // Before generation hook
    Spectrum::beforeGeneration(function ($data) {
        // Validation
        if (empty($data['routes'])) {
            throw new \Exception('No routes found for documentation');
        }
    });
    
    // After generation hook
    Spectrum::afterGeneration(function ($openapi) {
        // Add custom metadata
        $openapi['x-generated-by'] = 'Laravel Spectrum Custom';
        $openapi['x-generation-date'] = now()->toISOString();
        
        return $openapi;
    });
}
```

## 📦 Creating Custom Packages

### Package Structure

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
        
        // Register custom components
        Spectrum::addAnalyzer('custom', Analyzers\CustomAnalyzer::class);
        Spectrum::addGenerator('custom', Generators\CustomGenerator::class);
        Spectrum::addFormatter('custom', Formatters\CustomFormatter::class);
        
        // Register event listeners
        $this->registerEventListeners();
    }
}
```

## 💡 Best Practices

### 1. Single Responsibility Principle

Each custom component should have only one responsibility:

```php
// ✅ Good example
class EmailFieldGenerator
{
    public function generate(string $fieldName): string
    {
        return $this->faker->safeEmail();
    }
}

// ❌ Bad example
class UniversalGenerator
{
    public function generate($anything): mixed
    {
        // Too complex processing
    }
}
```

### 2. Using Interfaces

```php
interface CustomAnalyzer
{
    public function supports(string $controller, string $method): bool;
    public function analyze(string $controller, string $method): array;
}
```

### 3. Testability

```php
class TestableAnalyzer
{
    private ParserInterface $parser;
    
    public function __construct(ParserInterface $parser)
    {
        $this->parser = $parser;
    }
    
    // Easy to test with dependency injection
}
```

## 📚 Related Documentation

- [API Reference](./api-reference.md) - Available APIs
- [Plugin Development](./plugin-development.md) - Plugin details
- [Contributing Guide](./contributing.md) - How to contribute