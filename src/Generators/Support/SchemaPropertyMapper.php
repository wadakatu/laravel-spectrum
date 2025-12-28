<?php

declare(strict_types=1);

namespace LaravelSpectrum\Generators\Support;

use LaravelSpectrum\DTO\EnumInfo;

/**
 * Maps properties from source arrays to OpenAPI schema format.
 *
 * Centralizes the repetitive property copying logic used across
 * SchemaGenerator, ResponseSchemaGenerator, and FileUploadSchemaGenerator.
 */
final class SchemaPropertyMapper
{
    /**
     * Simple properties that can be directly copied without transformation.
     *
     * @var array<string>
     */
    private const SIMPLE_PROPERTIES = [
        'description',
        'example',
        'format',
        'pattern',
        'default',
    ];

    /**
     * Numeric constraint properties.
     *
     * @var array<string>
     */
    private const NUMERIC_CONSTRAINTS = [
        'minimum',
        'maximum',
        'exclusiveMinimum',
        'exclusiveMaximum',
        'multipleOf',
    ];

    /**
     * String constraint properties.
     *
     * @var array<string>
     */
    private const STRING_CONSTRAINTS = [
        'minLength',
        'maxLength',
    ];

    /**
     * Array constraint properties.
     *
     * @var array<string>
     */
    private const ARRAY_CONSTRAINTS = [
        'minItems',
        'maxItems',
        'uniqueItems',
    ];

    /**
     * Boolean properties that should only be set when true.
     *
     * @var array<string>
     */
    private const BOOLEAN_PROPERTIES = [
        'nullable',
        'readOnly',
        'writeOnly',
        'deprecated',
    ];

    /**
     * Map all standard properties from source to target schema.
     *
     * @param  array<string, mixed>  $source  The source data containing properties
     * @param  array<string, mixed>  $target  The target schema array (modified in place)
     * @return array<string, mixed> The modified target array
     */
    public function mapAll(array $source, array $target = []): array
    {
        $target = $this->mapSimpleProperties($source, $target);
        $target = $this->mapConstraints($source, $target);
        $target = $this->mapEnum($source, $target);
        $target = $this->mapBooleanProperties($source, $target);

        return $target;
    }

    /**
     * Map simple properties that can be directly copied.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    public function mapSimpleProperties(array $source, array $target = []): array
    {
        foreach (self::SIMPLE_PROPERTIES as $property) {
            if (isset($source[$property])) {
                $target[$property] = $source[$property];
            }
        }

        return $target;
    }

    /**
     * Map constraint properties (numeric, string, array).
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    public function mapConstraints(array $source, array $target = []): array
    {
        $allConstraints = array_merge(
            self::NUMERIC_CONSTRAINTS,
            self::STRING_CONSTRAINTS,
            self::ARRAY_CONSTRAINTS
        );

        foreach ($allConstraints as $constraint) {
            if (isset($source[$constraint])) {
                $target[$constraint] = $source[$constraint];
            }
        }

        return $target;
    }

    /**
     * Map enum property with support for EnumInfo DTO and legacy array structure.
     *
     * Handles:
     * - EnumInfo DTO from EnumAnalyzer
     * - Simple enum arrays: ['active', 'inactive']
     * - Legacy structured arrays: ['values' => ['active', 'inactive'], 'type' => 'string']
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    public function mapEnum(array $source, array $target = []): array
    {
        if (! isset($source['enum'])) {
            return $target;
        }

        $enum = $source['enum'];

        if ($enum instanceof EnumInfo) {
            // EnumInfo DTO from EnumAnalyzer
            $target['enum'] = $enum->values;
            $target['type'] = $enum->getOpenApiType();

            return $target;
        }

        if (is_array($enum) && isset($enum['values'])) {
            // Legacy structured enum from EnumAnalyzer
            $target['enum'] = $enum['values'];

            // Override type if specified in enum structure
            if (isset($enum['type'])) {
                $target['type'] = $enum['type'];
            }
        } else {
            // Simple enum array
            $target['enum'] = $enum;
        }

        return $target;
    }

    /**
     * Map boolean properties (only set when true).
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    public function mapBooleanProperties(array $source, array $target = []): array
    {
        foreach (self::BOOLEAN_PROPERTIES as $property) {
            if (isset($source[$property]) && $source[$property] === true) {
                $target[$property] = true;
            }
        }

        return $target;
    }

    /**
     * Map a specific list of properties from source to target.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string>  $properties  List of property names to map
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    public function mapSpecificProperties(array $source, array $properties, array $target = []): array
    {
        foreach ($properties as $property) {
            if (isset($source[$property])) {
                $target[$property] = $source[$property];
            }
        }

        return $target;
    }

    /**
     * Map type property with optional type inference.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    public function mapType(array $source, array $target = [], string $defaultType = 'string'): array
    {
        $target['type'] = $source['type'] ?? $defaultType;

        return $target;
    }
}
