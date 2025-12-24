<?php

namespace LaravelSpectrum\Tests\Unit\Support\Example\ValueProviders;

use LaravelSpectrum\Support\Example\FieldPatternRegistry;
use LaravelSpectrum\Support\Example\ValueProviders\StaticValueProvider;
use LaravelSpectrum\Tests\TestCase;

class StaticValueProviderTest extends TestCase
{
    private StaticValueProvider $provider;

    private FieldPatternRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new FieldPatternRegistry;
        $this->provider = new StaticValueProvider($this->registry);
    }

    public function test_generate_uses_registry_static_value(): void
    {
        $result = $this->provider->generate('email', ['type' => 'string']);

        $this->assertEquals('user@example.com', $result);
    }

    public function test_generate_falls_back_to_type(): void
    {
        $result = $this->provider->generate('unknown_field', ['type' => 'string']);

        $this->assertEquals('string', $result);
    }

    public function test_generate_handles_format_from_config(): void
    {
        $result = $this->provider->generate('some_field', [
            'type' => 'string',
            'format' => 'email',
        ]);

        $this->assertEquals('user@example.com', $result);
    }

    public function test_generate_by_format_returns_email(): void
    {
        $result = $this->provider->generateByFormat('email');

        $this->assertEquals('user@example.com', $result);
    }

    public function test_generate_by_format_returns_uuid(): void
    {
        $result = $this->provider->generateByFormat('uuid');

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $result);
    }

    public function test_generate_by_format_returns_url(): void
    {
        $result = $this->provider->generateByFormat('url');

        $this->assertEquals('https://example.com', $result);
    }

    public function test_generate_by_format_returns_uri(): void
    {
        $result = $this->provider->generateByFormat('uri');

        $this->assertEquals('https://example.com', $result);
    }

    public function test_generate_by_format_returns_date(): void
    {
        $result = $this->provider->generateByFormat('date');

        $this->assertEquals('2024-01-15', $result);
    }

    public function test_generate_by_format_returns_time(): void
    {
        $result = $this->provider->generateByFormat('time');

        $this->assertEquals('10:30:00', $result);
    }

    public function test_generate_by_format_returns_datetime(): void
    {
        $result = $this->provider->generateByFormat('date-time');

        $this->assertEquals('2024-01-15T10:30:00Z', $result);
    }

    public function test_generate_by_format_returns_ipv4(): void
    {
        $result = $this->provider->generateByFormat('ipv4');

        $this->assertEquals('192.168.1.1', $result);
    }

    public function test_generate_by_format_returns_ipv6(): void
    {
        $result = $this->provider->generateByFormat('ipv6');

        $this->assertEquals('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $result);
    }

    public function test_generate_by_format_returns_hostname(): void
    {
        $result = $this->provider->generateByFormat('hostname');

        $this->assertEquals('example.com', $result);
    }

    public function test_generate_by_format_returns_password(): void
    {
        $result = $this->provider->generateByFormat('password');

        $this->assertEquals('********', $result);
    }

    public function test_generate_by_format_returns_byte(): void
    {
        $result = $this->provider->generateByFormat('byte');

        // Should be base64 encoded
        $decoded = base64_decode($result, true);
        $this->assertNotFalse($decoded);
    }

    public function test_generate_by_format_returns_binary(): void
    {
        $result = $this->provider->generateByFormat('binary');

        // Static hex string representation
        $this->assertEquals('0x1234567890abcdef', $result);
    }

    public function test_generate_by_format_falls_back_for_unknown(): void
    {
        $result = $this->provider->generateByFormat('unknown_format');

        $this->assertEquals('string', $result);
    }

    public function test_generate_by_type_returns_integer(): void
    {
        $result = $this->provider->generateByType('integer');

        $this->assertEquals(1, $result);
    }

    public function test_generate_by_type_returns_integer_with_minimum(): void
    {
        // When minimum is provided, StaticValueProvider returns (min + max) / 2
        // Default max is 100, so (10 + 100) / 2 = 55
        $result = $this->provider->generateByType('integer', ['minimum' => 10]);

        $this->assertEquals(55, $result);
    }

    public function test_generate_by_type_returns_number(): void
    {
        $result = $this->provider->generateByType('number');

        $this->assertEquals(1.0, $result);
    }

    public function test_generate_by_type_returns_number_with_minimum(): void
    {
        // When minimum is provided, StaticValueProvider returns (min + max) / 2
        // Default max is 100, so (5.5 + 100) / 2 = 52.75
        $result = $this->provider->generateByType('number', ['minimum' => 5.5]);

        $this->assertEquals(52.75, $result);
    }

    public function test_generate_by_type_returns_boolean(): void
    {
        $result = $this->provider->generateByType('boolean');

        $this->assertTrue($result);
    }

    public function test_generate_by_type_returns_array(): void
    {
        $result = $this->provider->generateByType('array');

        $this->assertEquals([], $result);
    }

    public function test_generate_by_type_returns_object(): void
    {
        $result = $this->provider->generateByType('object');

        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function test_generate_by_type_returns_string(): void
    {
        $result = $this->provider->generateByType('string');

        $this->assertEquals('string', $result);
    }

    public function test_generate_by_type_returns_string_for_unknown(): void
    {
        $result = $this->provider->generateByType('unknown_type');

        $this->assertEquals('string', $result);
    }

    public function test_handles_password_field(): void
    {
        $result = $this->provider->generate('password', ['type' => 'string']);

        $this->assertEquals('********', $result);
    }

    public function test_handles_id_field(): void
    {
        $result = $this->provider->generate('id', ['type' => 'integer']);

        $this->assertEquals(1, $result);
    }

    public function test_handles_user_id_field(): void
    {
        $result = $this->provider->generate('user_id', ['type' => 'integer']);

        $this->assertEquals(1, $result);
    }

    public function test_handles_name_field(): void
    {
        $result = $this->provider->generate('name', ['type' => 'string']);

        $this->assertEquals('John Doe', $result);
    }

    public function test_handles_phone_field(): void
    {
        $result = $this->provider->generate('phone', ['type' => 'string']);

        $this->assertEquals('+1-555-123-4567', $result);
    }

    public function test_handles_url_field(): void
    {
        $result = $this->provider->generate('url', ['type' => 'string']);

        $this->assertEquals('https://example.com', $result);
    }

    public function test_handles_price_field(): void
    {
        $result = $this->provider->generate('price', ['type' => 'number']);

        $this->assertEquals(99.99, $result);
    }

    public function test_handles_amount_field(): void
    {
        $result = $this->provider->generate('amount', ['type' => 'number']);

        $this->assertEquals(100.00, $result);
    }

    public function test_handles_latitude_field(): void
    {
        $result = $this->provider->generate('latitude', ['type' => 'number']);

        $this->assertEquals(37.7749, $result);
    }

    public function test_handles_longitude_field(): void
    {
        $result = $this->provider->generate('longitude', ['type' => 'number']);

        $this->assertEquals(-122.4194, $result);
    }

    public function test_handles_address_field(): void
    {
        $result = $this->provider->generate('address', ['type' => 'string']);

        $this->assertEquals('123 Main St, Anytown, USA', $result);
    }

    public function test_handles_token_field(): void
    {
        $result = $this->provider->generate('api_token', ['type' => 'string']);

        $this->assertStringContainsString('sk_test_', $result);
    }

    public function test_handles_is_prefix_boolean(): void
    {
        $result = $this->provider->generate('is_active', ['type' => 'boolean']);

        $this->assertTrue($result);
    }

    public function test_handles_has_prefix_boolean(): void
    {
        $result = $this->provider->generate('has_access', ['type' => 'boolean']);

        $this->assertTrue($result);
    }

    public function test_handles_timestamp_fields(): void
    {
        $result = $this->provider->generate('created_at', ['type' => 'string']);

        $this->assertEquals('2024-01-15T10:30:00Z', $result);
    }

    public function test_custom_pattern_overrides_builtin(): void
    {
        $this->registry->registerPattern('email', [
            'type' => 'string',
            'format' => 'email',
            'fakerMethod' => null,
            'staticValue' => 'custom@test.com',
        ]);

        $result = $this->provider->generate('email', ['type' => 'string']);

        $this->assertEquals('custom@test.com', $result);
    }
}
