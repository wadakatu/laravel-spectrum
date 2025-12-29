<?php

namespace LaravelSpectrum\Tests\Unit\Support\Example;

use LaravelSpectrum\DTO\FieldPatternConfig;
use LaravelSpectrum\Support\Example\FieldPatternRegistry;
use LaravelSpectrum\Tests\TestCase;

class FieldPatternRegistryTest extends TestCase
{
    private FieldPatternRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new FieldPatternRegistry;
    }

    public function test_get_config_returns_pattern_for_exact_match(): void
    {
        $config = $this->registry->getConfig('email');

        $this->assertNotNull($config);
        $this->assertInstanceOf(FieldPatternConfig::class, $config);
        // Registry uses domain-specific types, not OpenAPI types
        $this->assertEquals('email', $config->type);
        $this->assertEquals('email', $config->format);
        $this->assertEquals('safeEmail', $config->fakerMethod);
    }

    public function test_handles_id_pattern_with_faker_args(): void
    {
        $config = $this->registry->getConfig('id');
        $this->assertNotNull($config);
        $this->assertEquals('id', $config->type);
        $this->assertEquals('integer', $config->format);
        $this->assertEquals('unique->numberBetween', $config->fakerMethod);
        $this->assertEquals([1, 10000], $config->fakerArgs);
        $this->assertEquals(1, $config->staticValue);
    }

    public function test_handles_name_pattern_variations(): void
    {
        // name pattern
        $config = $this->registry->getConfig('name');
        $this->assertNotNull($config);
        $this->assertEquals('name', $config->type);
        $this->assertEquals('full_name', $config->format);
        $this->assertEquals('name', $config->fakerMethod);

        // firstname pattern (normalized from first_name)
        $config = $this->registry->getConfig('first_name');
        $this->assertNotNull($config);
        $this->assertEquals('name', $config->type);
        $this->assertEquals('first_name', $config->format);
        $this->assertEquals('firstName', $config->fakerMethod);

        // lastname pattern (normalized from last_name)
        $config = $this->registry->getConfig('last_name');
        $this->assertNotNull($config);
        $this->assertEquals('name', $config->type);
        $this->assertEquals('last_name', $config->format);
        $this->assertEquals('lastName', $config->fakerMethod);

        // fullname pattern
        $config = $this->registry->getConfig('fullname');
        $this->assertNotNull($config);
        $this->assertEquals('name', $config->type);
        $this->assertEquals('full_name', $config->format);

        // emailaddress pattern (normalized)
        $config = $this->registry->getConfig('emailaddress');
        $this->assertNotNull($config);
        $this->assertEquals('email', $config->type);
        $this->assertEquals('email', $config->format);
    }

    public function test_handles_phone_pattern_variations(): void
    {
        // fax pattern
        $config = $this->registry->getConfig('fax');
        $this->assertNotNull($config);
        $this->assertEquals('phone', $config->type);
        $this->assertEquals('phone', $config->format);
    }

    public function test_handles_address_pattern_variations(): void
    {
        // street pattern
        $config = $this->registry->getConfig('street');
        $this->assertNotNull($config);
        $this->assertEquals('address', $config->type);

        // city pattern
        $config = $this->registry->getConfig('city');
        $this->assertNotNull($config);
        $this->assertEquals('address', $config->type);

        // state pattern with fakerArgs
        $config = $this->registry->getConfig('state');
        $this->assertNotNull($config);
        $this->assertEquals('address', $config->type);
        $this->assertEquals('randomElement', $config->fakerMethod);
        $this->assertEquals([['CA', 'NY', 'TX', 'FL', 'IL']], $config->fakerArgs);

        // postalcode pattern (normalized)
        $config = $this->registry->getConfig('postalcode');
        $this->assertNotNull($config);
        $this->assertEquals('address', $config->type);
    }

    public function test_handles_location_pattern_variations(): void
    {
        // lat pattern
        $config = $this->registry->getConfig('lat');
        $this->assertNotNull($config);
        $this->assertEquals('location', $config->type);
        $this->assertEquals('decimal', $config->format);

        // lng pattern
        $config = $this->registry->getConfig('lng');
        $this->assertNotNull($config);
        $this->assertEquals('location', $config->type);
        $this->assertEquals('decimal', $config->format);
    }

    public function test_get_config_returns_pattern_for_compound_field(): void
    {
        $config = $this->registry->getConfig('user_email');

        $this->assertNotNull($config);
        // Registry uses domain-specific types, not OpenAPI types
        $this->assertEquals('email', $config->type);
        $this->assertEquals('email', $config->format);
    }

    public function test_get_config_returns_pattern_for_suffix_match(): void
    {
        // _id suffix - uses domain-specific type
        $config = $this->registry->getConfig('user_id');
        $this->assertNotNull($config);
        $this->assertEquals('id', $config->type);
        $this->assertEquals('integer', $config->format);

        // _at suffix - uses domain-specific type
        $config = $this->registry->getConfig('published_at');
        $this->assertNotNull($config);
        $this->assertEquals('timestamp', $config->type);
        $this->assertEquals('datetime', $config->format);
    }

    public function test_get_config_returns_pattern_for_prefix_match(): void
    {
        // is_ prefix
        $config = $this->registry->getConfig('is_active');
        $this->assertNotNull($config);
        $this->assertEquals('boolean', $config->type);

        // has_ prefix
        $config = $this->registry->getConfig('has_access');
        $this->assertNotNull($config);
        $this->assertEquals('boolean', $config->type);
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

    public function test_register_pattern_throws_on_empty_pattern(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pattern name cannot be empty');

        $this->registry->registerPattern('', [
            'type' => 'string',
            'staticValue' => 'test',
        ]);
    }

    public function test_register_pattern_throws_on_missing_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must have a non-empty 'type' field");

        $this->registry->registerPattern('test', [
            'staticValue' => 'test',
        ]);
    }

    public function test_register_pattern_throws_on_empty_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must have a non-empty 'type' field");

        $this->registry->registerPattern('test', [
            'type' => '',
            'staticValue' => 'test',
        ]);
    }

    public function test_register_pattern_throws_on_missing_static_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must have a 'staticValue' field");

        $this->registry->registerPattern('test', [
            'type' => 'string',
        ]);
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

    public function test_handles_password_field(): void
    {
        $config = $this->registry->getConfig('password');

        $this->assertNotNull($config);
        // Registry uses domain-specific type
        $this->assertEquals('password', $config->type);
        $this->assertEquals('password', $config->format);
        $this->assertEquals('********', $config->staticValue);
    }

    public function test_handles_uuid_field(): void
    {
        $config = $this->registry->getConfig('uuid');

        $this->assertNotNull($config);
        // Registry uses domain-specific type
        $this->assertEquals('uuid', $config->type);
        $this->assertEquals('uuid', $config->format);
    }

    public function test_handles_monetary_fields(): void
    {
        $config = $this->registry->getConfig('price');
        $this->assertNotNull($config);
        // Registry uses domain-specific type 'money'
        $this->assertEquals('money', $config->type);
        $this->assertEquals('decimal', $config->format);

        $config = $this->registry->getConfig('amount');
        $this->assertNotNull($config);
        $this->assertEquals('money', $config->type);
        $this->assertEquals('decimal', $config->format);
    }

    public function test_handles_location_fields(): void
    {
        $config = $this->registry->getConfig('latitude');
        $this->assertNotNull($config);
        // Registry uses domain-specific type 'location'
        $this->assertEquals('location', $config->type);
        $this->assertEquals('decimal', $config->format);

        $config = $this->registry->getConfig('longitude');
        $this->assertNotNull($config);
        $this->assertEquals('location', $config->type);
        $this->assertEquals('decimal', $config->format);

        $config = $this->registry->getConfig('address');
        $this->assertNotNull($config);
        $this->assertEquals('address', $config->type);
        $this->assertEquals('text', $config->format);
    }

    public function test_handles_url_fields(): void
    {
        $config = $this->registry->getConfig('url');
        $this->assertNotNull($config);
        // Registry uses domain-specific type 'url'
        $this->assertEquals('url', $config->type);
        $this->assertEquals('url', $config->format);

        $config = $this->registry->getConfig('website');
        $this->assertNotNull($config);
        $this->assertEquals('url', $config->type);
        $this->assertEquals('url', $config->format);
    }

    public function test_handles_date_suffix_fields(): void
    {
        // Use expiry_date to test _date suffix pattern
        // (birth_date normalizes to birthdate which has its own pattern)
        $config = $this->registry->getConfig('expiry_date');
        $this->assertNotNull($config);
        $this->assertEquals('date', $config->type);
        $this->assertEquals('date', $config->format);
        $this->assertEquals('date', $config->fakerMethod);
        $this->assertEquals(['Y-m-d'], $config->fakerArgs);
        $this->assertEquals('2024-01-15', $config->staticValue);
    }

    public function test_handles_image_pattern_via_compound_match(): void
    {
        // profile_image matches 'image' pattern via compound field matching
        // (last part after underscore)
        $config = $this->registry->getConfig('profile_image');
        $this->assertNotNull($config);
        $this->assertEquals('url', $config->type);
        $this->assertEquals('image_url', $config->format);
        $this->assertEquals('imageUrl', $config->fakerMethod);
        $this->assertEquals([640, 480], $config->fakerArgs);
        $this->assertEquals('https://example.com/image.jpg', $config->staticValue);
    }

    public function test_handles_image_pattern_via_content_contains(): void
    {
        // Test image pattern via str_contains in matchSuffixPrefixPatterns()
        // This field doesn't match compound matching (last part 'data' has no pattern)
        // But contains 'image' so triggers the content pattern
        $config = $this->registry->getConfig('featured_image_data');
        $this->assertNotNull($config);
        $this->assertEquals('url', $config->type);
        $this->assertEquals('image_url', $config->format);
        $this->assertEquals('imageUrl', $config->fakerMethod);
        $this->assertEquals([640, 480], $config->fakerArgs);
        $this->assertEquals('https://example.com/image.jpg', $config->staticValue);
    }
}
