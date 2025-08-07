<?php

use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\ExampleValueFactory;
use LaravelSpectrum\Support\FieldNameInference;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test 1: Basic field generation with Faker enabled
echo "Test 1: Basic field generation with Faker enabled\n";
echo "===============================================\n";
config(['spectrum.example_generation.use_faker' => true]);
config(['spectrum.example_generation.faker_seed' => 12345]);

$factory = new ExampleValueFactory(new FieldNameInference);

echo 'Name: '.$factory->create('name', ['type' => 'string'])."\n";
echo 'Email: '.$factory->create('email', ['type' => 'string'])."\n";
echo 'Price: '.$factory->create('price', ['type' => 'number'])."\n";
echo 'Created At: '.$factory->create('created_at', ['type' => 'string', 'format' => 'date-time'])."\n";
echo 'Avatar: '.$factory->create('avatar', ['type' => 'string'])."\n";
echo 'Age: '.$factory->create('age', ['type' => 'integer'])."\n";

// Test 2: Custom generator
echo "\nTest 2: Custom generator\n";
echo "=======================\n";
$customGenerator = fn ($faker) => $faker->randomElement(['pending', 'processing', 'completed']);
echo 'Status with custom generator: '.$factory->create('status', ['type' => 'string'], $customGenerator)."\n";

// Test 3: ProductResource with custom examples
echo "\nTest 3: ProductResource with custom examples\n";
echo "===========================================\n";
$generator = new ExampleGenerator($factory);
$productSchema = [
    'properties' => [
        'id' => ['type' => 'integer'],
        'name' => ['type' => 'string'],
        'price' => ['type' => 'number'],
        'currency' => ['type' => 'string'],
        'status' => ['type' => 'string'],
        'tags' => ['type' => 'array'],
        'created_at' => ['type' => 'string', 'format' => 'date-time'],
    ],
];

$example = $generator->generateFromResource($productSchema, \App\Http\Resources\ProductResource::class);
echo json_encode($example, JSON_PRETTY_PRINT)."\n";

// Test 4: Faker disabled (fallback to static values)
echo "\nTest 4: Faker disabled (fallback to static values)\n";
echo "=================================================\n";
config(['spectrum.example_generation.use_faker' => false]);
$factory2 = new ExampleValueFactory(new FieldNameInference);

echo 'Name (static): '.$factory2->create('name', ['type' => 'string'])."\n";
echo 'Email (static): '.$factory2->create('email', ['type' => 'string'])."\n";
