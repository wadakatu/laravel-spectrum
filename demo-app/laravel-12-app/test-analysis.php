<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use LaravelSpectrum\Analyzers\FormRequestAnalyzer;

$analyzer = app(FormRequestAnalyzer::class);

echo "=== Testing analyzeWithConditionalRules ===\n";
$result = $analyzer->analyzeWithConditionalRules(\App\Http\Requests\AdvancedFormRequest::class);

echo 'Parameters count: '.count($result['parameters'] ?? [])."\n";
echo 'Has conditional rules: '.(! empty($result['conditional_rules']['rules_sets']) ? 'yes' : 'no')."\n";

// Find avatar and resume
foreach ($result['parameters'] ?? [] as $param) {
    if (in_array($param['name'], ['avatar', 'resume'])) {
        echo "\n".$param['name'].":\n";
        echo '  type: '.($param['type'] ?? 'not set')."\n";
        echo '  format: '.($param['format'] ?? 'not set')."\n";
    }
}
