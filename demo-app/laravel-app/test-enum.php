<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$analyzer = new \LaravelSpectrum\Analyzers\FormRequestAnalyzer(
    new \LaravelSpectrum\Support\TypeInference,
    new \LaravelSpectrum\Cache\DocumentationCache,
    new \LaravelSpectrum\Analyzers\EnumAnalyzer
);

$result = $analyzer->analyze(\App\Http\Requests\StoreUserRequest::class);

echo "FormRequest Analysis Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT);
