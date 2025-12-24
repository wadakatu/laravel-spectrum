<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support\Example\ValueProviders;

use DateTime;
use Faker\Generator as FakerGenerator;
use Illuminate\Support\Facades\Log;
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
        $pattern = $this->registry->getConfig($fieldName);

        if ($pattern !== null) {
            // If fakerMethod is set, invoke it
            if ($pattern['fakerMethod'] !== null) {
                return $this->invokeFakerMethod($pattern['fakerMethod'], $pattern['fakerArgs']);
            }

            // Handle special cases where fakerMethod is null but pattern exists
            // (e.g., password fields that need special handling)
            if ($pattern['format'] !== null) {
                return $this->generateByFormat($pattern['format']);
            }
        }

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
            'date-time', 'datetime' => $this->faker->dateTime()->format('Y-m-d\TH:i:s\Z'),
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
            'string' => $this->generateString($constraints),
            default => $this->handleUnknownType($type, $constraints),
        };
    }

    /**
     * Handle unknown OpenAPI types with logging.
     */
    private function handleUnknownType(string $type, array $constraints): string
    {
        Log::warning("Unknown OpenAPI type '{$type}' encountered. Falling back to string generation.");

        return $this->generateString($constraints);
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
     *
     * @param  string  $method  The Faker method name (supports chained methods like 'unique->numberBetween')
     * @param  array<int, mixed>  $args  Arguments to pass to the method
     * @return mixed The generated value
     *
     * @throws \RuntimeException If the Faker method is invalid or arguments are incorrect
     */
    private function invokeFakerMethod(string $method, array $args): mixed
    {
        try {
            $result = $this->executeMethodChain($method, $args);

            // Format DateTime objects consistently
            if ($result instanceof DateTime) {
                return $result->format('Y-m-d\TH:i:s\Z');
            }

            return $result;
        } catch (\BadMethodCallException|\InvalidArgumentException $e) {
            Log::error("Invalid Faker method '{$method}' in field pattern registry.", [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "Invalid Faker method '{$method}' in field pattern registry. "
                .'Check FieldPatternRegistry for typos. Original error: '.$e->getMessage(),
                0,
                $e
            );
        } catch (\ArgumentCountError|\TypeError $e) {
            Log::error("Invalid arguments for Faker method '{$method}'.", [
                'method' => $method,
                'args' => $args,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "Invalid arguments for Faker method '{$method}': ".json_encode($args).'. '
                .'Original error: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Execute a Faker method chain.
     */
    private function executeMethodChain(string $method, array $args): mixed
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

        return $this->faker->$method(...$args);
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
