<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the type of response from a controller method.
 */
enum ResponseType: string
{
    case VOID = 'void';
    case RESOURCE = 'resource';
    case OBJECT = 'object';
    case COLLECTION = 'collection';
    case UNKNOWN = 'unknown';

    /**
     * Check if this response type is void.
     */
    public function isVoid(): bool
    {
        return $this === self::VOID;
    }

    /**
     * Check if this response type is a collection.
     */
    public function isCollection(): bool
    {
        return $this === self::COLLECTION;
    }

    /**
     * Check if this response type is a resource.
     */
    public function isResource(): bool
    {
        return $this === self::RESOURCE;
    }

    /**
     * Check if this response type has a structure (properties).
     */
    public function hasStructure(): bool
    {
        return in_array($this, [self::OBJECT, self::COLLECTION, self::RESOURCE], true);
    }
}
