<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Cache enabled: " . (config('spectrum.cache.enabled') ? 'YES' : 'NO') . "\n\n";

// Initial analysis
$analyzer = new \LaravelSpectrum\Analyzers\FormRequestAnalyzer(
    new \LaravelSpectrum\Support\TypeInference,
    new \LaravelSpectrum\Cache\DocumentationCache,
    new \LaravelSpectrum\Analyzers\EnumAnalyzer
);

echo "=== Initial analysis ===\n";
$result1 = $analyzer->analyze(\App\Http\Requests\StoreUserRequest::class);
$roleEnum = null;
foreach ($result1 as $param) {
    if (isset($param['name']) && $param['name'] === 'role') {
        $roleEnum = $param['enum'] ?? null;
        break;
    }
}
if ($roleEnum) {
    echo "Role enum values: " . json_encode($roleEnum['values']) . "\n";
} else {
    echo "Role enum not found\n";
}

echo "\n=== Modifying FormRequest (adding new enum value) ===\n";
echo "Please manually edit StoreUserRequest and change UserRole enum usage,\n";
echo "then press Enter to continue...";
fgets(STDIN);

// Re-analyze after change
echo "\n=== Re-analyzing after change ===\n";
$analyzer2 = new \LaravelSpectrum\Analyzers\FormRequestAnalyzer(
    new \LaravelSpectrum\Support\TypeInference,
    new \LaravelSpectrum\Cache\DocumentationCache,
    new \LaravelSpectrum\Analyzers\EnumAnalyzer
);

$result2 = $analyzer2->analyze(\App\Http\Requests\StoreUserRequest::class);
$roleEnum2 = null;
foreach ($result2 as $param) {
    if (isset($param['name']) && $param['name'] === 'role') {
        $roleEnum2 = $param['enum'] ?? null;
        break;
    }
}
if ($roleEnum2) {
    echo "Role enum values after change: " . json_encode($roleEnum2['values']) . "\n";
} else {
    echo "Role enum not found after change\n";
}