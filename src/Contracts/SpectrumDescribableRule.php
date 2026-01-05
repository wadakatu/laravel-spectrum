<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts;

use LaravelSpectrum\DTO\OpenApiSchema;

/**
 * Interface for custom validation rules that provide explicit OpenAPI schema information.
 *
 * Custom validation rules implementing Laravel's ValidationRule interface can optionally
 * implement this interface to provide explicit control over how the rule is documented
 * in the generated OpenAPI specification.
 *
 * Example:
 * ```php
 * use Illuminate\Contracts\Validation\ValidationRule;
 * use LaravelSpectrum\Contracts\SpectrumDescribableRule;
 * use LaravelSpectrum\DTO\OpenApiSchema;
 *
 * class JapanesePostalCode implements ValidationRule, SpectrumDescribableRule
 * {
 *     public function spectrumSchema(): OpenApiSchema
 *     {
 *         return new OpenApiSchema(
 *             type: 'string',
 *             pattern: '^\d{3}-?\d{4}$',
 *         );
 *     }
 *
 *     public function validate(string $attribute, mixed $value, Closure $fail): void
 *     {
 *         // validation logic
 *     }
 * }
 * ```
 */
interface SpectrumDescribableRule
{
    /**
     * Get the OpenAPI schema for this validation rule.
     *
     * This method should return an OpenApiSchema object that describes
     * the constraints this rule enforces on the validated value.
     */
    public function spectrumSchema(): OpenApiSchema;
}
