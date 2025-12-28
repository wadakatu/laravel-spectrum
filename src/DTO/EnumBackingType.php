<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the backing type of a PHP enum.
 */
enum EnumBackingType: string
{
    case STRING = 'string';
    case INTEGER = 'int';

    /**
     * Check if this is a string-backed enum.
     */
    public function isString(): bool
    {
        return $this === self::STRING;
    }

    /**
     * Check if this is an integer-backed enum.
     */
    public function isInteger(): bool
    {
        return $this === self::INTEGER;
    }

    /**
     * Get the OpenAPI type for this backing type.
     */
    public function toOpenApiType(): string
    {
        return match ($this) {
            self::STRING => 'string',
            self::INTEGER => 'integer',
        };
    }
}
