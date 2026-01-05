<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Test fixture: Rule with pattern constraint.
 */
class PatternRule implements ValidationRule
{
    public function __construct(
        private string $pattern = '/^[A-Z]{3}-\d{4}$/',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! preg_match($this->pattern, $value)) {
            $fail("The {$attribute} format is invalid.");
        }
    }
}
