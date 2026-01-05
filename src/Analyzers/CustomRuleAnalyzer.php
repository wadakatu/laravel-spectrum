<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use Illuminate\Contracts\Validation\ValidationRule;
use LaravelSpectrum\Attributes\OpenApiSchemaAttribute;
use LaravelSpectrum\Contracts\SpectrumDescribableRule;
use LaravelSpectrum\DTO\OpenApiSchema;
use ReflectionClass;
use ReflectionProperty;

/**
 * Analyzes custom validation rule classes that implement Laravel's ValidationRule interface.
 *
 * Detection priority:
 * 1. SpectrumDescribableRule interface - explicit schema via spectrumSchema() method
 * 2. OpenApiSchemaAttribute - PHP 8 attribute on the class
 * 3. Reflection-based analysis - infer constraints from constructor parameters
 */
class CustomRuleAnalyzer
{
    /**
     * Known constructor parameter names that map to OpenAPI schema properties.
     *
     * @var array<string, string>
     */
    private const PARAMETER_MAPPINGS = [
        // String length constraints
        'minLength' => 'minLength',
        'minlength' => 'minLength',
        'min_length' => 'minLength',
        'maxLength' => 'maxLength',
        'maxlength' => 'maxLength',
        'max_length' => 'maxLength',

        // Numeric constraints
        'min' => 'minimum',
        'minimum' => 'minimum',
        'max' => 'maximum',
        'maximum' => 'maximum',

        // Pattern/format constraints
        'pattern' => 'pattern',
        'regex' => 'pattern',
        'format' => 'format',
    ];

    /**
     * Analyze a custom validation rule object and extract OpenAPI schema information.
     */
    public function analyze(ValidationRule $rule): ?OpenApiSchema
    {
        // Priority 1: Check if rule implements SpectrumDescribableRule interface
        if ($rule instanceof SpectrumDescribableRule) {
            return $rule->spectrumSchema();
        }

        $reflection = new ReflectionClass($rule);

        // Priority 2: Check for OpenApiSchemaAttribute
        $attributes = $reflection->getAttributes(OpenApiSchemaAttribute::class);
        if (count($attributes) > 0) {
            $attribute = $attributes[0]->newInstance();

            return $this->createSchemaFromAttribute($attribute);
        }

        // Priority 3: Reflection-based analysis of constructor parameters/properties
        return $this->analyzeViaReflection($rule, $reflection);
    }

    /**
     * Create an OpenApiSchema from an OpenApiSchemaAttribute.
     */
    private function createSchemaFromAttribute(OpenApiSchemaAttribute $attribute): OpenApiSchema
    {
        return new OpenApiSchema(
            type: $attribute->type,
            format: $attribute->format,
            minimum: $attribute->minimum,
            maximum: $attribute->maximum,
            minLength: $attribute->minLength,
            maxLength: $attribute->maxLength,
            pattern: $attribute->pattern,
        );
    }

    /**
     * Analyze the rule via reflection to extract constraints from properties.
     *
     * @param  ReflectionClass<ValidationRule>  $reflection
     */
    private function analyzeViaReflection(ValidationRule $rule, ReflectionClass $reflection): ?OpenApiSchema
    {
        $constraints = [];

        // Get all properties (including private/protected)
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $normalizedName = strtolower($propertyName);

            // Check if this property maps to an OpenAPI constraint
            foreach (self::PARAMETER_MAPPINGS as $paramName => $schemaProperty) {
                if ($normalizedName === strtolower($paramName) || $propertyName === $paramName) {
                    $value = $this->getPropertyValue($property, $rule);

                    if ($value !== null) {
                        $constraints[$schemaProperty] = $value;
                    }

                    break;
                }
            }
        }

        // If no constraints found, return null
        if (empty($constraints)) {
            return null;
        }

        // Determine the type based on constraints
        $type = $this->inferTypeFromConstraints($constraints);

        return new OpenApiSchema(
            type: $type,
            format: $constraints['format'] ?? null,
            minimum: $constraints['minimum'] ?? null,
            maximum: $constraints['maximum'] ?? null,
            minLength: $constraints['minLength'] ?? null,
            maxLength: $constraints['maxLength'] ?? null,
            pattern: $constraints['pattern'] ?? null,
        );
    }

    /**
     * Get the value of a property from the rule instance.
     */
    private function getPropertyValue(ReflectionProperty $property, ValidationRule $rule): mixed
    {
        $property->setAccessible(true);

        try {
            $value = $property->getValue($rule);

            // Only return scalar values that make sense for OpenAPI constraints
            if (is_int($value) || is_float($value) || is_string($value)) {
                return $value;
            }

            return null;
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    /**
     * Infer the OpenAPI type from the constraints.
     *
     * @param  array<string, mixed>  $constraints
     */
    private function inferTypeFromConstraints(array $constraints): string
    {
        // If we have min/max (numeric constraints), likely a number
        if (isset($constraints['minimum']) || isset($constraints['maximum'])) {
            // Check if values are integers
            $min = $constraints['minimum'] ?? null;
            $max = $constraints['maximum'] ?? null;

            if ((is_int($min) || $min === null) && (is_int($max) || $max === null)) {
                return 'integer';
            }

            return 'number';
        }

        // If we have minLength/maxLength/pattern, it's a string
        if (isset($constraints['minLength']) || isset($constraints['maxLength']) || isset($constraints['pattern'])) {
            return 'string';
        }

        // Default to string
        return 'string';
    }
}
