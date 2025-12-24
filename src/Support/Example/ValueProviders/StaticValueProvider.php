<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support\Example\ValueProviders;

use LaravelSpectrum\Contracts\ExampleGenerationStrategy;
use LaravelSpectrum\Support\Example\FieldPatternRegistry;

/**
 * Strategy that generates example values using static predefined values.
 *
 * Useful for deterministic output and testing scenarios.
 */
final class StaticValueProvider implements ExampleGenerationStrategy
{
    public function __construct(
        private FieldPatternRegistry $registry
    ) {}

    /**
     * {@inheritDoc}
     */
    public function generate(string $fieldName, array $config): mixed
    {
        // Check registry for field pattern
        $pattern = $this->registry->getConfig($fieldName);

        if ($pattern !== null) {
            return $pattern['staticValue'];
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
            'date-time', 'datetime' => '2024-01-15T10:30:00Z',
            'date' => '2024-01-15',
            'time' => '10:30:00',
            'email' => 'user@example.com',
            'url', 'uri' => 'https://example.com',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'password' => '********',
            'ipv4' => '192.168.1.1',
            'ipv6' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'hostname' => 'example.com',
            'byte' => 'c3RyaW5n',
            'binary' => '0x1234567890abcdef',
            default => 'string',
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
            'boolean' => true,
            'array' => [],
            'object' => new \stdClass,
            default => 'string',
        };
    }

    /**
     * Generate integer with constraints.
     */
    private function generateInteger(array $constraints): int
    {
        if (isset($constraints['minimum']) || isset($constraints['maximum'])) {
            $min = $constraints['minimum'] ?? 1;
            $max = $constraints['maximum'] ?? 100;

            return (int) (($min + $max) / 2);
        }

        return 1;
    }

    /**
     * Generate number (float) with constraints.
     */
    private function generateNumber(array $constraints): float
    {
        if (isset($constraints['minimum']) || isset($constraints['maximum'])) {
            $min = $constraints['minimum'] ?? 0.0;
            $max = $constraints['maximum'] ?? 100.0;

            return ($min + $max) / 2;
        }

        return 1.0;
    }
}
