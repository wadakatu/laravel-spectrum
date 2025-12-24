<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts;

/**
 * Strategy interface for generating example values.
 *
 * Implementations can generate values dynamically (Faker) or statically.
 */
interface ExampleGenerationStrategy
{
    /**
     * Generate an example value based on field name and schema configuration.
     *
     * @param  string  $fieldName  The field name to generate a value for
     * @param  array  $config  Schema configuration (type, format, constraints)
     * @return mixed The generated example value
     */
    public function generate(string $fieldName, array $config): mixed;

    /**
     * Generate a value by OpenAPI format.
     *
     * @param  string  $format  OpenAPI format (email, uuid, date-time, etc.)
     * @return mixed The generated value
     */
    public function generateByFormat(string $format): mixed;

    /**
     * Generate a value by OpenAPI type with optional constraints.
     *
     * @param  string  $type  OpenAPI type (string, integer, number, boolean, array, object)
     * @param  array  $constraints  Optional constraints (minimum, maximum, minLength, maxLength, etc.)
     * @return mixed The generated value
     */
    public function generateByType(string $type, array $constraints = []): mixed;
}
