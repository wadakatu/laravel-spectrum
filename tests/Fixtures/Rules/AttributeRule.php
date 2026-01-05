<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LaravelSpectrum\Attributes\OpenApiSchemaAttribute;

/**
 * Test fixture: Rule with OpenApiSchemaAttribute.
 */
#[OpenApiSchemaAttribute(type: 'string', format: 'uuid')]
class AttributeRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            $fail("The {$attribute} must be a valid UUID.");
        }
    }
}
