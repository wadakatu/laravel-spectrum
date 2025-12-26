<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\ExampleValueFactory;
use LaravelSpectrum\Tests\TestCase;

class ExampleValueFactoryTest extends TestCase
{
    private ExampleValueFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable Faker for existing tests to use static values
        config(['spectrum.example_generation.use_faker' => false]);

        $this->factory = new ExampleValueFactory;
    }

    public function test_generates_values_by_field_name(): void
    {
        // Common field names
        $this->assertEquals(1, $this->factory->create('id', ['type' => 'integer']));
        $this->assertEquals('John Doe', $this->factory->create('name', ['type' => 'string']));
        $this->assertEquals('user@example.com', $this->factory->create('email', ['type' => 'string']));
        $this->assertEquals('+1-555-123-4567', $this->factory->create('phone', ['type' => 'string']));
        $this->assertEquals('2024-01-15T10:30:00Z', $this->factory->create('created_at', ['type' => 'string']));
        $this->assertEquals(true, $this->factory->create('is_active', ['type' => 'boolean']));
    }

    public function test_generates_values_by_format(): void
    {
        // Date-time format
        $this->assertEquals(
            '2024-01-15T10:30:00Z',
            $this->factory->create('timestamp', ['type' => 'string', 'format' => 'date-time'])
        );

        // Date format (using generic name to test format, not field pattern)
        $this->assertEquals(
            '2024-01-15',
            $this->factory->create('some_date', ['type' => 'string', 'format' => 'date'])
        );

        // Email format
        $this->assertEquals(
            'user@example.com',
            $this->factory->create('contact', ['type' => 'string', 'format' => 'email'])
        );

        // URL format
        $this->assertEquals(
            'https://example.com',
            $this->factory->create('website', ['type' => 'string', 'format' => 'url'])
        );

        // UUID format
        $this->assertEquals(
            '550e8400-e29b-41d4-a716-446655440000',
            $this->factory->create('uuid', ['type' => 'string', 'format' => 'uuid'])
        );
    }

    public function test_generates_values_by_type(): void
    {
        // String
        $this->assertIsString($this->factory->generateByType('string'));
        $this->assertEquals('string', $this->factory->generateByType('string'));

        // Integer
        $this->assertIsInt($this->factory->generateByType('integer'));
        $this->assertEquals(1, $this->factory->generateByType('integer'));

        // Number
        $this->assertIsFloat($this->factory->generateByType('number'));
        $this->assertEquals(1.0, $this->factory->generateByType('number'));

        // Boolean
        $this->assertIsBool($this->factory->generateByType('boolean'));
        $this->assertEquals(true, $this->factory->generateByType('boolean'));

        // Array
        $this->assertIsArray($this->factory->generateByType('array'));
        $this->assertEquals([], $this->factory->generateByType('array'));

        // Object
        $result = $this->factory->generateByType('object');
        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function test_respects_constraints(): void
    {
        // Min/Max for integers
        $result = $this->factory->create('age', [
            'type' => 'integer',
            'minimum' => 18,
            'maximum' => 100,
        ]);
        $this->assertGreaterThanOrEqual(18, $result);
        $this->assertLessThanOrEqual(100, $result);

        // String with maxLength
        $result = $this->factory->create('short_text', [
            'type' => 'string',
            'maxLength' => 10,
        ]);
        $this->assertLessThanOrEqual(10, strlen($result));
    }

    public function test_handles_password_fields(): void
    {
        $result = $this->factory->create('password', ['type' => 'string']);
        $this->assertEquals('********', $result);

        $result = $this->factory->create('user_password', ['type' => 'string']);
        $this->assertEquals('********', $result);
    }

    public function test_handles_token_fields(): void
    {
        $result = $this->factory->create('api_token', ['type' => 'string']);
        $this->assertStringStartsWith('sk_test_', $result);
        $this->assertStringContainsString('****', $result);

        $result = $this->factory->create('access_token', ['type' => 'string']);
        $this->assertStringStartsWith('sk_test_', $result);
    }

    public function test_handles_url_fields(): void
    {
        $result = $this->factory->create('website_url', ['type' => 'string']);
        $this->assertEquals('https://example.com', $result);

        $result = $this->factory->create('image', ['type' => 'string']);
        $this->assertEquals('https://example.com/image.jpg', $result);

        $result = $this->factory->create('avatar', ['type' => 'string']);
        $this->assertEquals('https://example.com/avatar.jpg', $result);
    }

    public function test_handles_monetary_fields(): void
    {
        $result = $this->factory->create('price', ['type' => 'number']);
        $this->assertEquals(99.99, $result);

        $result = $this->factory->create('amount', ['type' => 'number']);
        $this->assertEquals(100.00, $result);
    }

    public function test_handles_location_fields(): void
    {
        $result = $this->factory->create('latitude', ['type' => 'number']);
        $this->assertEquals(37.7749, $result);

        $result = $this->factory->create('longitude', ['type' => 'number']);
        $this->assertEquals(-122.4194, $result);

        $result = $this->factory->create('address', ['type' => 'string']);
        $this->assertEquals('123 Main St, Anytown, USA', $result);
    }

    public function test_handles_unknown_fields_with_type_defaults(): void
    {
        // Unknown string field
        $result = $this->factory->create('unknown_field', ['type' => 'string']);
        $this->assertEquals('string', $result);

        // Unknown integer field
        $result = $this->factory->create('random_number', ['type' => 'integer']);
        $this->assertEquals(1, $result);

        // Unknown boolean field
        $result = $this->factory->create('some_flag', ['type' => 'boolean']);
        $this->assertEquals(true, $result);
    }

    public function test_handles_field_name_patterns(): void
    {
        // Fields ending with _id
        $result = $this->factory->create('user_id', ['type' => 'integer']);
        $this->assertIsInt($result);

        // Fields ending with _at
        $result = $this->factory->create('updated_at', ['type' => 'string']);
        $this->assertEquals('2024-01-15T10:30:00Z', $result);

        // Fields starting with is_ or has_
        $result = $this->factory->create('is_verified', ['type' => 'boolean']);
        $this->assertIsBool($result);

        $result = $this->factory->create('has_children', ['type' => 'boolean']);
        $this->assertIsBool($result);
    }

    public function test_returns_const_when_provided(): void
    {
        $result = $this->factory->create('status', [
            'type' => 'string',
            'const' => 'fixed_value',
        ]);
        $this->assertEquals('fixed_value', $result);
    }

    public function test_returns_first_example_when_provided(): void
    {
        $result = $this->factory->create('status', [
            'type' => 'string',
            'examples' => ['first_example', 'second_example'],
        ]);
        $this->assertEquals('first_example', $result);
    }

    public function test_returns_first_enum_value_in_static_mode(): void
    {
        $result = $this->factory->create('status', [
            'type' => 'string',
            'enum' => ['active', 'inactive', 'pending'],
        ]);
        $this->assertEquals('active', $result);
    }

    public function test_returns_default_when_provided(): void
    {
        $result = $this->factory->create('count', [
            'type' => 'integer',
            'default' => 42,
        ]);
        $this->assertEquals(42, $result);
    }

    public function test_const_takes_priority_over_examples(): void
    {
        $result = $this->factory->create('value', [
            'type' => 'string',
            'const' => 'const_value',
            'examples' => ['example_value'],
            'default' => 'default_value',
        ]);
        $this->assertEquals('const_value', $result);
    }

    public function test_examples_take_priority_over_enum(): void
    {
        $result = $this->factory->create('value', [
            'type' => 'string',
            'examples' => ['example_value'],
            'enum' => ['enum_value'],
            'default' => 'default_value',
        ]);
        $this->assertEquals('example_value', $result);
    }

    public function test_enum_takes_priority_over_default(): void
    {
        $result = $this->factory->create('value', [
            'type' => 'string',
            'enum' => ['enum_value'],
            'default' => 'default_value',
        ]);
        $this->assertEquals('enum_value', $result);
    }

    public function test_generate_by_type_with_format(): void
    {
        $result = $this->factory->generateByType('string', 'email');
        $this->assertEquals('user@example.com', $result);

        $result = $this->factory->generateByType('string', 'date-time');
        $this->assertEquals('2024-01-15T10:30:00Z', $result);

        $result = $this->factory->generateByType('string', 'uuid');
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $result);
    }

    public function test_get_faker_returns_null_in_static_mode(): void
    {
        $this->assertNull($this->factory->getFaker());
    }
}
