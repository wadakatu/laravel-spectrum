<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$analyzer = app(\LaravelSpectrum\Analyzers\FormRequestAnalyzer::class);

$result = $analyzer->analyze(\App\Http\Requests\StoreUserRequest::class);

echo "Full Analysis Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT);

echo "\n\nChecking if analyze returns parameters array:\n";
if (isset($result['rules'])) {
    echo "Result contains 'rules' key, not parameters array. Need to generate parameters.\n";

    // Test the enum analyzer directly
    $enumAnalyzer = new \LaravelSpectrum\Analyzers\EnumAnalyzer;

    echo "\nTesting EnumAnalyzer with different rules:\n";

    // Test with the string representations from AST
    $testRules = [
        'Rule::enum(UserRole::class)',
        'new Enum(UserStatus::class)',
    ];

    foreach ($testRules as $rule) {
        echo "\nRule: $rule\n";
        $enumResult = $enumAnalyzer->analyzeValidationRule($rule, 'App\\Http\\Requests', [
            'UserRole' => 'App\\Enums\\UserRole',
            'UserStatus' => 'App\\Enums\\UserStatus',
            'Enum' => 'Illuminate\\Validation\\Rules\\Enum',
            'Rule' => 'Illuminate\\Validation\\Rule',
        ]);

        if ($enumResult) {
            echo "Enum detected!\n";
            echo '  Class: '.$enumResult['class']."\n";
            echo '  Values: '.json_encode($enumResult['values'])."\n";
            echo '  Type: '.$enumResult['type']."\n";
        } else {
            echo "No enum detected.\n";
        }
    }
}
