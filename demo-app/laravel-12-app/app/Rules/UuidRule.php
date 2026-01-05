<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LaravelSpectrum\Attributes\OpenApiSchemaAttribute;

/**
 * Custom validation rule for UUIDs.
 * Tests the OpenApiSchemaAttribute for schema definition via PHP attributes.
 */
#[OpenApiSchemaAttribute(type: 'string', format: 'uuid')]
class UuidRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            $fail('The :attribute must be a valid UUID.');
        }
    }
}
