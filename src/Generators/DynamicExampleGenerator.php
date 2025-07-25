<?php

namespace LaravelSpectrum\Generators;

use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

class DynamicExampleGenerator
{
    private Faker $faker;

    public function __construct()
    {
        $this->faker = FakerFactory::create();
    }

    /**
     * Generate example data from OpenAPI schema
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
     * Generate string example
     */
    private function generateString(array $schema, array $options = []): string
    {
        // Handle enum
        if (isset($schema['enum'])) {
            return $this->faker->randomElement($schema['enum']);
        }

        // Handle format
        if (isset($schema['format'])) {
            return $this->generateFormattedString($schema['format'], $options);
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
     * Generate formatted string
     */
    private function generateFormattedString(string $format, array $options = []): string
    {
        return match ($format) {
            'email' => $this->faker->safeEmail(),
            'uri', 'url' => $this->faker->url(),
            'uuid' => $this->faker->uuid(),
            'date' => $this->faker->date('Y-m-d'),
            'date-time' => $this->faker->date('Y-m-d\TH:i:s\Z'),
            'time' => $this->faker->time('H:i:s'),
            'hostname' => $this->faker->domainName(),
            'ipv4' => $this->faker->ipv4(),
            'ipv6' => $this->faker->ipv6(),
            'password' => Str::random(12),
            default => $this->faker->word(),
        };
    }

    /**
     * Generate realistic string based on field name
     */
    private function generateRealisticString(string $fieldName): ?string
    {
        $fieldName = strtolower($fieldName);

        // Common field name patterns
        $patterns = [
            '/first_?name/i' => fn () => $this->faker->firstName(),
            '/last_?name/i' => fn () => $this->faker->lastName(),
            '/full_?name|name/i' => fn () => $this->faker->name(),
            '/email/i' => fn () => $this->faker->safeEmail(),
            '/phone/i' => fn () => $this->faker->phoneNumber(),
            '/address/i' => fn () => $this->faker->address(),
            '/street/i' => fn () => $this->faker->streetAddress(),
            '/city/i' => fn () => $this->faker->city(),
            '/state/i' => fn () => $this->faker->word(),
            '/country/i' => fn () => $this->faker->country(),
            '/zip|postal/i' => fn () => $this->faker->postcode(),
            '/company/i' => fn () => $this->faker->company(),
            '/title/i' => fn () => $this->faker->sentence(3),
            '/description|bio/i' => fn () => $this->faker->paragraph(),
            '/url|website/i' => fn () => $this->faker->url(),
            '/username/i' => fn () => $this->faker->userName(),
            '/slug/i' => fn () => $this->faker->slug(),
        ];

        foreach ($patterns as $pattern => $generator) {
            if (preg_match($pattern, $fieldName)) {
                return $generator();
            }
        }

        return null;
    }

    /**
     * Generate string from pattern
     */
    private function generateFromPattern(string $pattern, int $minLength, int $maxLength): string
    {
        // For complex patterns, return a simple string that likely matches
        // This is a simplified implementation
        return $this->faker->lexify(str_repeat('?', $minLength));
    }

    /**
     * Generate integer example
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
     * Generate number example
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
     * Generate boolean example
     */
    private function generateBoolean(): bool
    {
        return $this->faker->boolean();
    }

    /**
     * Generate array example
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
     * Generate object example
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
            // Add some random properties
            $additionalCount = $this->faker->numberBetween(0, 3);
            for ($i = 0; $i < $additionalCount; $i++) {
                $key = $this->faker->word();
                $object[$key] = $this->faker->word();
            }
        }

        return $object;
    }

    /**
     * Set faker instance (useful for testing with seed)
     */
    public function setFaker(Faker $faker): void
    {
        $this->faker = $faker;
    }
}
