<?php

namespace LaravelSpectrum\Formatters;

use Illuminate\Support\Str;

class RequestExampleFormatter
{
    /**
     * Generate example value based on type and field name
     */
    public function generateExample(string $type, string $fieldName): mixed
    {
        // Check field name for hints
        $fieldNameLower = strtolower($fieldName);

        if ($type === 'string') {
            return $this->generateStringExample($fieldNameLower, $fieldName);
        }

        return match ($type) {
            'integer' => $this->generateIntegerExample($fieldNameLower),
            'number' => $this->generateNumberExample($fieldNameLower),
            'boolean' => $this->generateBooleanExample($fieldNameLower),
            'array' => $this->generateArrayExample($fieldNameLower),
            default => "example_{$fieldName}",
        };
    }

    /**
     * Generate example from OpenAPI schema
     */
    public function generateFromSchema(array $schema): mixed
    {
        // If schema has an example, use it
        if (isset($schema['example'])) {
            return $schema['example'];
        }

        // Handle different schema types
        if (isset($schema['type'])) {
            return match ($schema['type']) {
                'object' => $this->generateObjectFromSchema($schema),
                'array' => $this->generateArrayFromSchema($schema),
                default => $this->generatePrimitiveFromSchema($schema),
            };
        }

        // Handle allOf
        if (isset($schema['allOf'])) {
            return $this->generateFromAllOf($schema['allOf']);
        }

        // Handle references (simplified)
        if (isset($schema['$ref'])) {
            return [];
        }

        return null;
    }

    private function generateStringExample(string $fieldNameLower, string $fieldName): string
    {
        // Check for specific patterns in field name
        if (str_contains($fieldNameLower, 'email')) {
            return 'user@example.com';
        }

        if (str_contains($fieldNameLower, 'phone')) {
            return '+1234567890';
        }

        if (str_contains($fieldNameLower, 'url') || str_contains($fieldNameLower, 'uri')) {
            return 'https://example.com';
        }

        if (str_contains($fieldNameLower, 'password')) {
            return 'securePassword123!';
        }

        if (str_contains($fieldNameLower, 'uuid')) {
            return Str::uuid()->toString();
        }

        if (str_contains($fieldNameLower, 'date') && ! str_contains($fieldNameLower, 'update')) {
            return now()->format('Y-m-d');
        }

        if (str_contains($fieldNameLower, 'time') || str_contains($fieldNameLower, '_at')) {
            return now()->toIso8601String();
        }

        if (str_contains($fieldNameLower, 'name')) {
            return "Example {$fieldName}";
        }

        if (str_contains($fieldNameLower, 'description') || str_contains($fieldNameLower, 'bio')) {
            return "This is an example {$fieldName}";
        }

        if (str_contains($fieldNameLower, 'token') || str_contains($fieldNameLower, 'key')) {
            return Str::random(32);
        }

        return "example_{$fieldName}";
    }

    private function generateIntegerExample(string $fieldNameLower): int
    {
        if (str_contains($fieldNameLower, 'id')) {
            return rand(1, 1000);
        }

        if (str_contains($fieldNameLower, 'age')) {
            return rand(18, 80);
        }

        if (str_contains($fieldNameLower, 'count') || str_contains($fieldNameLower, 'quantity')) {
            return rand(1, 100);
        }

        if (str_contains($fieldNameLower, 'price') || str_contains($fieldNameLower, 'amount')) {
            return rand(100, 10000);
        }

        if (str_contains($fieldNameLower, 'year')) {
            return (int) date('Y');
        }

        return rand(1, 100);
    }

    private function generateNumberExample(string $fieldNameLower): float
    {
        if (str_contains($fieldNameLower, 'price') || str_contains($fieldNameLower, 'amount')) {
            return round(rand(1000, 100000) / 100, 2);
        }

        if (str_contains($fieldNameLower, 'rate') || str_contains($fieldNameLower, 'percentage')) {
            return round(rand(0, 10000) / 100, 2);
        }

        if (str_contains($fieldNameLower, 'latitude')) {
            return round(rand(-90000, 90000) / 1000, 6);
        }

        if (str_contains($fieldNameLower, 'longitude')) {
            return round(rand(-180000, 180000) / 1000, 6);
        }

        return round(rand(0, 10000) / 100, 2);
    }

    private function generateBooleanExample(string $fieldNameLower): bool
    {
        // Prefer true for "is_", "has_", "can_" prefixes
        if (str_starts_with($fieldNameLower, 'is_') ||
            str_starts_with($fieldNameLower, 'has_') ||
            str_starts_with($fieldNameLower, 'can_')) {
            return true;
        }

        // Active/enabled fields are usually true
        if (str_contains($fieldNameLower, 'active') ||
            str_contains($fieldNameLower, 'enabled') ||
            str_contains($fieldNameLower, 'published')) {
            return true;
        }

        // Deleted/disabled fields are usually false
        if (str_contains($fieldNameLower, 'deleted') ||
            str_contains($fieldNameLower, 'disabled') ||
            str_contains($fieldNameLower, 'archived')) {
            return false;
        }

        return (bool) rand(0, 1);
    }

    private function generateArrayExample(string $fieldNameLower): array
    {
        if (str_contains($fieldNameLower, 'tags')) {
            return ['tag1', 'tag2', 'tag3'];
        }

        if (str_contains($fieldNameLower, 'categories')) {
            return ['category1', 'category2'];
        }

        if (str_contains($fieldNameLower, 'roles')) {
            return ['user', 'admin'];
        }

        if (str_contains($fieldNameLower, 'permissions')) {
            return ['read', 'write'];
        }

        return ['item1', 'item2', 'item3'];
    }

    private function generateObjectFromSchema(array $schema): array
    {
        $result = [];

        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $property => $propertySchema) {
                $result[$property] = $this->generateFromSchema($propertySchema);
            }
        }

        return $result;
    }

    private function generateArrayFromSchema(array $schema): array
    {
        $minItems = $schema['minItems'] ?? 1;
        $maxItems = $schema['maxItems'] ?? 3;
        $count = rand($minItems, $maxItems);

        if (! isset($schema['items'])) {
            return array_fill(0, $count, 'example');
        }

        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->generateFromSchema($schema['items']);
        }

        return $result;
    }

    private function generatePrimitiveFromSchema(array $schema): mixed
    {
        $type = $schema['type'] ?? 'string';
        $format = $schema['format'] ?? null;

        // Handle enums
        if (isset($schema['enum']) && ! empty($schema['enum'])) {
            return $schema['enum'][0];
        }

        // Handle specific formats
        if ($type === 'string' && $format) {
            return match ($format) {
                'email' => 'user@example.com',
                'uuid' => Str::uuid()->toString(),
                'date' => now()->format('Y-m-d'),
                'date-time' => now()->toIso8601String(),
                'uri', 'url' => 'https://example.com',
                'ipv4' => '192.168.1.1',
                'ipv6' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
                default => 'example_string',
            };
        }

        // Handle constraints
        if ($type === 'integer') {
            $min = $schema['minimum'] ?? 1;
            $max = $schema['maximum'] ?? 100;

            return rand($min, $max);
        }

        if ($type === 'number') {
            $min = $schema['minimum'] ?? 0;
            $max = $schema['maximum'] ?? 100;

            return round(rand($min * 100, $max * 100) / 100, 2);
        }

        if ($type === 'string') {
            $minLength = $schema['minLength'] ?? 3;
            $maxLength = $schema['maxLength'] ?? 50;
            $length = rand($minLength, min($maxLength, 20));

            // Generate string of appropriate length
            return Str::random($length);
        }

        // Default generation by type
        return $this->generateExample($type, 'field');
    }

    private function generateFromAllOf(array $allOf): array
    {
        $result = [];

        foreach ($allOf as $schema) {
            $generated = $this->generateFromSchema($schema);
            if (is_array($generated)) {
                $result = array_merge($result, $generated);
            }
        }

        return $result;
    }
}
