<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents information about a PHP enum class.
 */
final readonly class EnumInfo
{
    /**
     * @param  string  $class  The fully qualified enum class name
     * @param  array<int, string|int>  $values  The enum case values
     * @param  EnumBackingType  $backingType  The backing type of the enum
     */
    public function __construct(
        public string $class,
        public array $values,
        public EnumBackingType $backingType,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $typeString = $data['type'] ?? 'string';
        // Handle both OpenAPI format ('integer') and raw enum value ('int')
        if ($typeString === 'integer') {
            $typeString = 'int';
        }
        $backingType = EnumBackingType::tryFrom($typeString) ?? EnumBackingType::STRING;

        return new self(
            class: $data['class'] ?? '',
            values: $data['values'] ?? [],
            backingType: $backingType,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'values' => $this->values,
            'type' => $this->backingType->toOpenApiType(),
        ];
    }

    /**
     * Check if this is a string-backed enum.
     */
    public function isStringBacked(): bool
    {
        return $this->backingType->isString();
    }

    /**
     * Check if this is an integer-backed enum.
     */
    public function isIntegerBacked(): bool
    {
        return $this->backingType->isInteger();
    }

    /**
     * Get the short class name without namespace.
     */
    public function getShortClassName(): string
    {
        $parts = explode('\\', $this->class);

        return end($parts) ?: $this->class;
    }

    /**
     * Check if this enum has any values.
     */
    public function hasValues(): bool
    {
        return count($this->values) > 0;
    }

    /**
     * Count the number of values.
     */
    public function count(): int
    {
        return count($this->values);
    }

    /**
     * Get the OpenAPI type for this enum.
     */
    public function getOpenApiType(): string
    {
        return $this->backingType->toOpenApiType();
    }
}
