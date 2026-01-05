<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for numeric range.
 * Tests if min/max constraints are detected by Spectrum.
 */
class NumericRange implements ValidationRule
{
    public function __construct(
        private int $min = 0,
        private int $max = 100,
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail('The :attribute must be a number.');

            return;
        }

        if ($value < $this->min) {
            $fail("The :attribute must be at least {$this->min}.");
        }

        if ($value > $this->max) {
            $fail("The :attribute must not exceed {$this->max}.");
        }
    }
}
