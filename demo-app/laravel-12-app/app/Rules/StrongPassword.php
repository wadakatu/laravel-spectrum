<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for strong passwords.
 * Tests if custom Rule classes are detected by Spectrum.
 */
class StrongPassword implements ValidationRule
{
    public function __construct(
        private int $minLength = 12,
        private bool $requireUppercase = true,
        private bool $requireNumbers = true,
        private bool $requireSymbols = true,
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen($value) < $this->minLength) {
            $fail("The :attribute must be at least {$this->minLength} characters.");
        }

        if ($this->requireUppercase && ! preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
        }

        if ($this->requireNumbers && ! preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');
        }

        if ($this->requireSymbols && ! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('The :attribute must contain at least one special character.');
        }
    }
}
