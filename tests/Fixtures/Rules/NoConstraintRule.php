<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Test fixture: Rule with no detectable constraints (edge case).
 */
class NoConstraintRule implements ValidationRule
{
    public function __construct(
        private bool $required = true,
        private array $options = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Custom validation logic
        if ($this->required && empty($value)) {
            $fail("The {$attribute} is required.");
        }
    }
}
