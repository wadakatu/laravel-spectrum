<?php

declare(strict_types=1);

namespace LaravelSpectrum\Attributes;

use Attribute;

/**
 * PHP 8 Attribute for specifying OpenAPI schema on custom validation rules.
 *
 * Use this attribute on custom validation rule classes to define how they
 * should be represented in the generated OpenAPI specification.
 *
 * Example:
 * ```php
 * use Illuminate\Contracts\Validation\ValidationRule;
 * use LaravelSpectrum\Attributes\OpenApiSchemaAttribute;
 *
 * #[OpenApiSchemaAttribute(type: 'string', format: 'uuid')]
 * class UuidRule implements ValidationRule
 * {
 *     public function validate(string $attribute, mixed $value, Closure $fail): void
 *     {
 *         // validation logic
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class OpenApiSchemaAttribute
{
    /**
     * @param  string  $type  The data type (string, integer, number, boolean, array, object)
     * @param  string|null  $format  The format (int32, int64, float, double, date, date-time, email, uuid, etc.)
     * @param  string|null  $pattern  Regex pattern for string types
     * @param  int|null  $minimum  Minimum value for numeric types
     * @param  int|null  $maximum  Maximum value for numeric types
     * @param  int|null  $minLength  Minimum length for string types
     * @param  int|null  $maxLength  Maximum length for string types
     * @param  string|null  $description  Description of the constraint
     */
    public function __construct(
        public string $type = 'string',
        public ?string $format = null,
        public ?string $pattern = null,
        public ?int $minimum = null,
        public ?int $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?string $description = null,
    ) {}
}
