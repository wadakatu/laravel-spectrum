<?php

namespace LaravelSpectrum\Tests\Performance;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('performance')]
class LargeScalePerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before test
        app(DocumentationCache::class)->clear();

        // Disable parallel processing for consistent measurements
        config(['spectrum.performance.parallel_processing' => false]);
    }

    protected function tearDown(): void
    {
        // Clear cache after test
        app(DocumentationCache::class)->clear();

        parent::tearDown();
    }

    #[Test]
    public function it_handles_thousands_of_routes_within_memory_limits()
    {
        // Arrange - Create 3000 routes
        $this->generateLargeRouteSet(3000);

        // Measure initial memory
        $initialMemory = memory_get_usage(true);
        $this->info('Initial memory: '.$this->formatBytes($initialMemory));

        // Act - Generate documentation
        $startTime = microtime(true);

        $generator = app(OpenApiGenerator::class);
        // First analyze routes
        $routeAnalyzer = app(RouteAnalyzer::class);
        $routes = $routeAnalyzer->analyze();

        $result = $generator->generate($routes);

        $executionTime = microtime(true) - $startTime;
        $peakMemory = memory_get_peak_usage(true);
        $memoryUsed = $peakMemory - $initialMemory;

        // Assert
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('paths', $result);

        // Memory should not exceed 500MB for 3000 routes
        $memoryLimitMB = 500;
        $memoryUsedMB = $memoryUsed / 1024 / 1024;
        $this->assertLessThan($memoryLimitMB, $memoryUsedMB,
            "Memory usage ({$memoryUsedMB}MB) exceeded limit ({$memoryLimitMB}MB)");

        // Execution time should be reasonable (under 30 seconds)
        $this->assertLessThan(30, $executionTime,
            "Execution time ({$executionTime}s) exceeded 30 seconds");

        // Log performance metrics
        $this->info('Routes processed: 3000');
        $this->info('Execution time: '.round($executionTime, 2).' seconds');
        $this->info('Memory used: '.round($memoryUsedMB, 2).' MB');
        $this->info('Peak memory: '.$this->formatBytes($peakMemory));
        $this->info('Routes per second: '.round(3000 / $executionTime, 2));
    }

    #[Test]
    public function it_handles_hundreds_of_form_requests_efficiently()
    {
        // Arrange - Create 300 unique FormRequest classes
        $formRequests = $this->generateFormRequestClasses(300);

        // Measure initial state
        $initialMemory = memory_get_usage(true);
        $startTime = microtime(true);

        // Act - Analyze all FormRequests
        $analyzer = app(FormRequestAnalyzer::class);
        $totalParameters = 0;

        foreach ($formRequests as $formRequestClass) {
            $parameters = $analyzer->analyze($formRequestClass);
            $totalParameters += count($parameters);
        }

        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_peak_usage(true) - $initialMemory;

        // Assert
        $this->assertGreaterThan(0, $totalParameters);

        // Performance assertions
        $avgTimePerRequest = $executionTime / 300;
        $this->assertLessThan(0.1, $avgTimePerRequest,
            "Average time per FormRequest ({$avgTimePerRequest}s) exceeded 0.1s");

        // Memory efficiency
        $memoryPerRequestKB = ($memoryUsed / 300) / 1024;
        $this->assertLessThan(100, $memoryPerRequestKB,
            "Memory per FormRequest ({$memoryPerRequestKB}KB) exceeded 100KB");

        // Log metrics
        $this->info('FormRequests analyzed: 300');
        $this->info('Total parameters extracted: '.$totalParameters);
        $this->info('Execution time: '.round($executionTime, 2).' seconds');
        $this->info('Average time per request: '.round($avgTimePerRequest * 1000, 2).' ms');
        $this->info('Memory per request: '.round($memoryPerRequestKB, 2).' KB');
    }

    #[Test]
    public function it_maintains_performance_with_complex_nested_routes()
    {
        // Arrange - Create deeply nested route groups
        $this->generateNestedRouteGroups(5, 10); // 5 levels deep, 10 routes per level

        // Act
        $startTime = microtime(true);
        $analyzer = app(RouteAnalyzer::class);
        $routes = $analyzer->analyze();
        $executionTime = microtime(true) - $startTime;

        // Assert
        $totalRoutes = count($routes);
        $this->assertGreaterThan(0, $totalRoutes);

        // Performance should still be reasonable with nested routes
        $routesPerSecond = $totalRoutes / $executionTime;
        $this->assertGreaterThan(100, $routesPerSecond,
            "Route analysis too slow: {$routesPerSecond} routes/second");

        // Verify route structure is preserved
        $nestedRoutes = array_filter($routes, function ($route) {
            return substr_count($route['uri'], '/') > 5;
        });
        $this->assertNotEmpty($nestedRoutes, 'Nested routes not properly analyzed');

        $this->info('Total nested routes: '.$totalRoutes);
        $this->info('Analysis time: '.round($executionTime, 2).' seconds');
        $this->info('Routes per second: '.round($routesPerSecond, 2));
    }

    #[Test]
    public function it_scales_linearly_with_route_count()
    {
        $measurements = [];
        $routeCounts = [100, 500, 1000, 2000];

        foreach ($routeCounts as $count) {
            // Clear routes
            Route::getRoutes()->refreshNameLookups();

            // Generate routes
            $this->generateLargeRouteSet($count);

            // Measure
            $startTime = microtime(true);
            $analyzer = app(RouteAnalyzer::class);
            $analyzer->analyze();
            $executionTime = microtime(true) - $startTime;

            $measurements[$count] = $executionTime;
            $this->info("Routes: {$count}, Time: ".round($executionTime, 3).'s');
        }

        // Calculate scaling factor
        $scalingFactors = [];
        $previous = null;
        foreach ($measurements as $count => $time) {
            if ($previous !== null) {
                $routeRatio = $count / $previous['count'];
                $timeRatio = $time / $previous['time'];
                $scalingFactors[] = $timeRatio / $routeRatio;
            }
            $previous = ['count' => $count, 'time' => $time];
        }

        $avgScalingFactor = array_sum($scalingFactors) / count($scalingFactors);

        // Should scale roughly linearly (factor close to 1.0)
        $this->assertLessThan(1.5, $avgScalingFactor,
            "Non-linear scaling detected: {$avgScalingFactor}");

        $this->info('Average scaling factor: '.round($avgScalingFactor, 2));
    }

    /**
     * Generate a large set of routes for testing
     */
    private function generateLargeRouteSet(int $count): void
    {
        $controllers = [
            'UserController', 'ProductController', 'OrderController',
            'CategoryController', 'ReviewController', 'PaymentController',
        ];

        $actions = ['index', 'show', 'store', 'update', 'destroy'];

        for ($i = 0; $i < $count; $i++) {
            $controller = $controllers[$i % count($controllers)];
            $action = $actions[$i % count($actions)];
            $method = match ($action) {
                'index', 'show' => 'get',
                'store' => 'post',
                'update' => 'put',
                'destroy' => 'delete'
            };

            $uri = "api/v1/resource-{$i}";
            if ($action === 'show' || $action === 'update' || $action === 'destroy') {
                $uri .= '/{id}';
            }

            Route::$method($uri, ["App\\Http\\Controllers\\{$controller}", $action])
                ->name("resource{$i}.{$action}");
        }
    }

    /**
     * Generate FormRequest classes dynamically
     */
    private function generateFormRequestClasses(int $count): array
    {
        $classes = [];

        for ($i = 0; $i < $count; $i++) {
            $className = "DynamicFormRequest{$i}";
            $classCode = "
                namespace Tests\Performance\Generated;
                
                use Illuminate\\Foundation\\Http\\FormRequest;
                
                class {$className} extends FormRequest
                {
                    public function rules(): array
                    {
                        return [
                            'field_1' => 'required|string|max:255',
                            'field_2' => 'required|integer|min:0|max:1000',
                            'field_3' => 'nullable|email|unique:users,email',
                            'field_4' => 'required|array',
                            'field_4.*' => 'required|string',
                            'field_5' => 'required|date|after:today',
                        ];
                    }
                    
                    public function attributes(): array
                    {
                        return [
                            'field_1' => 'Field One',
                            'field_2' => 'Field Two',
                            'field_3' => 'Field Three',
                            'field_4' => 'Field Four',
                            'field_5' => 'Field Five',
                        ];
                    }
                }
            ";

            // Use reflection to create dynamic class
            if (! class_exists($fullClassName = "Tests\Performance\Generated\{$className}")) {
                eval($classCode);
            }
            $classes[] = "Tests\Performance\Generated\{$className}";
        }

        return $classes;
    }

    /**
     * Generate deeply nested route groups
     */
    private function generateNestedRouteGroups(int $depth, int $routesPerLevel): void
    {
        $this->createNestedGroup([], $depth, $routesPerLevel);
    }

    private function createNestedGroup(array $prefixes, int $remainingDepth, int $routesPerLevel): void
    {
        if ($remainingDepth === 0) {
            return;
        }

        $prefix = implode('/', $prefixes);

        if (empty($prefix)) {
            // Root level routes
            for ($i = 0; $i < $routesPerLevel; $i++) {
                Route::get("endpoint-L0-{$i}", function () {
                    return ['status' => 'ok'];
                })->name("nested.L0.endpoint{$i}");
            }

            // Create next level
            if ($remainingDepth > 1) {
                $this->createNestedGroup(['level1'], $remainingDepth - 1, $routesPerLevel);
            }
        } else {
            Route::prefix($prefix)->group(function () use ($prefixes, $remainingDepth, $routesPerLevel) {
                // Add routes at this level
                for ($i = 0; $i < $routesPerLevel; $i++) {
                    $level = count($prefixes);
                    Route::get("endpoint-L{$level}-{$i}", function () {
                        return ['status' => 'ok'];
                    })->name("nested.L{$level}.endpoint{$i}");
                }

                // Create next level
                if ($remainingDepth > 1) {
                    $newPrefixes = array_merge($prefixes, ['level'.(count($prefixes) + 1)]);
                    $this->createNestedGroup($newPrefixes, $remainingDepth - 1, $routesPerLevel);
                }
            });
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Output information during tests
     */
    private function info(string $message): void
    {
        fwrite(STDOUT, "\n".$message);
    }
}
