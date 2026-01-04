<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an OpenAPI Info object.
 *
 * @see https://spec.openapis.org/oas/v3.1.0#info-object
 */
final readonly class OpenApiInfo
{
    /**
     * @param  string  $title  The title of the API
     * @param  string  $version  The version of the API document
     * @param  string  $description  A description of the API
     * @param  string|null  $termsOfService  URL to the Terms of Service
     * @param  array{name?: string, url?: string, email?: string}|null  $contact  Contact information
     * @param  array{name?: string, url?: string, identifier?: string}|null  $license  License information
     */
    public function __construct(
        public string $title,
        public string $version,
        public string $description = '',
        public ?string $termsOfService = null,
        public ?array $contact = null,
        public ?array $license = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            version: $data['version'] ?? '',
            description: $data['description'] ?? '',
            termsOfService: $data['termsOfService'] ?? null,
            contact: $data['contact'] ?? null,
            license: $data['license'] ?? null,
        );
    }

    /**
     * Convert to OpenAPI info array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'title' => $this->title,
            'version' => $this->version,
        ];

        if ($this->description !== '') {
            $result['description'] = $this->description;
        }

        if ($this->termsOfService !== null) {
            $result['termsOfService'] = $this->termsOfService;
        }

        if ($this->contact !== null) {
            $result['contact'] = $this->contact;
        }

        if ($this->license !== null) {
            $result['license'] = $this->license;
        }

        return $result;
    }

    /**
     * Check if this info has contact information.
     */
    public function hasContact(): bool
    {
        return $this->contact !== null;
    }

    /**
     * Check if this info has license information.
     */
    public function hasLicense(): bool
    {
        return $this->license !== null;
    }

    /**
     * Check if this info has terms of service.
     */
    public function hasTermsOfService(): bool
    {
        return $this->termsOfService !== null;
    }
}
