<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support\Example;

use LaravelSpectrum\DTO\FieldPatternConfig;

/**
 * Single source of truth for field name to generation configuration mappings.
 *
 * Consolidates patterns from FieldNameInference, ExampleValueFactory, and DynamicExampleGenerator.
 */
final class FieldPatternRegistry
{
    /**
     * Custom patterns registered at runtime.
     *
     * @var array<string, FieldPatternConfig>
     */
    private array $customPatterns = [];

    /**
     * Get generation configuration for a field name.
     *
     * Looks up the field name in the following order:
     * 1. Custom patterns registered at runtime
     * 2. Exact match (normalized: lowercase, no dashes/underscores)
     * 3. Compound field name suffix (e.g., user_email -> email)
     * 4. Suffix/prefix pattern matching
     *
     * @param  string  $fieldName  The field name to look up
     */
    public function getConfig(string $fieldName): ?FieldPatternConfig
    {
        // Check custom patterns first
        if (isset($this->customPatterns[$fieldName])) {
            return $this->customPatterns[$fieldName];
        }

        // Check exact matches
        $patterns = $this->getFieldPatterns();
        $normalized = strtolower(str_replace(['-', '_'], '', $fieldName));

        if (isset($patterns[$normalized])) {
            return $patterns[$normalized];
        }

        // Check original field name for exact match
        if (isset($patterns[$fieldName])) {
            return $patterns[$fieldName];
        }

        // Check compound field names (e.g., user_email -> email)
        if (str_contains($fieldName, '_')) {
            $parts = explode('_', $fieldName);
            $lastPart = strtolower(end($parts));
            if (isset($patterns[$lastPart])) {
                return $patterns[$lastPart];
            }
        }

        // Check suffix/prefix patterns
        return $this->matchSuffixPrefixPatterns($fieldName);
    }

    /**
     * Alias for getConfig().
     *
     * Matches a field name against all registered patterns (custom, exact, compound, suffix/prefix).
     *
     * @param  string  $fieldName  The field name to match
     */
    public function matchPattern(string $fieldName): ?FieldPatternConfig
    {
        return $this->getConfig($fieldName);
    }

    /**
     * Register a custom pattern.
     *
     * @param  string  $pattern  The pattern name (field name to match)
     * @param  array{type: string, format?: ?string, fakerMethod?: ?string, fakerArgs?: array<int, mixed>, staticValue: mixed}|FieldPatternConfig  $config
     *
     * @throws \InvalidArgumentException If pattern is empty or config is missing required fields
     */
    public function registerPattern(string $pattern, array|FieldPatternConfig $config): void
    {
        if (empty($pattern)) {
            throw new \InvalidArgumentException('Pattern name cannot be empty.');
        }

        if ($config instanceof FieldPatternConfig) {
            $this->customPatterns[$pattern] = $config;

            return;
        }

        if (! isset($config['type']) || ! is_string($config['type']) || $config['type'] === '') {
            throw new \InvalidArgumentException(
                "Pattern '{$pattern}' must have a non-empty 'type' field."
            );
        }

        if (! array_key_exists('staticValue', $config)) {
            throw new \InvalidArgumentException(
                "Pattern '{$pattern}' must have a 'staticValue' field (can be null)."
            );
        }

        $this->customPatterns[$pattern] = new FieldPatternConfig(
            type: $config['type'],
            format: $config['format'] ?? null,
            fakerMethod: $config['fakerMethod'] ?? null,
            fakerArgs: $config['fakerArgs'] ?? [],
            staticValue: $config['staticValue'],
        );
    }

    /**
     * Get all registered patterns (for testing/debugging).
     *
     * @return array<string, FieldPatternConfig>
     */
    public function getAllPatterns(): array
    {
        return array_merge($this->getFieldPatterns(), $this->customPatterns);
    }

    /**
     * Check suffix and prefix patterns.
     */
    private function matchSuffixPrefixPatterns(string $fieldName): ?FieldPatternConfig
    {
        $lower = strtolower($fieldName);

        // Suffix patterns
        if (str_ends_with($lower, '_id') || str_ends_with($lower, 'id')) {
            return new FieldPatternConfig(
                type: 'id',
                format: 'integer',
                fakerMethod: 'numberBetween',
                fakerArgs: [1, 1000],
                staticValue: 1,
            );
        }

        if (str_ends_with($lower, '_at')) {
            return new FieldPatternConfig(
                type: 'timestamp',
                format: 'datetime',
                fakerMethod: 'dateTime',
                fakerArgs: [],
                staticValue: '2024-01-15T10:30:00Z',
            );
        }

        if (str_ends_with($lower, '_url') || str_ends_with($lower, '_link')) {
            return new FieldPatternConfig(
                type: 'url',
                format: 'url',
                fakerMethod: 'url',
                fakerArgs: [],
                staticValue: 'https://example.com',
            );
        }

        if (str_ends_with($lower, '_date')) {
            return new FieldPatternConfig(
                type: 'date',
                format: 'date',
                fakerMethod: 'date',
                fakerArgs: ['Y-m-d'],
                staticValue: '2024-01-15',
            );
        }

        if (str_ends_with($lower, '_time')) {
            return new FieldPatternConfig(
                type: 'time',
                format: 'time',
                fakerMethod: 'time',
                fakerArgs: ['H:i:s'],
                staticValue: '10:30:00',
            );
        }

        if (str_ends_with($lower, '_count') || str_ends_with($lower, '_total')) {
            return new FieldPatternConfig(
                type: 'quantity',
                format: 'integer',
                fakerMethod: 'numberBetween',
                fakerArgs: [0, 100],
                staticValue: 42,
            );
        }

        // Prefix patterns
        if (str_starts_with($lower, 'is_') || str_starts_with($lower, 'has_') || str_starts_with($lower, 'can_') || str_starts_with($lower, 'should_')) {
            return new FieldPatternConfig(
                type: 'boolean',
                format: 'boolean',
                fakerMethod: 'boolean',
                fakerArgs: [],
                staticValue: true,
            );
        }

        if (str_starts_with($lower, 'num_') || str_starts_with($lower, 'number_')) {
            return new FieldPatternConfig(
                type: 'quantity',
                format: 'integer',
                fakerMethod: 'numberBetween',
                fakerArgs: [1, 100],
                staticValue: 1,
            );
        }

        // Content patterns
        if (str_contains($lower, 'image') || str_contains($lower, 'photo') || str_contains($lower, 'picture') || str_contains($lower, 'avatar') || str_contains($lower, 'thumbnail')) {
            return new FieldPatternConfig(
                type: 'url',
                format: 'image_url',
                fakerMethod: 'imageUrl',
                fakerArgs: [640, 480],
                staticValue: 'https://example.com/image.jpg',
            );
        }

        if (str_contains($lower, 'file') || str_contains($lower, 'document') || str_contains($lower, 'attachment')) {
            return new FieldPatternConfig(
                type: 'file',
                format: 'path',
                fakerMethod: 'filePath',
                fakerArgs: [],
                staticValue: '/path/to/file.pdf',
            );
        }

        return null;
    }

    /**
     * Get all field patterns consolidated from multiple sources.
     *
     * @return array<string, FieldPatternConfig>
     */
    private function getFieldPatterns(): array
    {
        return array_map(
            fn (array $data) => FieldPatternConfig::fromArray($data),
            [
                // Identity fields
                'id' => [
                    'type' => 'id',
                    'format' => 'integer',
                    'fakerMethod' => 'unique->numberBetween',
                    'fakerArgs' => [1, 10000],
                    'staticValue' => 1,
                ],
                'uuid' => [
                    'type' => 'uuid',
                    'format' => 'uuid',
                    'fakerMethod' => 'uuid',
                    'fakerArgs' => [],
                    'staticValue' => '550e8400-e29b-41d4-a716-446655440000',
                ],

                // User name fields
                'name' => [
                    'type' => 'name',
                    'format' => 'full_name',
                    'fakerMethod' => 'name',
                    'fakerArgs' => [],
                    'staticValue' => 'John Doe',
                ],
                'firstname' => [
                    'type' => 'name',
                    'format' => 'first_name',
                    'fakerMethod' => 'firstName',
                    'fakerArgs' => [],
                    'staticValue' => 'John',
                ],
                'lastname' => [
                    'type' => 'name',
                    'format' => 'last_name',
                    'fakerMethod' => 'lastName',
                    'fakerArgs' => [],
                    'staticValue' => 'Doe',
                ],
                'fullname' => [
                    'type' => 'name',
                    'format' => 'full_name',
                    'fakerMethod' => 'name',
                    'fakerArgs' => [],
                    'staticValue' => 'John Doe',
                ],

                // Contact fields
                'email' => [
                    'type' => 'email',
                    'format' => 'email',
                    'fakerMethod' => 'safeEmail',
                    'fakerArgs' => [],
                    'staticValue' => 'user@example.com',
                ],
                'emailaddress' => [
                    'type' => 'email',
                    'format' => 'email',
                    'fakerMethod' => 'safeEmail',
                    'fakerArgs' => [],
                    'staticValue' => 'user@example.com',
                ],
                'username' => [
                    'type' => 'username',
                    'format' => 'alphanumeric',
                    'fakerMethod' => 'userName',
                    'fakerArgs' => [],
                    'staticValue' => 'johndoe',
                ],
                'password' => [
                    'type' => 'password',
                    'format' => 'password',
                    'fakerMethod' => null, // Special handling
                    'fakerArgs' => [],
                    'staticValue' => '********',
                ],

                // Phone fields
                'phone' => [
                    'type' => 'phone',
                    'format' => 'phone',
                    'fakerMethod' => 'phoneNumber',
                    'fakerArgs' => [],
                    'staticValue' => '+1-555-123-4567',
                ],
                'phonenumber' => [
                    'type' => 'phone',
                    'format' => 'phone',
                    'fakerMethod' => 'phoneNumber',
                    'fakerArgs' => [],
                    'staticValue' => '+1-555-123-4567',
                ],
                'mobile' => [
                    'type' => 'phone',
                    'format' => 'mobile',
                    'fakerMethod' => 'phoneNumber',
                    'fakerArgs' => [],
                    'staticValue' => '+1-555-987-6543',
                ],
                'fax' => [
                    'type' => 'phone',
                    'format' => 'phone',
                    'fakerMethod' => 'phoneNumber',
                    'fakerArgs' => [],
                    'staticValue' => '+1-555-000-0000',
                ],

                // Address fields
                'address' => [
                    'type' => 'address',
                    'format' => 'text',
                    'fakerMethod' => 'address',
                    'fakerArgs' => [],
                    'staticValue' => '123 Main St, Anytown, USA',
                ],
                'street' => [
                    'type' => 'address',
                    'format' => 'text',
                    'fakerMethod' => 'streetAddress',
                    'fakerArgs' => [],
                    'staticValue' => '123 Main St',
                ],
                'city' => [
                    'type' => 'address',
                    'format' => 'text',
                    'fakerMethod' => 'city',
                    'fakerArgs' => [],
                    'staticValue' => 'Anytown',
                ],
                'state' => [
                    'type' => 'address',
                    'format' => 'text',
                    'fakerMethod' => 'randomElement',
                    'fakerArgs' => [['CA', 'NY', 'TX', 'FL', 'IL']],
                    'staticValue' => 'CA',
                ],
                'country' => [
                    'type' => 'address',
                    'format' => 'text',
                    'fakerMethod' => 'country',
                    'fakerArgs' => [],
                    'staticValue' => 'USA',
                ],
                'countrycode' => [
                    'type' => 'address',
                    'format' => 'text',
                    'fakerMethod' => 'countryCode',
                    'fakerArgs' => [],
                    'staticValue' => 'US',
                ],
                'postalcode' => [
                    'type' => 'address',
                    'format' => 'text',
                    'fakerMethod' => 'postcode',
                    'fakerArgs' => [],
                    'staticValue' => '12345',
                ],
                'zipcode' => [
                    'type' => 'address',
                    'format' => 'text',
                    'fakerMethod' => 'postcode',
                    'fakerArgs' => [],
                    'staticValue' => '12345',
                ],

                // Location fields
                'latitude' => [
                    'type' => 'location',
                    'format' => 'decimal',
                    'fakerMethod' => 'latitude',
                    'fakerArgs' => [],
                    'staticValue' => 37.7749,
                ],
                'longitude' => [
                    'type' => 'location',
                    'format' => 'decimal',
                    'fakerMethod' => 'longitude',
                    'fakerArgs' => [],
                    'staticValue' => -122.4194,
                ],
                'lat' => [
                    'type' => 'location',
                    'format' => 'decimal',
                    'fakerMethod' => 'latitude',
                    'fakerArgs' => [],
                    'staticValue' => 37.7749,
                ],
                'lng' => [
                    'type' => 'location',
                    'format' => 'decimal',
                    'fakerMethod' => 'longitude',
                    'fakerArgs' => [],
                    'staticValue' => -122.4194,
                ],
                'lon' => [
                    'type' => 'location',
                    'format' => 'decimal',
                    'fakerMethod' => 'longitude',
                    'fakerArgs' => [],
                    'staticValue' => -122.4194,
                ],

                // URL fields
                'url' => [
                    'type' => 'url',
                    'format' => 'url',
                    'fakerMethod' => 'url',
                    'fakerArgs' => [],
                    'staticValue' => 'https://example.com',
                ],
                'website' => [
                    'type' => 'url',
                    'format' => 'url',
                    'fakerMethod' => 'url',
                    'fakerArgs' => [],
                    'staticValue' => 'https://example.com',
                ],
                'website_url' => [
                    'type' => 'url',
                    'format' => 'url',
                    'fakerMethod' => 'url',
                    'fakerArgs' => [],
                    'staticValue' => 'https://example.com',
                ],
                'link' => [
                    'type' => 'url',
                    'format' => 'url',
                    'fakerMethod' => 'url',
                    'fakerArgs' => [],
                    'staticValue' => 'https://example.com',
                ],
                'image' => [
                    'type' => 'url',
                    'format' => 'image_url',
                    'fakerMethod' => 'imageUrl',
                    'fakerArgs' => [640, 480],
                    'staticValue' => 'https://example.com/image.jpg',
                ],
                'avatar' => [
                    'type' => 'url',
                    'format' => 'avatar_url',
                    'fakerMethod' => 'imageUrl',
                    'fakerArgs' => [200, 200],
                    'staticValue' => 'https://example.com/avatar.jpg',
                ],
                'thumbnail' => [
                    'type' => 'url',
                    'format' => 'image_url',
                    'fakerMethod' => 'imageUrl',
                    'fakerArgs' => [150, 150],
                    'staticValue' => 'https://example.com/thumb.jpg',
                ],
                'photo' => [
                    'type' => 'url',
                    'format' => 'image_url',
                    'fakerMethod' => 'imageUrl',
                    'fakerArgs' => [640, 480],
                    'staticValue' => 'https://example.com/photo.jpg',
                ],
                'picture' => [
                    'type' => 'url',
                    'format' => 'image_url',
                    'fakerMethod' => 'imageUrl',
                    'fakerArgs' => [640, 480],
                    'staticValue' => 'https://example.com/picture.jpg',
                ],
                'icon' => [
                    'type' => 'url',
                    'format' => 'image_url',
                    'fakerMethod' => 'imageUrl',
                    'fakerArgs' => [64, 64],
                    'staticValue' => 'https://example.com/icon.png',
                ],
                'logo' => [
                    'type' => 'url',
                    'format' => 'image_url',
                    'fakerMethod' => 'imageUrl',
                    'fakerArgs' => [200, 200],
                    'staticValue' => 'https://example.com/logo.png',
                ],
                'banner' => [
                    'type' => 'url',
                    'format' => 'image_url',
                    'fakerMethod' => 'imageUrl',
                    'fakerArgs' => [1200, 400],
                    'staticValue' => 'https://example.com/banner.jpg',
                ],
                'cover' => [
                    'type' => 'url',
                    'format' => 'image_url',
                    'fakerMethod' => 'imageUrl',
                    'fakerArgs' => [1200, 600],
                    'staticValue' => 'https://example.com/cover.jpg',
                ],

                // Company fields
                'company' => [
                    'type' => 'company',
                    'format' => 'text',
                    'fakerMethod' => 'company',
                    'fakerArgs' => [],
                    'staticValue' => 'Acme Inc.',
                ],
                'companyname' => [
                    'type' => 'company',
                    'format' => 'text',
                    'fakerMethod' => 'company',
                    'fakerArgs' => [],
                    'staticValue' => 'Acme Inc.',
                ],
                'jobtitle' => [
                    'type' => 'job',
                    'format' => 'text',
                    'fakerMethod' => 'jobTitle',
                    'fakerArgs' => [],
                    'staticValue' => 'Software Engineer',
                ],
                'department' => [
                    'type' => 'department',
                    'format' => 'text',
                    'fakerMethod' => 'randomElement',
                    'fakerArgs' => [['Sales', 'Marketing', 'Engineering', 'HR', 'Finance']],
                    'staticValue' => 'Engineering',
                ],

                // Text content fields
                'title' => [
                    'type' => 'text',
                    'format' => 'text',
                    'fakerMethod' => 'sentence',
                    'fakerArgs' => [4],
                    'staticValue' => 'Example Title',
                ],
                'description' => [
                    'type' => 'text',
                    'format' => 'text',
                    'fakerMethod' => 'paragraph',
                    'fakerArgs' => [2],
                    'staticValue' => 'This is an example description.',
                ],
                'summary' => [
                    'type' => 'text',
                    'format' => 'text',
                    'fakerMethod' => 'sentence',
                    'fakerArgs' => [10],
                    'staticValue' => 'This is a summary.',
                ],
                'content' => [
                    'type' => 'text',
                    'format' => 'html',
                    'fakerMethod' => 'paragraphs',
                    'fakerArgs' => [3, true],
                    'staticValue' => '<p>This is example content.</p>',
                ],
                'body' => [
                    'type' => 'text',
                    'format' => 'text',
                    'fakerMethod' => 'paragraphs',
                    'fakerArgs' => [3, true],
                    'staticValue' => 'This is the body content.',
                ],
                'message' => [
                    'type' => 'text',
                    'format' => 'text',
                    'fakerMethod' => 'sentence',
                    'fakerArgs' => [8],
                    'staticValue' => 'This is an example message.',
                ],
                'notes' => [
                    'type' => 'text',
                    'format' => 'text',
                    'fakerMethod' => 'paragraph',
                    'fakerArgs' => [1],
                    'staticValue' => 'Some notes here.',
                ],
                'bio' => [
                    'type' => 'text',
                    'format' => 'text',
                    'fakerMethod' => 'paragraph',
                    'fakerArgs' => [3],
                    'staticValue' => 'This is a biography.',
                ],
                'biography' => [
                    'type' => 'text',
                    'format' => 'text',
                    'fakerMethod' => 'paragraph',
                    'fakerArgs' => [3],
                    'staticValue' => 'This is a biography.',
                ],
                'slug' => [
                    'type' => 'slug',
                    'format' => 'text',
                    'fakerMethod' => 'slug',
                    'fakerArgs' => [],
                    'staticValue' => 'example-slug',
                ],

                // Numeric fields
                'age' => [
                    'type' => 'age',
                    'format' => 'integer',
                    'fakerMethod' => 'numberBetween',
                    'fakerArgs' => [18, 80],
                    'staticValue' => 25,
                ],
                'price' => [
                    'type' => 'money',
                    'format' => 'decimal',
                    'fakerMethod' => 'randomFloat',
                    'fakerArgs' => [2, 10, 1000],
                    'staticValue' => 99.99,
                ],
                'amount' => [
                    'type' => 'money',
                    'format' => 'decimal',
                    'fakerMethod' => 'randomFloat',
                    'fakerArgs' => [2, 0, 10000],
                    'staticValue' => 100.00,
                ],
                'total' => [
                    'type' => 'money',
                    'format' => 'decimal',
                    'fakerMethod' => 'randomFloat',
                    'fakerArgs' => [2, 0, 10000],
                    'staticValue' => 100.00,
                ],
                'subtotal' => [
                    'type' => 'money',
                    'format' => 'decimal',
                    'fakerMethod' => 'randomFloat',
                    'fakerArgs' => [2, 0, 10000],
                    'staticValue' => 90.00,
                ],
                'tax' => [
                    'type' => 'money',
                    'format' => 'decimal',
                    'fakerMethod' => 'randomFloat',
                    'fakerArgs' => [2, 0, 1000],
                    'staticValue' => 10.00,
                ],
                'discount' => [
                    'type' => 'money',
                    'format' => 'decimal',
                    'fakerMethod' => 'randomFloat',
                    'fakerArgs' => [2, 0, 100],
                    'staticValue' => 0.00,
                ],
                'quantity' => [
                    'type' => 'quantity',
                    'format' => 'integer',
                    'fakerMethod' => 'numberBetween',
                    'fakerArgs' => [1, 100],
                    'staticValue' => 1,
                ],
                'stock' => [
                    'type' => 'quantity',
                    'format' => 'integer',
                    'fakerMethod' => 'numberBetween',
                    'fakerArgs' => [0, 1000],
                    'staticValue' => 100,
                ],
                'count' => [
                    'type' => 'quantity',
                    'format' => 'integer',
                    'fakerMethod' => 'numberBetween',
                    'fakerArgs' => [0, 100],
                    'staticValue' => 42,
                ],
                'rating' => [
                    'type' => 'rating',
                    'format' => 'decimal',
                    'fakerMethod' => 'randomFloat',
                    'fakerArgs' => [1, 1, 5],
                    'staticValue' => 4.5,
                ],
                'score' => [
                    'type' => 'score',
                    'format' => 'integer',
                    'fakerMethod' => 'numberBetween',
                    'fakerArgs' => [0, 100],
                    'staticValue' => 85,
                ],
                'views' => [
                    'type' => 'quantity',
                    'format' => 'integer',
                    'fakerMethod' => 'numberBetween',
                    'fakerArgs' => [0, 100000],
                    'staticValue' => 1000,
                ],
                'clicks' => [
                    'type' => 'quantity',
                    'format' => 'integer',
                    'fakerMethod' => 'numberBetween',
                    'fakerArgs' => [0, 10000],
                    'staticValue' => 100,
                ],
                'downloads' => [
                    'type' => 'quantity',
                    'format' => 'integer',
                    'fakerMethod' => 'numberBetween',
                    'fakerArgs' => [0, 50000],
                    'staticValue' => 500,
                ],

                // Status fields
                'status' => [
                    'type' => 'status',
                    'format' => 'string',
                    'fakerMethod' => 'randomElement',
                    'fakerArgs' => [['active', 'inactive', 'pending']],
                    'staticValue' => 'active',
                ],
                'role' => [
                    'type' => 'role',
                    'format' => 'string',
                    'fakerMethod' => 'randomElement',
                    'fakerArgs' => [['user', 'admin', 'moderator']],
                    'staticValue' => 'user',
                ],
                'type' => [
                    'type' => 'type',
                    'format' => 'string',
                    'fakerMethod' => 'word',
                    'fakerArgs' => [],
                    'staticValue' => 'default',
                ],

                // Token fields
                'token' => [
                    'type' => 'token',
                    'format' => 'string',
                    'fakerMethod' => 'sha256',
                    'fakerArgs' => [],
                    'staticValue' => 'sk_test_********************',
                ],
                'apikey' => [
                    'type' => 'token',
                    'format' => 'string',
                    'fakerMethod' => 'sha256',
                    'fakerArgs' => [],
                    'staticValue' => 'sk_test_********************',
                ],
                'api_key' => [
                    'type' => 'token',
                    'format' => 'string',
                    'fakerMethod' => 'sha256',
                    'fakerArgs' => [],
                    'staticValue' => 'sk_test_********************',
                ],
                'apitoken' => [
                    'type' => 'token',
                    'format' => 'string',
                    'fakerMethod' => 'sha256',
                    'fakerArgs' => [],
                    'staticValue' => 'sk_test_********************',
                ],
                'api_token' => [
                    'type' => 'token',
                    'format' => 'string',
                    'fakerMethod' => 'sha256',
                    'fakerArgs' => [],
                    'staticValue' => 'sk_test_********************',
                ],
                'accesstoken' => [
                    'type' => 'token',
                    'format' => 'string',
                    'fakerMethod' => 'sha256',
                    'fakerArgs' => [],
                    'staticValue' => 'sk_test_********************',
                ],
                'access_token' => [
                    'type' => 'token',
                    'format' => 'string',
                    'fakerMethod' => 'sha256',
                    'fakerArgs' => [],
                    'staticValue' => 'sk_test_********************',
                ],
                'refreshtoken' => [
                    'type' => 'token',
                    'format' => 'string',
                    'fakerMethod' => 'sha256',
                    'fakerArgs' => [],
                    'staticValue' => 'sk_test_********************',
                ],
                'secret' => [
                    'type' => 'token',
                    'format' => 'string',
                    'fakerMethod' => 'sha256',
                    'fakerArgs' => [],
                    'staticValue' => '********************',
                ],

                // Date/time fields
                'birthdate' => [
                    'type' => 'date',
                    'format' => 'date',
                    'fakerMethod' => 'date',
                    'fakerArgs' => ['Y-m-d', '-18 years'],
                    'staticValue' => '1990-01-15',
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'format' => 'datetime',
                    'fakerMethod' => 'dateTimeBetween',
                    'fakerArgs' => ['-1 year', '-1 week'],
                    'staticValue' => '2024-01-15T10:30:00Z',
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'format' => 'datetime',
                    'fakerMethod' => 'dateTimeBetween',
                    'fakerArgs' => ['-1 week', 'now'],
                    'staticValue' => '2024-01-15T10:30:00Z',
                ],
                'deleted_at' => [
                    'type' => 'timestamp',
                    'format' => 'datetime',
                    'fakerMethod' => null, // Special handling for nullable
                    'fakerArgs' => [],
                    'staticValue' => null,
                ],
                'published_at' => [
                    'type' => 'timestamp',
                    'format' => 'datetime',
                    'fakerMethod' => 'dateTime',
                    'fakerArgs' => [],
                    'staticValue' => '2024-01-15T10:30:00Z',
                ],
                'expires_at' => [
                    'type' => 'timestamp',
                    'format' => 'datetime',
                    'fakerMethod' => 'dateTimeBetween',
                    'fakerArgs' => ['now', '+1 year'],
                    'staticValue' => '2025-01-15T10:30:00Z',
                ],
                'started_at' => [
                    'type' => 'timestamp',
                    'format' => 'datetime',
                    'fakerMethod' => 'dateTime',
                    'fakerArgs' => [],
                    'staticValue' => '2024-01-15T10:30:00Z',
                ],
                'ended_at' => [
                    'type' => 'timestamp',
                    'format' => 'datetime',
                    'fakerMethod' => 'dateTime',
                    'fakerArgs' => [],
                    'staticValue' => '2024-01-15T10:30:00Z',
                ],
                'completed_at' => [
                    'type' => 'timestamp',
                    'format' => 'datetime',
                    'fakerMethod' => 'dateTime',
                    'fakerArgs' => [],
                    'staticValue' => '2024-01-15T10:30:00Z',
                ],

                // Localization fields
                'locale' => [
                    'type' => 'locale',
                    'format' => 'string',
                    'fakerMethod' => 'locale',
                    'fakerArgs' => [],
                    'staticValue' => 'en_US',
                ],
                'language' => [
                    'type' => 'language',
                    'format' => 'string',
                    'fakerMethod' => 'languageCode',
                    'fakerArgs' => [],
                    'staticValue' => 'en',
                ],
                'currency' => [
                    'type' => 'currency',
                    'format' => 'string',
                    'fakerMethod' => 'currencyCode',
                    'fakerArgs' => [],
                    'staticValue' => 'USD',
                ],
                'timezone' => [
                    'type' => 'timezone',
                    'format' => 'string',
                    'fakerMethod' => 'timezone',
                    'fakerArgs' => [],
                    'staticValue' => 'America/New_York',
                ],

                // Technical fields
                'ipaddress' => [
                    'type' => 'ip',
                    'format' => 'ipv4',
                    'fakerMethod' => 'ipv4',
                    'fakerArgs' => [],
                    'staticValue' => '192.168.1.1',
                ],
                'ip' => [
                    'type' => 'ip',
                    'format' => 'ipv4',
                    'fakerMethod' => 'ipv4',
                    'fakerArgs' => [],
                    'staticValue' => '192.168.1.1',
                ],
                'useragent' => [
                    'type' => 'user_agent',
                    'format' => 'string',
                    'fakerMethod' => 'userAgent',
                    'fakerArgs' => [],
                    'staticValue' => 'Mozilla/5.0 (compatible)',
                ],

                // Color fields
                'color' => [
                    'type' => 'color',
                    'format' => 'hex',
                    'fakerMethod' => 'hexColor',
                    'fakerArgs' => [],
                    'staticValue' => '#FF5733',
                ],
                'hexcolor' => [
                    'type' => 'color',
                    'format' => 'hex',
                    'fakerMethod' => 'hexColor',
                    'fakerArgs' => [],
                    'staticValue' => '#FF5733',
                ],
                'hex_color' => [
                    'type' => 'color',
                    'format' => 'hex',
                    'fakerMethod' => 'hexColor',
                    'fakerArgs' => [],
                    'staticValue' => '#FF5733',
                ],

                // Misc fields
                'gender' => [
                    'type' => 'gender',
                    'format' => 'string',
                    'fakerMethod' => 'randomElement',
                    'fakerArgs' => [['male', 'female', 'other']],
                    'staticValue' => 'male',
                ],

                // Boolean fields
                'is_active' => [
                    'type' => 'boolean',
                    'format' => 'boolean',
                    'fakerMethod' => 'boolean',
                    'fakerArgs' => [],
                    'staticValue' => true,
                ],
                'is_verified' => [
                    'type' => 'boolean',
                    'format' => 'boolean',
                    'fakerMethod' => 'boolean',
                    'fakerArgs' => [],
                    'staticValue' => true,
                ],
                'is_admin' => [
                    'type' => 'boolean',
                    'format' => 'boolean',
                    'fakerMethod' => 'boolean',
                    'fakerArgs' => [],
                    'staticValue' => false,
                ],
                'has_children' => [
                    'type' => 'boolean',
                    'format' => 'boolean',
                    'fakerMethod' => 'boolean',
                    'fakerArgs' => [],
                    'staticValue' => false,
                ],
            ]);
    }
}
