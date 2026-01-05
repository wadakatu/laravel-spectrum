<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LaravelSpectrum\Contracts\SpectrumDescribableRule;
use LaravelSpectrum\DTO\OpenApiSchema;

/**
 * Test fixture: Rule implementing SpectrumDescribableRule interface.
 */
class DescribableRule implements SpectrumDescribableRule, ValidationRule
{
    public function spectrumSchema(): OpenApiSchema
    {
        return new OpenApiSchema(
            type: 'string',
            pattern: '^\d{3}-\d{4}$',
            minLength: 8,
            maxLength: 8,
        );
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! preg_match('/^\d{3}-\d{4}$/', $value)) {
            $fail("The {$attribute} must be a valid postal code.");
        }
    }
}
