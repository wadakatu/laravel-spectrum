<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the type of authentication scheme.
 */
enum AuthenticationType: string
{
    case HTTP = 'http';
    case API_KEY = 'apiKey';
    case OAUTH2 = 'oauth2';

    /**
     * Check if this is an HTTP authentication type.
     */
    public function isHttp(): bool
    {
        return $this === self::HTTP;
    }

    /**
     * Check if this is an API Key authentication type.
     */
    public function isApiKey(): bool
    {
        return $this === self::API_KEY;
    }

    /**
     * Check if this is an OAuth2 authentication type.
     */
    public function isOAuth2(): bool
    {
        return $this === self::OAUTH2;
    }
}
