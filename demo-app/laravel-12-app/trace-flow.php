<?php

require 'vendor/autoload.php';

use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\DTO\Collections\ValidationRuleCollection;

// Clear cache first
foreach (glob('storage/app/spectrum/cache/*.cache') as $file) {
    unlink($file);
}

echo "=== Testing FileUploadAnalyzer directly ===\n";

$rules = [
    'avatar' => ['nullable', 'image'],
    'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx'],
];

$fileUploadAnalyzer = new FileUploadAnalyzer;
$fileFields = $fileUploadAnalyzer->analyzeRulesToResult($rules);

echo 'File fields detected: '.json_encode(array_keys($fileFields))."\n";
foreach ($fileFields as $field => $info) {
    echo "  $field: ".json_encode($info->toArray(), JSON_PRETTY_PRINT)."\n";
}

echo "\n=== Testing ValidationRuleCollection ===\n";

$collection = ValidationRuleCollection::from(['nullable', 'image']);
echo 'Rules: '.json_encode($collection->all())."\n";
echo 'hasFileRule(): '.($collection->hasFileRule() ? 'true' : 'false')."\n";

echo "\n=== Testing FormRequestAnalyzer ===\n";

$analyzer = app(FormRequestAnalyzer::class);

// Get the AdvancedFormRequest parameters
$parameters = $analyzer->analyze(\App\Http\Requests\AdvancedFormRequest::class);

echo 'Total parameters: '.count($parameters)."\n";

// Find avatar and resume
foreach ($parameters as $param) {
    if (in_array($param['name'], ['avatar', 'resume'])) {
        echo "\n".$param['name'].":\n";
        echo '  type: '.$param['type']."\n";
        echo '  format: '.($param['format'] ?? 'null')."\n";
        echo '  validation: '.json_encode($param['validation'])."\n";
    }
}
