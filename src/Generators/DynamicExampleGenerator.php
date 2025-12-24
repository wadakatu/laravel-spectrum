<?php

declare(strict_types=1);

namespace LaravelSpectrum\Generators;

use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use LaravelSpectrum\Support\Example\FieldPatternRegistry;
use LaravelSpectrum\Support\Example\ValueProviders\FakerValueProvider;

/**
 * Generates dynamic example data from OpenAPI schemas.
 *
 * Used by MockServer for generating realistic API responses.
 * Supports schema constraints (min/max, enum, pattern) and recursive object/array generation.
 */
class DynamicExampleGenerator
{
    private Faker $faker;

    private FieldPatternRegistry $registry;

    private FakerValueProvider $valueProvider;

    public function __construct(
        ?FieldPatternRegistry $registry = null,
        ?Faker $faker = null
    ) {
        $this->faker = $faker ?? FakerFactory::create();
        $this->registry = $registry ?? new FieldPatternRegistry;
        $this->valueProvider = new FakerValueProvider($this->faker, $this->registry);
    }

    /**
     * Generate example data from OpenAPI schema.
     */
    public function generateFromSchema(array $schema, array $options = []): mixed
    {
        // If example is provided, use it
        if (isset($schema['example'])) {
            return $schema['example'];
        }

        // Generate based on type
        return match ($schema['type'] ?? 'string') {
            'string' => $this->generateString($schema, $options),
            'integer' => $this->generateInteger($schema),
            'number' => $this->generateNumber($schema),
            'boolean' => $this->generateBoolean(),
            'array' => $this->generateArray($schema, $options),
            'object' => $this->generateObject($schema, $options),
            default => $this->generateString($schema, $options),
        };
    }

    /**
     * Set faker instance (useful for testing with seed).
     */
    public function setFaker(Faker $faker): void
    {
        $this->faker = $faker;
        $this->valueProvider = new FakerValueProvider($this->faker, $this->registry);
    }

    /**
     * Generate string example.
     */
    private function generateString(array $schema, array $options = []): string
    {
        // Handle enum
        if (isset($schema['enum'])) {
            return $this->faker->randomElement($schema['enum']);
        }

        // Handle format
        if (isset($schema['format'])) {
            return $this->generateFormattedString($schema['format']);
        }

        // Generate based on field name if realistic data is requested
        if ($options['use_realistic_data'] ?? false) {
            $fieldName = $options['field_name'] ?? '';
            $realisticValue = $this->generateRealisticString($fieldName);
            if ($realisticValue !== null) {
                return $realisticValue;
            }
        }

        // Handle length constraints
        $minLength = $schema['minLength'] ?? 1;
        $maxLength = $schema['maxLength'] ?? 50;

        // Handle pattern
        if (isset($schema['pattern'])) {
            return $this->generateFromPattern($schema['pattern'], $minLength, $maxLength);
        }

        // Generate random string
        $length = $this->faker->numberBetween($minLength, min($maxLength, 20));

        return $this->faker->lexify(str_repeat('?', $length));
    }

    /**
     * Generate formatted string.
     */
    private function generateFormattedString(string $format): string
    {
        return $this->valueProvider->generateByFormat($format);
    }

    /**
     * Generate realistic string based on field name using registry.
     */
    private function generateRealisticString(string $fieldName): ?string
    {
        if (empty($fieldName)) {
            return null;
        }

        $config = $this->registry->matchPattern($fieldName);

        if ($config !== null && $config['fakerMethod'] !== null) {
            $result = $this->invokeFakerMethod($config['fakerMethod'], $config['fakerArgs']);

            return is_string($result) ? $result : (string) $result;
        }

        return null;
    }

    /**
     * Generate string from pattern.
     */
    private function generateFromPattern(string $pattern, int $minLength, int $maxLength): string
    {
        // For complex patterns, return a simple string that likely matches
        return $this->faker->lexify(str_repeat('?', $minLength));
    }

    /**
     * Generate integer example.
     */
    private function generateInteger(array $schema): int
    {
        $min = $schema['minimum'] ?? 0;
        $max = $schema['maximum'] ?? 100;

        if (isset($schema['enum'])) {
            return (int) $this->faker->randomElement($schema['enum']);
        }

        return $this->faker->numberBetween($min, $max);
    }

    /**
     * Generate number example.
     */
    private function generateNumber(array $schema): float
    {
        $min = $schema['minimum'] ?? 0;
        $max = $schema['maximum'] ?? 100;

        if (isset($schema['enum'])) {
            return (float) $this->faker->randomElement($schema['enum']);
        }

        return $this->faker->randomFloat(2, $min, $max);
    }

    /**
     * Generate boolean example.
     */
    private function generateBoolean(): bool
    {
        return $this->faker->boolean();
    }

    /**
     * Generate array example.
     */
    private function generateArray(array $schema, array $options = []): array
    {
        $minItems = $schema['minItems'] ?? 1;
        $maxItems = $schema['maxItems'] ?? 5;
        $count = $this->faker->numberBetween($minItems, $maxItems);

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            if (isset($schema['items'])) {
                $items[] = $this->generateFromSchema($schema['items'], $options);
            } else {
                $items[] = $this->faker->word();
            }
        }

        return $items;
    }

    /**
     * Generate object example.
     */
    private function generateObject(array $schema, array $options = []): array
    {
        $object = [];

        // Generate all properties
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $property => $propertySchema) {
                // Always generate required properties
                if (isset($schema['required']) && in_array($property, $schema['required'])) {
                    $object[$property] = $this->generateFromSchema(
                        $propertySchema,
                        array_merge($options, ['field_name' => $property])
                    );
                } else {
                    // Include optional properties sometimes (or always in test mode)
                    $includeOptional = $options['include_all_properties'] ?? $this->faker->boolean(70);
                    if ($includeOptional) {
                        $object[$property] = $this->generateFromSchema(
                            $propertySchema,
                            array_merge($options, ['field_name' => $property])
                        );
                    }
                }
            }
        }

        // Handle additionalProperties
        if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === true) {
            $additionalCount = $this->faker->numberBetween(0, 3);
            for ($i = 0; $i < $additionalCount; $i++) {
                $key = $this->faker->word();
                $object[$key] = $this->faker->word();
            }
        }

        return $object;
    }

    /**
     * Invoke a Faker method with arguments.
     */
    private function invokeFakerMethod(string $method, array $args): mixed
    {
        // Handle chained methods like 'unique->numberBetween'
        if (str_contains($method, '->')) {
            $parts = explode('->', $method);
            $result = $this->faker;
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $result = $result->$part(...$args);
                } else {
                    $result = $result->$part;
                }
            }

            return $result;
        }

        $result = $this->faker->$method(...$args);

        // Format DateTime objects
        if ($result instanceof \DateTime) {
            return $result->format('Y-m-d\TH:i:s\Z');
        }

        return $result;
    }
}
