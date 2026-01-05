<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Test fixture: Simple rule with minLength property.
 */
class MinLengthRule implements ValidationRule
{
    public function __construct(
        private int $minLength = 8,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) < $this->minLength) {
            $fail("The {$attribute} must be at least {$this->minLength} characters.");
        }
    }
}
