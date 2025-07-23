<?php

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\Support\FieldNameInference;

class ExampleValueFactory
{
    public function __construct(
        private FieldNameInference $fieldInference
    ) {}

    public function create(string $fieldName, array $schema): mixed
    {
        // First check common field values
        $commonValues = $this->getCommonFieldValues();
        if (isset($commonValues[$fieldName])) {
            return $commonValues[$fieldName];
        }

        // Check for special fields that need masking
        if ($this->isPasswordField($fieldName)) {
            return '********';
        }

        if ($this->isTokenField($fieldName)) {
            return 'sk_test_********************';
        }

        // Use schema format if available
        $format = $schema['format'] ?? null;
        $type = $schema['type'] ?? 'string';

        // If format is explicitly provided, prioritize it
        if ($format !== null) {
            return $this->generateByType($type, $format);
        }

        // Check field name patterns
        $inferredType = $this->fieldInference->inferFieldType($fieldName);
        if ($inferredType['type'] !== 'string' || $inferredType['format'] !== 'text') {
            return $this->generateByInferredType($inferredType);
        }

        // Generate based on constraints
        if (isset($schema['minimum']) || isset($schema['maximum'])) {
            return $this->generateWithConstraints($type, $schema);
        }

        // Default to type-based generation
        return $this->generateByType($type, $format);
    }

    public function generateByType(string $type, ?string $format = null): mixed
    {
        return match ($type) {
            'string' => $this->generateString($format),
            'integer' => $this->generateInteger(),
            'number' => $this->generateNumber(),
            'boolean' => true,
            'array' => [],
            'object' => new \stdClass,
            default => null,
        };
    }

    private function generateString(?string $format): string
    {
        return match ($format) {
            'date-time' => '2024-01-15T10:30:00Z',
            'date' => '2024-01-15',
            'time' => '10:30:00',
            'email' => 'user@example.com',
            'url', 'uri' => 'https://example.com',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'password' => '********',
            'ipv4' => '192.168.1.1',
            'ipv6' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'hostname' => 'example.com',
            default => 'string',
        };
    }

    private function generateInteger(): int
    {
        return 1;
    }

    private function generateNumber(): float
    {
        return 1.0;
    }

    private function getCommonFieldValues(): array
    {
        return [
            'id' => 1,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'user@example.com',
            'username' => 'johndoe',
            'password' => '********',
            'phone' => '+1-555-123-4567',
            'mobile' => '+1-555-987-6543',
            'address' => '123 Main St, Anytown, USA',
            'street' => '123 Main St',
            'city' => 'Anytown',
            'state' => 'CA',
            'country' => 'USA',
            'postal_code' => '12345',
            'zip_code' => '12345',
            'created_at' => '2024-01-15T10:30:00Z',
            'updated_at' => '2024-01-15T10:30:00Z',
            'deleted_at' => null,
            'is_active' => true,
            'is_verified' => true,
            'is_admin' => false,
            'has_children' => false,
            'status' => 'active',
            'role' => 'user',
            'title' => 'Example Title',
            'description' => 'This is an example description.',
            'content' => '<p>This is example content.</p>',
            'body' => 'This is the body content.',
            'message' => 'This is an example message.',
            'price' => 99.99,
            'amount' => 100.00,
            'total' => 100.00,
            'subtotal' => 90.00,
            'tax' => 10.00,
            'discount' => 0.00,
            'quantity' => 1,
            'count' => 42,
            'age' => 25,
            'rating' => 4.5,
            'score' => 85,
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'lat' => 37.7749,
            'lng' => -122.4194,
            'url' => 'https://example.com',
            'website' => 'https://example.com',
            'website_url' => 'https://example.com',
            'link' => 'https://example.com',
            'image' => 'https://example.com/image.jpg',
            'avatar' => 'https://example.com/avatar.jpg',
            'thumbnail' => 'https://example.com/thumb.jpg',
            'photo' => 'https://example.com/photo.jpg',
            'picture' => 'https://example.com/picture.jpg',
            'icon' => 'https://example.com/icon.png',
            'logo' => 'https://example.com/logo.png',
            'banner' => 'https://example.com/banner.jpg',
            'cover' => 'https://example.com/cover.jpg',
            'api_key' => 'sk_test_********************',
            'api_token' => 'sk_test_********************',
            'access_token' => 'sk_test_********************',
            'token' => 'sk_test_********************',
            'secret' => '********************',
            'color' => '#FF5733',
            'hex_color' => '#FF5733',
            'gender' => 'male',
            'timezone' => 'America/New_York',
            'locale' => 'en_US',
            'currency' => 'USD',
            'language' => 'en',
        ];
    }

    private function isPasswordField(string $fieldName): bool
    {
        $passwordFields = ['password', 'pass', 'passwd', 'user_password', 'admin_password'];

        return in_array($fieldName, $passwordFields) || str_ends_with($fieldName, '_password');
    }

    private function isTokenField(string $fieldName): bool
    {
        $tokenFields = ['token', 'api_token', 'access_token', 'auth_token', 'bearer_token', 'secret_token'];

        return in_array($fieldName, $tokenFields) || str_ends_with($fieldName, '_token') || str_ends_with($fieldName, '_key');
    }

    private function generateByInferredType(array $inferredType): mixed
    {
        switch ($inferredType['type']) {
            case 'id':
                return 1;
            case 'timestamp':
                return '2024-01-15T10:30:00Z';
            case 'boolean':
                return true;
            case 'email':
                return 'user@example.com';
            case 'url':
                return 'https://example.com';
            case 'phone':
                return '+1-555-123-4567';
            case 'money':
                return $inferredType['format'] === 'integer' ? 100 : 99.99;
            case 'age':
                return 25;
            case 'quantity':
                return 1;
            case 'status':
                return 'active';
            case 'text':
                return 'This is example text.';
            case 'name':
                return match ($inferredType['format']) {
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    default => 'John Doe',
                };
            case 'username':
                return 'johndoe';
            case 'password':
                return '********';
            default:
                return $this->generateByType('string');
        }
    }

    private function generateWithConstraints(string $type, array $schema): mixed
    {
        if ($type === 'integer') {
            $min = $schema['minimum'] ?? 1;
            $max = $schema['maximum'] ?? 100;

            // Return a value in the middle of the range
            return (int) (($min + $max) / 2);
        }

        if ($type === 'number') {
            $min = $schema['minimum'] ?? 0.0;
            $max = $schema['maximum'] ?? 100.0;

            // Return a value in the middle of the range
            return ($min + $max) / 2;
        }

        if ($type === 'string' && isset($schema['maxLength'])) {
            $maxLength = $schema['maxLength'];
            if ($maxLength <= 10) {
                return 'short';
            }

            return 'string';
        }

        return $this->generateByType($type, $schema['format'] ?? null);
    }
}
