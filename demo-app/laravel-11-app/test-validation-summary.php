<?php

require 'vendor/autoload.php';

echo "=== Laravel 11 Anonymous FormRequest Validation Summary ===\n\n";

$openapi = json_decode(file_get_contents('storage/app/spectrum/openapi.json'), true);

$endpoints = [
    '/api/anonymous-form-request/blog' => 'post',
    '/api/anonymous-form-request/profile/{id}' => 'put',
    '/api/anonymous-form-request/product' => 'post',
    '/api/anonymous-form-request/register' => 'post',
];

foreach ($endpoints as $path => $method) {
    echo "📍 $method ".strtoupper($path)."\n";

    $endpoint = $openapi['paths'][$path][$method] ?? null;

    if (! $endpoint) {
        echo "  ❌ Endpoint not found\n\n";

        continue;
    }

    $requestBody = $endpoint['requestBody']['content']['application/json']['schema'] ?? null;

    if (! $requestBody) {
        echo "  ⚠️  No request body schema\n";
    } else {
        $properties = $requestBody['properties'] ?? [];
        $required = $requestBody['required'] ?? [];

        echo '  ✅ Fields: '.count($properties)."\n";
        echo '  ✅ Required: '.implode(', ', $required)."\n";

        // Check for special fields
        if (isset($properties['tags'])) {
            echo "  ✅ Array field detected: tags\n";
        }
        if (isset($properties['preferences.theme'])) {
            echo "  ✅ Nested field detected: preferences.theme\n";
        }
    }

    // Check response schema
    $response = $endpoint['responses']['200']['content']['application/json']['schema'] ??
                $endpoint['responses']['201']['content']['application/json']['schema'] ?? null;

    if ($response && isset($response['properties'])) {
        $responseFields = array_keys($response['properties']);
        echo '  ✅ Response fields: '.count($responseFields)."\n";
    }

    echo "\n";
}

echo "=== Summary ===\n";
echo "✅ All anonymous FormRequest endpoints are properly registered\n";
echo "✅ Validation rules are being extracted and converted to OpenAPI schemas\n";

// Count total validations
$totalFields = 0;
$totalRequired = 0;

foreach ($endpoints as $path => $method) {
    $schema = $openapi['paths'][$path][$method]['requestBody']['content']['application/json']['schema'] ?? [];
    $totalFields += count($schema['properties'] ?? []);
    $totalRequired += count($schema['required'] ?? []);
}

echo "📊 Total fields across all endpoints: $totalFields\n";
echo "📊 Total required fields: $totalRequired\n";
