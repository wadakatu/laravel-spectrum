<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check cache configuration
$cacheEnabled = config('spectrum.cache.enabled');
echo "Cache enabled in config: " . ($cacheEnabled ? 'YES' : 'NO') . "\n";
echo "PRISM_CACHE_ENABLED env: " . env('PRISM_CACHE_ENABLED', 'not set') . "\n";
echo "env() returns type: " . gettype(env('PRISM_CACHE_ENABLED')) . "\n";
echo "env() value: '" . env('PRISM_CACHE_ENABLED') . "'\n";
echo "env() var_dump: ";
var_dump(env('PRISM_CACHE_ENABLED'));
echo "config raw: ";
var_dump(config('spectrum.cache.enabled'));

// Check DocumentationCache directly
$cache = new \LaravelSpectrum\Cache\DocumentationCache;
$reflection = new ReflectionClass($cache);
$enabledProp = $reflection->getProperty('enabled');
$enabledProp->setAccessible(true);
echo "DocumentationCache->enabled: " . ($enabledProp->getValue($cache) ? 'YES' : 'NO') . "\n";

// Test if analyzer uses cache
echo "\nFirst analysis with new analyzer instance...\n";
$analyzer1 = new \LaravelSpectrum\Analyzers\FormRequestAnalyzer(
    new \LaravelSpectrum\Support\TypeInference,
    new \LaravelSpectrum\Cache\DocumentationCache,
    new \LaravelSpectrum\Analyzers\EnumAnalyzer
);
$start1 = microtime(true);
$result1 = $analyzer1->analyze(\App\Http\Requests\StoreUserRequest::class);
$time1 = microtime(true) - $start1;
echo "Time: " . number_format($time1 * 1000, 2) . "ms\n";

echo "\nSecond analysis with new analyzer instance...\n";
$analyzer2 = new \LaravelSpectrum\Analyzers\FormRequestAnalyzer(
    new \LaravelSpectrum\Support\TypeInference,
    new \LaravelSpectrum\Cache\DocumentationCache,
    new \LaravelSpectrum\Analyzers\EnumAnalyzer
);
$start2 = microtime(true);
$result2 = $analyzer2->analyze(\App\Http\Requests\StoreUserRequest::class);
$time2 = microtime(true) - $start2;
echo "Time: " . number_format($time2 * 1000, 2) . "ms\n";

echo "\nThird analysis with same instance as first...\n";
$start3 = microtime(true);
$result3 = $analyzer1->analyze(\App\Http\Requests\StoreUserRequest::class);
$time3 = microtime(true) - $start3;
echo "Time: " . number_format($time3 * 1000, 2) . "ms\n";

if ($time2 < $time1 * 0.5) {
    echo "\nFile-based cache might be active (second new instance was faster)\n";
} else {
    echo "\nFile-based cache appears to be disabled\n";
}

if ($time3 < $time1 * 0.1) {
    echo "In-memory cache is active within the same instance\n";
} else {
    echo "No in-memory cache detected\n";
}