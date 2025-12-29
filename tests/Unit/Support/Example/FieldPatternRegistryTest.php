<?php

namespace LaravelSpectrum\Tests\Unit\Support\Example;

use LaravelSpectrum\DTO\FieldPatternConfig;
use LaravelSpectrum\Support\Example\FieldPatternRegistry;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class FieldPatternRegistryTest extends TestCase
{
    private FieldPatternRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new FieldPatternRegistry;
    }

    /**
     * @return array<string, array{field: string, type: string, format: ?string, fakerMethod: ?string, fakerArgs: ?array<mixed>, staticValue: mixed}>
     */
    public static function patternProvider(): array
    {
        return [
            // ID patterns
            'id' => [
                'field' => 'id',
                'type' => 'id',
                'format' => 'integer',
                'fakerMethod' => 'unique->numberBetween',
                'fakerArgs' => [1, 10000],
                'staticValue' => 1,
            ],
            'user_id suffix' => [
                'field' => 'user_id',
                'type' => 'id',
                'format' => 'integer',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],

            // Email patterns
            'email' => [
                'field' => 'email',
                'type' => 'email',
                'format' => 'email',
                'fakerMethod' => 'safeEmail',
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'emailaddress normalized' => [
                'field' => 'emailaddress',
                'type' => 'email',
                'format' => 'email',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'user_email compound' => [
                'field' => 'user_email',
                'type' => 'email',
                'format' => 'email',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],

            // Name patterns
            'name' => [
                'field' => 'name',
                'type' => 'name',
                'format' => 'full_name',
                'fakerMethod' => 'name',
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'first_name normalized' => [
                'field' => 'first_name',
                'type' => 'name',
                'format' => 'first_name',
                'fakerMethod' => 'firstName',
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'last_name normalized' => [
                'field' => 'last_name',
                'type' => 'name',
                'format' => 'last_name',
                'fakerMethod' => 'lastName',
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'fullname' => [
                'field' => 'fullname',
                'type' => 'name',
                'format' => 'full_name',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],

            // Phone patterns
            'phone' => [
                'field' => 'phone',
                'type' => 'phone',
                'format' => 'phone',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'phonenumber' => [
                'field' => 'phonenumber',
                'type' => 'phone',
                'format' => 'phone',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'fax' => [
                'field' => 'fax',
                'type' => 'phone',
                'format' => 'phone',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],

            // Address patterns
            'street' => [
                'field' => 'street',
                'type' => 'address',
                'format' => null,
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'city' => [
                'field' => 'city',
                'type' => 'address',
                'format' => null,
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'state with fakerArgs' => [
                'field' => 'state',
                'type' => 'address',
                'format' => null,
                'fakerMethod' => 'randomElement',
                'fakerArgs' => [['CA', 'NY', 'TX', 'FL', 'IL']],
                'staticValue' => null,
            ],
            'postalcode normalized' => [
                'field' => 'postalcode',
                'type' => 'address',
                'format' => null,
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'postal_code with underscore' => [
                'field' => 'postal_code',
                'type' => 'address',
                'format' => null,
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'country' => [
                'field' => 'country',
                'type' => 'address',
                'format' => null,
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'countrycode' => [
                'field' => 'countrycode',
                'type' => 'address',
                'format' => null,
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'zipcode' => [
                'field' => 'zipcode',
                'type' => 'address',
                'format' => null,
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'address' => [
                'field' => 'address',
                'type' => 'address',
                'format' => 'text',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],

            // Location patterns
            'latitude' => [
                'field' => 'latitude',
                'type' => 'location',
                'format' => 'decimal',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'longitude' => [
                'field' => 'longitude',
                'type' => 'location',
                'format' => 'decimal',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'lat' => [
                'field' => 'lat',
                'type' => 'location',
                'format' => 'decimal',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'lng' => [
                'field' => 'lng',
                'type' => 'location',
                'format' => 'decimal',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'lon' => [
                'field' => 'lon',
                'type' => 'location',
                'format' => 'decimal',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],

            // URL patterns
            'url' => [
                'field' => 'url',
                'type' => 'url',
                'format' => 'url',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'website' => [
                'field' => 'website',
                'type' => 'url',
                'format' => 'url',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],

            // Image/Avatar patterns
            'avatar' => [
                'field' => 'avatar',
                'type' => 'url',
                'format' => 'avatar_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [200, 200],
                'staticValue' => null,
            ],
            'thumbnail' => [
                'field' => 'thumbnail',
                'type' => 'url',
                'format' => 'image_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [150, 150],
                'staticValue' => null,
            ],
            'photo' => [
                'field' => 'photo',
                'type' => 'url',
                'format' => 'image_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [640, 480],
                'staticValue' => null,
            ],
            'picture' => [
                'field' => 'picture',
                'type' => 'url',
                'format' => 'image_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [640, 480],
                'staticValue' => null,
            ],
            'icon' => [
                'field' => 'icon',
                'type' => 'url',
                'format' => 'image_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [64, 64],
                'staticValue' => null,
            ],
            'logo' => [
                'field' => 'logo',
                'type' => 'url',
                'format' => 'image_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [200, 200],
                'staticValue' => null,
            ],
            'banner' => [
                'field' => 'banner',
                'type' => 'url',
                'format' => 'image_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [1200, 400],
                'staticValue' => null,
            ],
            'cover' => [
                'field' => 'cover',
                'type' => 'url',
                'format' => 'image_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [1200, 600],
                'staticValue' => null,
            ],
            'profile_image compound' => [
                'field' => 'profile_image',
                'type' => 'url',
                'format' => 'image_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [640, 480],
                'staticValue' => 'https://example.com/image.jpg',
            ],
            'featured_image_data contains' => [
                'field' => 'featured_image_data',
                'type' => 'url',
                'format' => 'image_url',
                'fakerMethod' => 'imageUrl',
                'fakerArgs' => [640, 480],
                'staticValue' => 'https://example.com/image.jpg',
            ],

            // Password pattern
            'password' => [
                'field' => 'password',
                'type' => 'password',
                'format' => 'password',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => '********',
            ],

            // UUID pattern
            'uuid' => [
                'field' => 'uuid',
                'type' => 'uuid',
                'format' => 'uuid',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],

            // Monetary patterns
            'price' => [
                'field' => 'price',
                'type' => 'money',
                'format' => 'decimal',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'amount' => [
                'field' => 'amount',
                'type' => 'money',
                'format' => 'decimal',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],

            // Date/Time patterns
            'published_at suffix' => [
                'field' => 'published_at',
                'type' => 'timestamp',
                'format' => 'datetime',
                'fakerMethod' => null,
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'expiry_date suffix' => [
                'field' => 'expiry_date',
                'type' => 'date',
                'format' => 'date',
                'fakerMethod' => 'date',
                'fakerArgs' => ['Y-m-d'],
                'staticValue' => '2024-01-15',
            ],

            // Boolean patterns (exact matches from PATTERNS)
            'is_active exact' => [
                'field' => 'is_active',
                'type' => 'boolean',
                'format' => 'boolean',
                'fakerMethod' => 'boolean',
                'fakerArgs' => [],
                'staticValue' => true,
            ],
            'is_admin exact' => [
                'field' => 'is_admin',
                'type' => 'boolean',
                'format' => 'boolean',
                'fakerMethod' => 'boolean',
                'fakerArgs' => [],
                'staticValue' => false,
            ],
            'has_children exact' => [
                'field' => 'has_children',
                'type' => 'boolean',
                'format' => 'boolean',
                'fakerMethod' => 'boolean',
                'fakerArgs' => [],
                'staticValue' => false,
            ],
            'has_access prefix pattern' => [
                'field' => 'has_access',
                'type' => 'boolean',
                'format' => 'boolean',
                'fakerMethod' => 'boolean',
                'fakerArgs' => [],
                'staticValue' => true,
            ],

            // Color patterns
            'color exact' => [
                'field' => 'color',
                'type' => 'color',
                'format' => 'hex',
                'fakerMethod' => 'hexColor',
                'fakerArgs' => [],
                'staticValue' => '#FF5733',
            ],
            'hexcolor exact' => [
                'field' => 'hexcolor',
                'type' => 'color',
                'format' => 'hex',
                'fakerMethod' => 'hexColor',
                'fakerArgs' => [],
                'staticValue' => '#FF5733',
            ],
            'hex_color normalized' => [
                'field' => 'hex_color',
                'type' => 'color',
                'format' => 'hex',
                'fakerMethod' => 'hexColor',
                'fakerArgs' => [],
                'staticValue' => '#FF5733',
            ],

            // Gender pattern
            'gender exact' => [
                'field' => 'gender',
                'type' => 'gender',
                'format' => 'string',
                'fakerMethod' => 'randomElement',
                'fakerArgs' => [['male', 'female', 'other']],
                'staticValue' => 'male',
            ],

            // Company patterns
            'company' => [
                'field' => 'company',
                'type' => 'company',
                'format' => 'text',
                'fakerMethod' => 'company',
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'jobtitle' => [
                'field' => 'jobtitle',
                'type' => 'job',
                'format' => 'text',
                'fakerMethod' => 'jobTitle',
                'fakerArgs' => null,
                'staticValue' => null,
            ],
            'department with fakerArgs' => [
                'field' => 'department',
                'type' => 'department',
                'format' => 'text',
                'fakerMethod' => 'randomElement',
                'fakerArgs' => [['Sales', 'Marketing', 'Engineering', 'HR', 'Finance']],
                'staticValue' => null,
            ],
        ];
    }

    /**
     * @param  array<mixed>|null  $fakerArgs
     */
    #[DataProvider('patternProvider')]
    public function test_pattern_returns_correct_config(
        string $field,
        string $type,
        ?string $format,
        ?string $fakerMethod,
        ?array $fakerArgs,
        mixed $staticValue
    ): void {
        $config = $this->registry->getConfig($field);

        $this->assertNotNull($config, "Config should not be null for field: {$field}");
        $this->assertInstanceOf(FieldPatternConfig::class, $config);
        $this->assertEquals($type, $config->type, "Type mismatch for field: {$field}");

        if ($format !== null) {
            $this->assertEquals($format, $config->format, "Format mismatch for field: {$field}");
        }

        if ($fakerMethod !== null) {
            $this->assertEquals($fakerMethod, $config->fakerMethod, "FakerMethod mismatch for field: {$field}");
        }

        if ($fakerArgs !== null) {
            $this->assertEquals($fakerArgs, $config->fakerArgs, "FakerArgs mismatch for field: {$field}");
        }

        if ($staticValue !== null) {
            $this->assertEquals($staticValue, $config->staticValue, "StaticValue mismatch for field: {$field}");
        }
    }

    public function test_get_config_returns_null_for_unknown_field(): void
    {
        $config = $this->registry->getConfig('random_unknown_field');

        $this->assertNull($config);
    }

    public function test_match_pattern_is_alias_for_get_config(): void
    {
        $getConfigResult = $this->registry->getConfig('email');
        $matchPatternResult = $this->registry->matchPattern('email');

        $this->assertEquals($getConfigResult, $matchPatternResult);
    }

    public function test_register_pattern_adds_custom_pattern(): void
    {
        $this->registry->registerPattern('custom_field', [
            'type' => 'string',
            'format' => 'custom',
            'fakerMethod' => 'word',
            'staticValue' => 'custom_value',
        ]);

        $config = $this->registry->getConfig('custom_field');

        $this->assertNotNull($config);
        $this->assertEquals('string', $config->type);
        $this->assertEquals('custom', $config->format);
        $this->assertEquals('word', $config->fakerMethod);
        $this->assertEquals('custom_value', $config->staticValue);
    }

    public function test_register_pattern_custom_takes_priority(): void
    {
        // Override built-in email pattern
        $this->registry->registerPattern('email', [
            'type' => 'string',
            'format' => 'custom-email',
            'fakerMethod' => 'email',
            'staticValue' => 'custom@example.com',
        ]);

        $config = $this->registry->getConfig('email');

        $this->assertEquals('custom-email', $config->format);
        $this->assertEquals('custom@example.com', $config->staticValue);
    }

    public function test_register_pattern_accepts_field_pattern_config_directly(): void
    {
        $config = new FieldPatternConfig(
            type: 'custom',
            format: 'custom_format',
            fakerMethod: 'word',
            fakerArgs: ['arg1', 'arg2'],
            staticValue: 'dto_value',
        );

        $this->registry->registerPattern('dto_field', $config);

        $result = $this->registry->getConfig('dto_field');

        // Should return the exact same object (no conversion needed)
        $this->assertSame($config, $result);
        $this->assertEquals('custom', $result->type);
        $this->assertEquals('custom_format', $result->format);
        $this->assertEquals(['arg1', 'arg2'], $result->fakerArgs);
        $this->assertEquals('dto_value', $result->staticValue);
    }

    /**
     * @return array<string, array{pattern: string, config: array<string, mixed>, expectedException: string, expectedMessage: string}>
     */
    public static function invalidPatternProvider(): array
    {
        return [
            'empty pattern name' => [
                'pattern' => '',
                'config' => ['type' => 'string', 'staticValue' => 'test'],
                'expectedException' => \InvalidArgumentException::class,
                'expectedMessage' => 'Pattern name cannot be empty',
            ],
            'missing type' => [
                'pattern' => 'test',
                'config' => ['staticValue' => 'test'],
                'expectedException' => \InvalidArgumentException::class,
                'expectedMessage' => "must have a non-empty 'type' field",
            ],
            'empty type' => [
                'pattern' => 'test',
                'config' => ['type' => '', 'staticValue' => 'test'],
                'expectedException' => \InvalidArgumentException::class,
                'expectedMessage' => "must have a non-empty 'type' field",
            ],
            'missing staticValue' => [
                'pattern' => 'test',
                'config' => ['type' => 'string'],
                'expectedException' => \InvalidArgumentException::class,
                'expectedMessage' => "must have a 'staticValue' field",
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    #[DataProvider('invalidPatternProvider')]
    public function test_register_pattern_throws_on_invalid_input(
        string $pattern,
        array $config,
        string $expectedException,
        string $expectedMessage
    ): void {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        $this->registry->registerPattern($pattern, $config);
    }

    public function test_register_pattern_allows_null_static_value(): void
    {
        $this->registry->registerPattern('nullable_field', [
            'type' => 'string',
            'staticValue' => null,
        ]);

        $config = $this->registry->getConfig('nullable_field');

        $this->assertNotNull($config);
        $this->assertNull($config->staticValue);
    }

    public function test_get_all_patterns_returns_merged_patterns(): void
    {
        $this->registry->registerPattern('custom', [
            'type' => 'string',
            'staticValue' => 'custom',
        ]);

        $patterns = $this->registry->getAllPatterns();

        // Should contain both built-in and custom patterns
        $this->assertArrayHasKey('custom', $patterns);
        $this->assertArrayHasKey('email', $patterns);
        $this->assertArrayHasKey('id', $patterns);
    }
}
