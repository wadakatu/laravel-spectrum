<?php

require 'vendor/autoload.php';

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\ExampleValueFactory;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Support\TypeInference;
use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter\Standard;

$analyzer = new InlineValidationAnalyzer(
    new TypeInference(new NodeTraverser, new Standard),
    new EnumAnalyzer,
    new FileUploadAnalyzer
);

$faker = \Faker\Factory::create();
$generator = new SchemaGenerator(
    new TypeInference(new NodeTraverser, new Standard),
    new ExampleGenerator(new ExampleValueFactory($faker))
);

// Test with array rules
$rules = [
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'unique:users,email'],
];

echo "Testing schema generation with array rules:\n";
$schema = $generator->generateFromRules($rules);

echo "Schema for 'name' field:\n";
echo '  Type: '.($schema['properties']['name']['type'] ?? 'MISSING')."\n";
echo '  Full: '.json_encode($schema['properties']['name'] ?? [], JSON_PRETTY_PRINT)."\n";

echo "\nSchema for 'email' field:\n";
echo '  Type: '.($schema['properties']['email']['type'] ?? 'MISSING')."\n";
echo '  Full: '.json_encode($schema['properties']['email'] ?? [], JSON_PRETTY_PRINT)."\n";

echo "\nFull schema:\n";
echo json_encode($schema, JSON_PRETTY_PRINT)."\n";
