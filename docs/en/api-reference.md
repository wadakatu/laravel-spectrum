# API Reference

Programmatic usage and API reference for Laravel Spectrum.

## 📋 Basic Usage

### Programmatic Execution

```php
use LaravelSpectrum\Facades\Spectrum;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Analyzers\RouteAnalyzer;

// Analyze routes
$analyzer = app(RouteAnalyzer::class);
$routes = $analyzer->analyze();

// Generate OpenAPI document
$generator = app(OpenApiGenerator::class);
$openapi = $generator->generate($routes);

// Save to file
file_put_contents(
    storage_path('app/spectrum/openapi.json'),
    json_encode($openapi, JSON_PRETTY_PRINT)
);
```

### Using Facades

```php
use LaravelSpectrum\Facades\Spectrum;

// Generate documentation
$openapi = Spectrum::generate();

// Only specific route patterns
$openapi = Spectrum::generate(['api/v1/*']);

// With options
$openapi = Spectrum::generate(['api/*'], [
    'includeVendor' => false,
    'useCache' => true,
]);
```

## 🔍 Analyzers

### RouteAnalyzer

Main class for analyzing route information.

```php
namespace LaravelSpectrum\Analyzers;

class RouteAnalyzer
{
    /**
     * Analyze all routes
     *
     * @param array $patterns Route patterns to include
     * @param array $excludes Route patterns to exclude
     * @return array
     */
    public function analyze(array $patterns = [], array $excludes = []): array;
    
    /**
     * Analyze a single route
     *
     * @param \Illuminate\Routing\Route $route
     * @return array|null
     */
    public function analyzeRoute($route): ?array;
}
```

**Usage Examples:**

```php
$analyzer = app(RouteAnalyzer::class);

// Analyze all API routes
$routes = $analyzer->analyze(['api/*']);

// Exclude specific routes
$routes = $analyzer->analyze(['api/*'], ['api/debug/*']);

// Analyze single route
$route = Route::getRoutes()->getByName('users.index');
$analyzed = $analyzer->analyzeRoute($route);
```

### FormRequestAnalyzer

Extracts validation rules from FormRequest classes.

```php
namespace LaravelSpectrum\Analyzers;

class FormRequestAnalyzer
{
    /**
     * Analyze FormRequest
     *
     * @param string $requestClass
     * @return array
     */
    public function analyze(string $requestClass): array;
    
    /**
     * Analyze including conditional rules
     *
     * @param string $requestClass
     * @return array
     */
    public function analyzeWithConditionalRules(string $requestClass): array;
}
```

**Usage Examples:**

```php
$analyzer = app(FormRequestAnalyzer::class);

// Basic analysis
$parameters = $analyzer->analyze(CreateUserRequest::class);

// Analysis including conditional rules
$result = $analyzer->analyzeWithConditionalRules(UserRequest::class);
// $result = [
//     'parameters' => [...],
//     'conditional_rules' => [...],
// ]
```

### ResourceAnalyzer

Analyzes API resource class structures.

```php
namespace LaravelSpectrum\Analyzers;

class ResourceAnalyzer
{
    /**
     * Analyze resource class
     *
     * @param string $resourceClass
     * @return array
     */
    public function analyze(string $resourceClass): array;
    
    /**
     * Analyze including nested resources
     *
     * @param string $resourceClass
     * @param int $depth
     * @return array
     */
    public function analyzeWithNested(string $resourceClass, int $depth = 3): array;
}
```

**Usage Examples:**

```php
$analyzer = app(ResourceAnalyzer::class);

// Get resource structure
$structure = $analyzer->analyze(UserResource::class);

// Analyze including nested resources (up to depth 5)
$structure = $analyzer->analyzeWithNested(PostResource::class, 5);
```

### QueryParameterAnalyzer

Detects query parameters from controller methods.

```php
namespace LaravelSpectrum\Analyzers;

class QueryParameterAnalyzer
{
    /**
     * Detect query parameters from method
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

Main generator for creating OpenAPI specifications.

```php
namespace LaravelSpectrum\Generators;

class OpenApiGenerator
{
    /**
     * Generate OpenAPI document
     *
     * @param array $routes
     * @param array $options
     * @return array
     */
    public function generate(array $routes, array $options = []): array;
    
    /**
     * Generate path item
     *
     * @param array $route
     * @return array
     */
    public function generatePathItem(array $route): array;
    
    /**
     * Generate operation
     *
     * @param array $route
     * @return array
     */
    public function generateOperation(array $route): array;
}
```

**Options:**

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

Generates JSON schemas from data structures.

```php
namespace LaravelSpectrum\Generators;

class SchemaGenerator
{
    /**
     * Generate schema from validation rules
     *
     * @param array $rules
     * @return array
     */
    public function fromValidationRules(array $rules): array;
    
    /**
     * Generate schema from resource structure
     *
     * @param array $structure
     * @return array
     */
    public function fromResourceStructure(array $structure): array;
    
    /**
     * Generate schema from model
     *
     * @param string $modelClass
     * @return array
     */
    public function fromModel(string $modelClass): array;
}
```

### ExampleGenerator

Generates realistic example data.

```php
namespace LaravelSpectrum\Generators;

class ExampleGenerator
{
    /**
     * Generate example from schema
     *
     * @param array $schema
     * @param string|null $fieldName
     * @return mixed
     */
    public function generateFromSchema(array $schema, ?string $fieldName = null);
    
    /**
     * Generate example from validation rules
     *
     * @param array $rules
     * @param string $fieldName
     * @return mixed
     */
    public function generateFromRules(array $rules, string $fieldName);
}
```

## 🔌 Events

### Available Events

```php
// Before route analysis
LaravelSpectrum\Events\BeforeRouteAnalysis::class

// After route analysis
LaravelSpectrum\Events\AfterRouteAnalysis::class

// Before document generation
LaravelSpectrum\Events\BeforeDocumentGeneration::class

// After document generation
LaravelSpectrum\Events\AfterDocumentGeneration::class

// On error
LaravelSpectrum\Events\AnalysisError::class
```

### Registering Event Listeners

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

### Custom Listener Example

```php
namespace App\Listeners;

use LaravelSpectrum\Events\AfterDocumentGeneration;

class NotifyDocumentGenerated
{
    public function handle(AfterDocumentGeneration $event)
    {
        $openapi = $event->getOpenApi();
        $stats = $event->getStatistics();
        
        // Notify Slack
        Slack::send("API documentation generated: {$stats['total_routes']} routes");
        
        // Record metrics
        Metrics::record('api_docs_generated', [
            'routes' => $stats['total_routes'],
            'duration' => $stats['duration'],
        ]);
    }
}
```

## 🎨 Contracts

### Analyzer Contract

```php
namespace LaravelSpectrum\Contracts;

interface Analyzer
{
    /**
     * Execute analysis
     *
     * @param mixed $target
     * @return array
     */
    public function analyze($target): array;
    
    /**
     * Check if target is supported
     *
     * @param mixed $target
     * @return bool
     */
    public function supports($target): bool;
}
```

### Generator Contract

```php
namespace LaravelSpectrum\Contracts;

interface Generator
{
    /**
     * Execute generation
     *
     * @param array $data
     * @param array $options
     * @return array
     */
    public function generate(array $data, array $options = []): array;
}
```

### ExportFormatter Contract

```php
namespace LaravelSpectrum\Contracts;

interface ExportFormatter
{
    /**
     * Format OpenAPI document
     *
     * @param array $openapi
     * @return array
     */
    public function format(array $openapi): array;
    
    /**
     * Get supported format
     *
     * @return string
     */
    public function getFormat(): string;
}
```

## 🛠️ Services

### DocumentationCache

Manages documentation generation caching.

```php
use LaravelSpectrum\Cache\DocumentationCache;

$cache = app(DocumentationCache::class);

// Get from cache or generate
$data = $cache->remember('routes:all', function () {
    return $this->analyzeAllRoutes();
});

// Clear specific cache
$cache->forget('routes:api/users');

// Clear by pattern
$cache->forgetByPattern('routes:api/*');

// Clear all
$cache->clear();
```

### FileWatcher

Monitors file changes.

```php
use LaravelSpectrum\Services\FileWatcher;

$watcher = app(FileWatcher::class);

// Start watching
$watcher->watch([
    app_path('Http/Controllers'),
    app_path('Http/Requests'),
], function ($path, $changeType) {
    // Handle file changes
    echo "File {$changeType}: {$path}\n";
});

// Stop watching
$watcher->stop();
```

## 💡 Extension Points

### Adding Custom Analyzers

```php
use LaravelSpectrum\Facades\Spectrum;

// AppServiceProvider.php
public function boot()
{
    Spectrum::addAnalyzer('custom', CustomAnalyzer::class);
}

// Usage
$result = Spectrum::analyze('custom', $target);
```

### Adding Custom Generators

> **Note**: Custom generator functionality is planned for a future release.

```php
// Example of planned custom generator API
Spectrum::addGenerator('custom', CustomSchemaGenerator::class);

// Usage
$schema = Spectrum::generate('custom', $data);
```

### Adding Middleware

```php
Spectrum::addMiddleware(function ($openapi, $next) {
    // Pre-processing
    $openapi['x-custom'] = 'value';
    
    $openapi = $next($openapi);
    
    // Post-processing
    return $openapi;
});
```

## 🔍 Helper Functions

```php
// Generate OpenAPI document
$openapi = spectrum_generate();

// Generate with specific route patterns
$openapi = spectrum_generate(['api/v1/*']);

// Analyze routes
$routes = spectrum_analyze_routes();

// Analyze FormRequest
$params = spectrum_analyze_request(CreateUserRequest::class);

// Analyze resource
$schema = spectrum_analyze_resource(UserResource::class);
```

## 📊 Debugging and Logging

### Debug Mode

```php
// config/spectrum.php
'debug' => [
    'enabled' => true,
    'log_level' => 'debug',
    'log_channel' => 'spectrum',
],
```

### Custom Logger

```php
use LaravelSpectrum\Facades\Spectrum;

Spectrum::setLogger(function ($level, $message, $context) {
    // Custom logging logic
    CustomLogger::log($level, $message, $context);
});
```

### Performance Monitoring

```php
use LaravelSpectrum\Support\PerformanceMonitor;

$monitor = app(PerformanceMonitor::class);
$monitor->start('route_analysis');

// Processing...

$monitor->end('route_analysis');

// Get statistics
$stats = $monitor->getStatistics();
// [
//     'route_analysis' => [
//         'duration' => 1.234,
//         'memory' => 2048,
//         'count' => 150,
//     ]
// ]
```

## 📚 Related Documentation

- [Customization](./customization.md) - Detailed extension methods
- [Plugin Development](./plugin-development.md) - Creating plugins
- [Contributing Guide](./contributing.md) - Contributing to the project