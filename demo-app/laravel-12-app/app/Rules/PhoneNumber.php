<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for phone numbers.
 * Tests if pattern constraint is detected by Spectrum.
 */
class PhoneNumber implements ValidationRule
{
    public function __construct(
        private string $pattern = '/^\+?[0-9]{10,15}$/',
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (! preg_match($this->pattern, $value)) {
            $fail('The :attribute must be a valid phone number.');
        }
    }
}
