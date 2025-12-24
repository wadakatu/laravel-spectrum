<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$analyzer = app(\LaravelSpectrum\Analyzers\FormRequestAnalyzer::class);

$result = $analyzer->analyze(\App\Http\Requests\StoreUserRequest::class);

echo "FormRequest Analysis Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT);
