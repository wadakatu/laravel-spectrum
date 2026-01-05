<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Test fixture: Rule with min/max numeric constraints.
 */
class NumericRangeRule implements ValidationRule
{
    public function __construct(
        private int $min = 0,
        private int $max = 100,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_numeric($value) && ($value < $this->min || $value > $this->max)) {
            $fail("The {$attribute} must be between {$this->min} and {$this->max}.");
        }
    }
}
