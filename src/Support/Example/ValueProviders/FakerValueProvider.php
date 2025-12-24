<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support\Example\ValueProviders;

use DateTime;
use Faker\Generator as FakerGenerator;
use LaravelSpectrum\Contracts\ExampleGenerationStrategy;
use LaravelSpectrum\Support\Example\FieldPatternRegistry;

/**
 * Strategy that generates example values using Faker.
 */
final class FakerValueProvider implements ExampleGenerationStrategy
{
    public function __construct(
        private FakerGenerator $faker,
        private FieldPatternRegistry $registry
    ) {}

    /**
     * {@inheritDoc}
     */
    public function generate(string $fieldName, array $config): mixed
    {
        // Check registry for field pattern
        $pattern = $this->registry->getConfig($fieldName);

        if ($pattern !== null && $pattern['fakerMethod'] !== null) {
            return $this->invokeFakerMethod($pattern['fakerMethod'], $pattern['fakerArgs']);
        }

        // Fall back to type-based generation
        $type = $config['type'] ?? 'string';
        $format = $config['format'] ?? null;

        if ($format !== null) {
            return $this->generateByFormat($format);
        }

        return $this->generateByType($type, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function generateByFormat(string $format): mixed
    {
        return match ($format) {
            'email' => $this->faker->safeEmail(),
            'uri', 'url' => $this->faker->url(),
            'uuid' => $this->faker->uuid(),
            'date' => $this->faker->date('Y-m-d'),
            'time' => $this->faker->time('H:i:s'),
            'date-time' => $this->faker->dateTime()->format('Y-m-d\TH:i:s\Z'),
            'password' => 'hashed_'.$this->faker->lexify('????????'),
            'byte' => base64_encode($this->faker->text(20)),
            'binary' => $this->faker->sha256(),
            'ipv4' => $this->faker->ipv4(),
            'ipv6' => $this->faker->ipv6(),
            'hostname' => $this->faker->domainName(),
            default => $this->faker->word(),
        };
    }

    /**
     * {@inheritDoc}
     */
    public function generateByType(string $type, array $constraints = []): mixed
    {
        return match ($type) {
            'integer' => $this->generateInteger($constraints),
            'number' => $this->generateNumber($constraints),
            'boolean' => $this->faker->boolean(),
            'array' => [],
            'object' => new \stdClass,
            default => $this->generateString($constraints),
        };
    }

    /**
     * Get the underlying Faker instance.
     */
    public function getFaker(): FakerGenerator
    {
        return $this->faker;
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
                    // Last part - call with args
                    $result = $result->$part(...$args);
                } else {
                    // Intermediate part - call without args
                    $result = $result->$part;
                }
            }

            return $result;
        }

        $result = $this->faker->$method(...$args);

        // Format DateTime objects
        if ($result instanceof DateTime) {
            return $result->format('Y-m-d\TH:i:s\Z');
        }

        return $result;
    }

    /**
     * Generate integer with constraints.
     */
    private function generateInteger(array $constraints): int
    {
        $min = $constraints['minimum'] ?? 1;
        $max = $constraints['maximum'] ?? 1000000;

        return $this->faker->numberBetween($min, $max);
    }

    /**
     * Generate number (float) with constraints.
     */
    private function generateNumber(array $constraints): float
    {
        $min = $constraints['minimum'] ?? 0;
        $max = $constraints['maximum'] ?? 1000000;

        return $this->faker->randomFloat(2, $min, $max);
    }

    /**
     * Generate string with constraints.
     */
    private function generateString(array $constraints): string
    {
        $maxLength = $constraints['maxLength'] ?? 255;

        if ($maxLength <= 10) {
            return $this->faker->lexify(str_repeat('?', $maxLength));
        }

        if ($maxLength > 1000) {
            return $this->faker->paragraphs(3, true);
        }

        if ($maxLength > 100) {
            return $this->faker->paragraph();
        }

        return $this->faker->sentence();
    }
}
