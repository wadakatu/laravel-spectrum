<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LaravelSpectrum\Contracts\SpectrumDescribableRule;
use LaravelSpectrum\DTO\OpenApiSchema;

/**
 * Custom validation rule for Japanese postal codes.
 * Tests the SpectrumDescribableRule interface for explicit schema definition.
 */
class JapanesePostalCode implements SpectrumDescribableRule, ValidationRule
{
    /**
     * Define the OpenAPI schema for this validation rule.
     */
    public function spectrumSchema(): OpenApiSchema
    {
        return new OpenApiSchema(
            type: 'string',
            pattern: '^\d{3}-?\d{4}$',
            minLength: 7,
            maxLength: 8,
        );
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (! preg_match('/^\d{3}-?\d{4}$/', $value)) {
            $fail('The :attribute must be a valid Japanese postal code (e.g., 123-4567 or 1234567).');
        }
    }
}
