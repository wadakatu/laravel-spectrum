# Plugin Development Guide

You can add custom analysis features and customizations using Laravel Spectrum's plugin system.

## 🎯 Basic Plugin Structure

### Plugin Interface

```php
namespace LaravelSpectrum\Contracts;

interface PluginInterface
{
    /**
     * Initialize the plugin
     */
    public function boot(): void;
    
    /**
     * Get plugin name
     */
    public function getName(): string;
    
    /**
     * Get plugin version
     */
    public function getVersion(): string;
    
    /**
     * Get plugin description
     */
    public function getDescription(): string;
    
    /**
     * Get analyzers provided by the plugin
     */
    public function getAnalyzers(): array;
    
    /**
     * Get generators provided by the plugin
     */
    public function getGenerators(): array;
}
```

### Basic Plugin Implementation

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
        return 'Adds support for custom authentication systems';
    }
    
    public function boot(): void
    {
        // Plugin initialization
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

## 📦 Creating Custom Analyzers

### Analyzer Interface

```php
namespace LaravelSpectrum\Contracts;

interface AnalyzerInterface
{
    /**
     * Execute analysis
     */
    public function analyze($target): array;
    
    /**
     * Determine if this analyzer supports the target
     */
    public function supports($target): bool;
    
    /**
     * Get analyzer priority (higher runs first)
     */
    public function getPriority(): int;
}
```

### Custom Analyzer Implementation

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
        
        // Analyze controller method AST
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
        // Supports route objects
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
        // Detect custom auth annotations
        if ($node instanceof Node\Stmt\ClassMethod && 
            $node->name->toString() === $this->targetMethod) {
            
            // @requiresPermission annotation
            if ($permissions = $this->extractAnnotation($node, '@requiresPermission')) {
                $this->authData['permissions'] = $permissions;
            }
            
            // @requiresRole annotation
            if ($roles = $this->extractAnnotation($node, '@requiresRole')) {
                $this->authData['roles'] = $roles;
            }
        }
        
        // Checks within method
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

## 🎨 Creating Custom Generators

### Generator Interface

```php
namespace LaravelSpectrum\Contracts;

interface GeneratorInterface
{
    /**
     * Generate OpenAPI component
     */
    public function generate(array $analysisData): array;
    
    /**
     * Determine if generator supports the data
     */
    public function supports(array $analysisData): bool;
    
    /**
     * Get generated component type
     */
    public function getComponentType(): string;
}
```

### Custom Generator Implementation

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
        
        // Generate security schema
        if ($authData['auth_type'] === 'custom_token') {
            $schema['securitySchemes']['customToken'] = [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-Custom-Token',
                'description' => 'Custom token authentication',
            ];
        }
        
        // Permission-based schema
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

## 🔄 Plugin Lifecycle

### Event Hooks

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
        // Before analysis starts
        $this->on(AnalysisStarted::class, function ($event) {
            $this->logger->info('Analysis started for: ' . $event->getTarget());
        });
        
        // After analysis completes
        $this->on(AnalysisCompleted::class, function ($event) {
            $results = $event->getResults();
            $this->postProcessResults($results);
        });
        
        // Before generation starts
        $this->on(GenerationStarted::class, function ($event) {
            $this->prepareGeneration($event->getAnalysisData());
        });
        
        // After generation completes
        $this->on(GenerationCompleted::class, function ($event) {
            $openApiDoc = $event->getDocument();
            $this->enhanceDocument($openApiDoc);
        });
    }
    
    protected function postProcessResults(array &$results): void
    {
        // Post-process analysis results
        if (isset($results['routes'])) {
            foreach ($results['routes'] as &$route) {
                $route['custom_metadata'] = $this->generateMetadata($route);
            }
        }
    }
    
    protected function enhanceDocument(array &$document): void
    {
        // Enhance document
        $document['x-custom-extension'] = [
            'plugin' => $this->getName(),
            'version' => $this->getVersion(),
            'generated_at' => now()->toISOString(),
        ];
    }
}
```

## 🚀 Plugin Configuration

### Configuration File

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
        // Register configuration
        $this->publishes([
            __DIR__ . '/../config/custom-auth-plugin.php' => config_path('spectrum-plugins/custom-auth.php'),
        ], 'config');
        
        // Load configuration
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

### Example Plugin Configuration File

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

## 📦 Plugin Packaging

### composer.json

```json
{
    "name": "mycompany/spectrum-custom-auth-plugin",
    "description": "Custom authentication plugin for Laravel Spectrum",
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

### Service Provider

```php
namespace MyCompany\SpectrumPlugins;

use Illuminate\Support\ServiceProvider;
use LaravelSpectrum\PluginManager;

class CustomAuthPluginServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register plugin
        $this->app->booted(function () {
            $pluginManager = $this->app->make(PluginManager::class);
            $pluginManager->register(new CustomAuthPlugin());
        });
    }
    
    public function boot()
    {
        // Publish views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'custom-auth-plugin');
        
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/plugin.php' => config_path('spectrum-plugins/custom-auth.php'),
        ], 'config');
        
        // Publish migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

## 💡 Best Practices

### 1. Plugin Independence
- Design without dependencies on other plugins
- Avoid namespace collisions
- Separate configuration namespaces

### 2. Performance Considerations
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

### 3. Error Handling
```php
public function analyze($target): array
{
    try {
        return $this->doAnalysis($target);
    } catch (\Exception $e) {
        $this->logger->error("Plugin analysis failed: " . $e->getMessage());
        
        // Fallback
        return $this->getFallbackResults();
    }
}
```

## 📚 Related Documentation

- [Customization](./customization.md) - Laravel Spectrum customization
- [API Reference](./api-reference.md) - Available APIs
- [Contributing Guide](./contributing.md) - How to publish plugins