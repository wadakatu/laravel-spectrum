<?php

namespace LaravelSpectrum\Generators;

use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use LaravelSpectrum\Support\FieldNameInference;

class ExampleValueFactory
{
    private Faker $faker;

    private FieldNameInference $fieldNameInference;

    private bool $useFaker;

    public function __construct(FieldNameInference $fieldNameInference, ?string $locale = null)
    {
        $this->fieldNameInference = $fieldNameInference;
        $this->useFaker = config('spectrum.example_generation.use_faker', true);

        // Initialize Faker with locale
        $locale = $locale ?? config('spectrum.example_generation.faker_locale', config('app.faker_locale', 'en_US'));
        $this->faker = FakerFactory::create($locale);

        // Set seed for consistent examples if configured
        if ($seed = config('spectrum.example_generation.faker_seed')) {
            $this->faker->seed($seed);
        }
    }

    /**
     * Create example value based on field name and schema
     */
    public function create(string $fieldName, array $fieldSchema, ?callable $customGenerator = null): mixed
    {
        // Use custom generator if provided
        if ($customGenerator !== null) {
            return $customGenerator($this->faker);
        }

        // If Faker is disabled, use legacy static values
        if (! $this->useFaker) {
            return $this->createStaticValue($fieldName, $fieldSchema);
        }

        // Special handling for specific field names
        if ($value = $this->createByFieldName($fieldName, $fieldSchema)) {
            return $value;
        }

        // Create by type
        return $this->createByType($fieldSchema);
    }

    /**
     * Create value based on field name patterns
     */
    private function createByFieldName(string $fieldName, array $fieldSchema): mixed
    {
        $normalized = strtolower(str_replace(['_', '-'], '', $fieldName));

        // Exact matches
        $exactMatches = [
            'id' => fn () => $this->faker->unique()->numberBetween(1, 10000),
            'uuid' => fn () => $this->faker->uuid(),
            'email' => fn () => $this->faker->email(),
            'emailaddress' => fn () => $this->faker->email(),
            'firstname' => fn () => $this->faker->firstName(),
            'lastname' => fn () => $this->faker->lastName(),
            'fullname' => fn () => $this->faker->name(),
            'name' => fn () => $this->handleNameField($fieldName, $fieldSchema),
            'username' => fn () => $this->faker->userName(),
            'password' => fn () => 'hashed_'.$this->faker->lexify('????????'),
            'phone' => fn () => $this->handlePhoneField(),
            'phonenumber' => fn () => $this->handlePhoneField(),
            'mobile' => fn () => $this->handlePhoneField(),
            'address' => fn () => $this->faker->address(),
            'street' => fn () => $this->faker->streetAddress(),
            'city' => fn () => $this->faker->city(),
            'state' => fn () => $this->faker->randomElement(['CA', 'NY', 'TX', 'FL', 'IL']),
            'country' => fn () => $this->faker->country(),
            'countrycode' => fn () => $this->faker->countryCode(),
            'postalcode' => fn () => $this->faker->postcode(),
            'zipcode' => fn () => $this->faker->postcode(),
            'latitude' => fn () => $this->faker->latitude(),
            'longitude' => fn () => $this->faker->longitude(),
            'lat' => fn () => $this->faker->latitude(),
            'lng' => fn () => $this->faker->longitude(),
            'lon' => fn () => $this->faker->longitude(),
            'url' => fn () => $this->faker->url(),
            'website' => fn () => $this->faker->url(),
            'company' => fn () => $this->faker->company(),
            'companyname' => fn () => $this->faker->company(),
            'jobtitle' => fn () => $this->faker->jobTitle(),
            'department' => fn () => $this->faker->randomElement(['Sales', 'Marketing', 'Engineering', 'HR', 'Finance']),
            'bio' => fn () => $this->faker->paragraph(3),
            'biography' => fn () => $this->faker->paragraph(3),
            'description' => fn () => $this->faker->paragraph(2),
            'summary' => fn () => $this->faker->sentence(10),
            'title' => fn () => $this->faker->sentence(4),
            'content' => fn () => $this->faker->paragraphs(3, true),
            'body' => fn () => $this->faker->paragraphs(3, true),
            'slug' => fn () => $this->faker->slug(),
            'token' => fn () => $this->faker->sha256(),
            'apikey' => fn () => $this->faker->sha256(),
            'apitoken' => fn () => $this->faker->sha256(),
            'accesstoken' => fn () => $this->faker->sha256(),
            'refreshtoken' => fn () => $this->faker->sha256(),
            'locale' => fn () => $this->faker->locale(),
            'language' => fn () => $this->faker->languageCode(),
            'currency' => fn () => $this->faker->currencyCode(),
            'timezone' => fn () => $this->faker->timezone(),
            'ipaddress' => fn () => $this->faker->ipv4(),
            'ip' => fn () => $this->faker->ipv4(),
            'useragent' => fn () => $this->faker->userAgent(),
            'color' => fn () => $this->faker->hexColor(),
            'hexcolor' => fn () => $this->faker->hexColor(),
            'gender' => fn () => $this->faker->randomElement(['male', 'female', 'other']),
            'birthdate' => fn () => $this->faker->date('Y-m-d', '-18 years'),
            'age' => fn () => $this->faker->numberBetween(18, 80),
            'price' => fn () => $this->faker->randomFloat(2, 10, 1000),
            'amount' => fn () => $this->faker->randomFloat(2, 0, 10000),
            'quantity' => fn () => $this->faker->numberBetween(1, 100),
            'stock' => fn () => $this->faker->numberBetween(0, 1000),
            'rating' => fn () => $this->faker->randomFloat(1, 1, 5),
            'score' => fn () => $this->faker->numberBetween(0, 100),
            'views' => fn () => $this->faker->numberBetween(0, 100000),
            'clicks' => fn () => $this->faker->numberBetween(0, 10000),
            'downloads' => fn () => $this->faker->numberBetween(0, 50000),
        ];

        if (isset($exactMatches[$normalized])) {
            return $exactMatches[$normalized]();
        }

        // Pattern matches
        if ($this->endsWith($fieldName, ['_id', 'Id'])) {
            return $this->faker->numberBetween(1, 1000);
        }

        if ($this->endsWith($fieldName, ['_at', 'At'])) {
            return $this->handleTimestampField($fieldName);
        }

        if ($this->endsWith($fieldName, ['_date', 'Date'])) {
            return $this->faker->date('Y-m-d');
        }

        if ($this->endsWith($fieldName, ['_time', 'Time'])) {
            return $this->faker->time('H:i:s');
        }

        if ($this->contains($fieldName, ['image', 'photo', 'picture', 'avatar', 'thumbnail'])) {
            return $this->handleImageField($fieldName);
        }

        if ($this->contains($fieldName, ['file', 'document', 'attachment'])) {
            return $this->faker->filePath();
        }

        if ($this->startsWith($fieldName, ['is_', 'has_', 'can_', 'should_'])) {
            return $this->faker->boolean();
        }

        if ($this->endsWith($fieldName, ['_count', 'Count'])) {
            return $this->faker->numberBetween(0, 100);
        }

        if ($this->endsWith($fieldName, ['_percentage', 'Percentage', '_percent', 'Percent'])) {
            return $this->faker->numberBetween(0, 100);
        }

        return null;
    }

    /**
     * Create value based on type
     */
    private function createByType(array $fieldSchema): mixed
    {
        $type = $fieldSchema['type'] ?? 'string';
        $format = $fieldSchema['format'] ?? null;

        // Handle format-specific generation
        if ($format) {
            return $this->createByFormat($format, $type);
        }

        // Handle enum
        if (isset($fieldSchema['enum']) && ! empty($fieldSchema['enum'])) {
            return $this->faker->randomElement($fieldSchema['enum']);
        }

        // Handle basic types
        return match ($type) {
            'integer' => $this->createInteger($fieldSchema),
            'number' => $this->createNumber($fieldSchema),
            'boolean' => $this->faker->boolean(),
            'array' => [],
            'object' => new \stdClass,
            default => $this->createString($fieldSchema),
        };
    }

    /**
     * Create value based on format
     */
    private function createByFormat(string $format, string $type): mixed
    {
        return match ($format) {
            'email' => $this->faker->email(),
            'uri', 'url' => $this->faker->url(),
            'uuid' => $this->faker->uuid(),
            'date' => $this->faker->date('Y-m-d'),
            'time' => $this->faker->time('H:i:s'),
            'date-time' => $this->faker->dateTime()->format('Y-m-d\TH:i:s\Z'),
            'password' => 'hashed_'.$this->faker->lexify('????????'),
            'byte' => base64_encode($this->faker->text(20)),
            'binary' => $this->faker->sha256(),
            'ipv4' => $this->faker->ipv4(),
            'ipv6' => $this->faker->ipv6(),
            'hostname' => $this->faker->domainName(),
            default => $type === 'integer' ? $this->faker->numberBetween() : $this->faker->word(),
        };
    }

    /**
     * Handle name field based on context
     */
    private function handleNameField(string $fieldName, array $fieldSchema): string
    {
        // If it's likely a person's name
        if ($this->contains($fieldName, ['user', 'person', 'customer', 'client', 'employee'])) {
            return $this->faker->name();
        }

        // If it's a product/item name
        if ($this->contains($fieldName, ['product', 'item', 'service'])) {
            return $this->faker->words(3, true);
        }

        // If it's a company name
        if ($this->contains($fieldName, ['company', 'organization', 'vendor', 'supplier'])) {
            return $this->faker->company();
        }

        // Default to a generic name
        return ucfirst($this->faker->words(2, true));
    }

    /**
     * Handle phone field based on locale
     */
    private function handlePhoneField(): string
    {
        // Get the locale from config or use reflection to access private property
        $locale = config('spectrum.example_generation.faker_locale', 'en_US');

        // Special handling for Japanese phone numbers
        if (str_starts_with($locale, 'ja_')) {
            return $this->faker->regexify('0[789]0-[0-9]{4}-[0-9]{4}');
        }

        return $this->faker->phoneNumber();
    }

    /**
     * Handle timestamp fields
     */
    private function handleTimestampField(string $fieldName): ?string
    {
        $normalized = strtolower($fieldName);

        if (str_contains($normalized, 'deleted')) {
            // optional() can return null OR a DateTime object
            /** @var \DateTime|null $dateTime */
            $dateTime = $this->faker->optional(0.2)->dateTime();

            return $dateTime instanceof \DateTime ? $dateTime->format('Y-m-d\TH:i:s\Z') : null;
        }

        if (str_contains($normalized, 'created')) {
            return $this->faker->dateTimeBetween('-1 year', '-1 week')->format('Y-m-d\TH:i:s\Z');
        }

        if (str_contains($normalized, 'updated') || str_contains($normalized, 'modified')) {
            return $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d\TH:i:s\Z');
        }

        if (str_contains($normalized, 'expired') || str_contains($normalized, 'expires')) {
            return $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d\TH:i:s\Z');
        }

        return $this->faker->dateTime()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Handle image URL fields
     */
    private function handleImageField(string $fieldName): string
    {
        $width = 640;
        $height = 480;

        if (str_contains(strtolower($fieldName), 'avatar') || str_contains(strtolower($fieldName), 'profile')) {
            $width = $height = 200;
        } elseif (str_contains(strtolower($fieldName), 'thumbnail')) {
            $width = $height = 150;
        } elseif (str_contains(strtolower($fieldName), 'banner')) {
            $width = 1200;
            $height = 400;
        }

        return $this->faker->imageUrl($width, $height);
    }

    /**
     * Create integer with constraints
     */
    private function createInteger(array $fieldSchema): int
    {
        $min = $fieldSchema['minimum'] ?? 1;
        $max = $fieldSchema['maximum'] ?? 1000000;

        return $this->faker->numberBetween($min, $max);
    }

    /**
     * Create number with constraints
     */
    private function createNumber(array $fieldSchema): float
    {
        $min = $fieldSchema['minimum'] ?? 0;
        $max = $fieldSchema['maximum'] ?? 1000000;
        $decimals = 2;

        return $this->faker->randomFloat($decimals, $min, $max);
    }

    /**
     * Create string with constraints
     */
    private function createString(array $fieldSchema): string
    {
        $minLength = $fieldSchema['minLength'] ?? 1;
        $maxLength = $fieldSchema['maxLength'] ?? 255;

        if ($maxLength <= 10) {
            return $this->faker->lexify(str_repeat('?', $maxLength));
        }

        if ($maxLength > 1000) {
            return $this->faker->paragraphs(3, true);
        }

        if ($maxLength > 100) {
            return $this->faker->paragraph();
        }

        return $this->faker->sentence();
    }

    /**
     * Legacy static value generation
     */
    private function createStaticValue(string $fieldName, array $fieldSchema): mixed
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
        $format = $fieldSchema['format'] ?? null;
        $type = $fieldSchema['type'] ?? 'string';

        // If format is explicitly provided, prioritize it
        if ($format !== null) {
            return $this->generateByType($type, $format);
        }

        // Check field name patterns
        $inferredType = $this->fieldNameInference->inferFieldType($fieldName);
        if ($inferredType['type'] !== 'string' || $inferredType['format'] !== 'text') {
            return $this->generateByInferredType($inferredType);
        }

        // Generate based on constraints
        if (isset($fieldSchema['minimum']) || isset($fieldSchema['maximum'])) {
            return $this->generateWithConstraints($type, $fieldSchema);
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

    // Helper methods
    private function endsWith(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function startsWith(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function contains(string $haystack, array $needles): bool
    {
        $lower = strtolower($haystack);
        foreach ($needles as $needle) {
            if (str_contains($lower, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
