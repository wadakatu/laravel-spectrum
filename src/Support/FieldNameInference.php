<?php

namespace LaravelSpectrum\Support;

class FieldNameInference
{
    public function inferFieldType(string $fieldName): array
    {
        // First check for exact matches in common patterns
        $patterns = $this->getFieldPatterns();
        if (isset($patterns[$fieldName])) {
            return $patterns[$fieldName];
        }

        // Check for compound field names (e.g., user_email)
        if (str_contains($fieldName, '_')) {
            $parts = explode('_', $fieldName);
            $lastPart = end($parts);

            // Check if the last part matches a pattern
            if (isset($patterns[$lastPart])) {
                return $patterns[$lastPart];
            }
        }

        // Check suffixes
        if (str_ends_with($fieldName, '_id')) {
            return ['type' => 'id', 'format' => 'integer'];
        }

        if (str_ends_with($fieldName, '_at')) {
            return ['type' => 'timestamp', 'format' => 'datetime'];
        }

        if (str_ends_with($fieldName, '_url') || str_ends_with($fieldName, '_link')) {
            return ['type' => 'url', 'format' => 'url'];
        }

        if (str_ends_with($fieldName, '_date')) {
            return ['type' => 'date', 'format' => 'date'];
        }

        if (str_ends_with($fieldName, '_time')) {
            return ['type' => 'time', 'format' => 'time'];
        }

        if (str_ends_with($fieldName, '_count') || str_ends_with($fieldName, '_total')) {
            return ['type' => 'quantity', 'format' => 'integer'];
        }

        // Check prefixes
        if (str_starts_with($fieldName, 'is_') || str_starts_with($fieldName, 'has_')) {
            return ['type' => 'boolean', 'format' => 'boolean'];
        }

        if (str_starts_with($fieldName, 'num_') || str_starts_with($fieldName, 'number_')) {
            return ['type' => 'quantity', 'format' => 'integer'];
        }

        // Check for plural forms that might indicate URLs
        if ($fieldName === 'images' || str_ends_with($fieldName, '_images')) {
            return ['type' => 'url', 'format' => 'image_url'];
        }

        // Default to string
        return ['type' => 'string', 'format' => 'text'];
    }

    private function getFieldPatterns(): array
    {
        return [
            // Identity fields
            'id' => ['type' => 'id', 'format' => 'integer'],
            'uuid' => ['type' => 'uuid', 'format' => 'uuid'],

            // User fields
            'email' => ['type' => 'email', 'format' => 'email'],
            'password' => ['type' => 'password', 'format' => 'password'],
            'username' => ['type' => 'username', 'format' => 'alphanumeric'],
            'first_name' => ['type' => 'name', 'format' => 'first_name'],
            'last_name' => ['type' => 'name', 'format' => 'last_name'],
            'name' => ['type' => 'name', 'format' => 'full_name'],

            // Contact fields
            'phone' => ['type' => 'phone', 'format' => 'phone'],
            'mobile' => ['type' => 'phone', 'format' => 'mobile'],
            'fax' => ['type' => 'phone', 'format' => 'phone'],

            // Address fields
            'address' => ['type' => 'address', 'format' => 'text'],
            'street' => ['type' => 'address', 'format' => 'text'],
            'city' => ['type' => 'address', 'format' => 'text'],
            'state' => ['type' => 'address', 'format' => 'text'],
            'country' => ['type' => 'address', 'format' => 'text'],
            'postal_code' => ['type' => 'address', 'format' => 'text'],
            'zip_code' => ['type' => 'address', 'format' => 'text'],

            // Numeric fields
            'age' => ['type' => 'age', 'format' => 'integer'],
            'price' => ['type' => 'money', 'format' => 'decimal'],
            'amount' => ['type' => 'money', 'format' => 'decimal'],
            'total' => ['type' => 'money', 'format' => 'decimal'],
            'subtotal' => ['type' => 'money', 'format' => 'decimal'],
            'tax' => ['type' => 'money', 'format' => 'decimal'],
            'discount' => ['type' => 'money', 'format' => 'decimal'],
            'quantity' => ['type' => 'quantity', 'format' => 'integer'],
            'count' => ['type' => 'quantity', 'format' => 'integer'],
            'rating' => ['type' => 'rating', 'format' => 'decimal'],
            'score' => ['type' => 'score', 'format' => 'integer'],

            // Status fields
            'status' => ['type' => 'status', 'format' => 'string'],
            'role' => ['type' => 'role', 'format' => 'string'],
            'type' => ['type' => 'type', 'format' => 'string'],

            // Text fields
            'title' => ['type' => 'text', 'format' => 'text'],
            'description' => ['type' => 'text', 'format' => 'text'],
            'content' => ['type' => 'text', 'format' => 'html'],
            'body' => ['type' => 'text', 'format' => 'text'],
            'message' => ['type' => 'text', 'format' => 'text'],
            'summary' => ['type' => 'text', 'format' => 'text'],
            'notes' => ['type' => 'text', 'format' => 'text'],

            // URL fields
            'url' => ['type' => 'url', 'format' => 'url'],
            'website' => ['type' => 'url', 'format' => 'url'],
            'link' => ['type' => 'url', 'format' => 'url'],
            'image' => ['type' => 'url', 'format' => 'image_url'],
            'avatar' => ['type' => 'url', 'format' => 'avatar_url'],
            'thumbnail' => ['type' => 'url', 'format' => 'image_url'],
            'photo' => ['type' => 'url', 'format' => 'image_url'],
            'picture' => ['type' => 'url', 'format' => 'image_url'],
            'icon' => ['type' => 'url', 'format' => 'image_url'],
            'logo' => ['type' => 'url', 'format' => 'image_url'],
            'banner' => ['type' => 'url', 'format' => 'image_url'],
            'cover' => ['type' => 'url', 'format' => 'image_url'],

            // Location fields
            'latitude' => ['type' => 'location', 'format' => 'decimal'],
            'longitude' => ['type' => 'location', 'format' => 'decimal'],
            'lat' => ['type' => 'location', 'format' => 'decimal'],
            'lng' => ['type' => 'location', 'format' => 'decimal'],
            'lon' => ['type' => 'location', 'format' => 'decimal'],

            // Token fields
            'token' => ['type' => 'token', 'format' => 'string'],
            'api_key' => ['type' => 'token', 'format' => 'string'],
            'api_token' => ['type' => 'token', 'format' => 'string'],
            'access_token' => ['type' => 'token', 'format' => 'string'],
            'secret' => ['type' => 'token', 'format' => 'string'],

            // Other common fields
            'color' => ['type' => 'color', 'format' => 'hex'],
            'hex_color' => ['type' => 'color', 'format' => 'hex'],
            'gender' => ['type' => 'gender', 'format' => 'string'],
            'timezone' => ['type' => 'timezone', 'format' => 'string'],
            'locale' => ['type' => 'locale', 'format' => 'string'],
            'currency' => ['type' => 'currency', 'format' => 'string'],
            'language' => ['type' => 'language', 'format' => 'string'],

            // Timestamp fields
            'created_at' => ['type' => 'timestamp', 'format' => 'datetime'],
            'updated_at' => ['type' => 'timestamp', 'format' => 'datetime'],
            'deleted_at' => ['type' => 'timestamp', 'format' => 'datetime'],
            'published_at' => ['type' => 'timestamp', 'format' => 'datetime'],
            'expires_at' => ['type' => 'timestamp', 'format' => 'datetime'],
            'started_at' => ['type' => 'timestamp', 'format' => 'datetime'],
            'ended_at' => ['type' => 'timestamp', 'format' => 'datetime'],
            'completed_at' => ['type' => 'timestamp', 'format' => 'datetime'],
        ];
    }
}
