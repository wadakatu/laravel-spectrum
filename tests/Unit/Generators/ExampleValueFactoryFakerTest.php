<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\ExampleValueFactory;
use LaravelSpectrum\Tests\TestCase;

/**
 * Tests for Faker-enabled mode.
 */
class ExampleValueFactoryFakerTest extends TestCase
{
    private ExampleValueFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable Faker with fixed seed for deterministic tests
        config(['spectrum.example_generation.use_faker' => true]);
        config(['spectrum.example_generation.faker_seed' => 12345]);

        $this->factory = new ExampleValueFactory;
    }

    public function test_get_faker_returns_instance_in_faker_mode(): void
    {
        $this->assertNotNull($this->factory->getFaker());
    }

    public function test_custom_generator_is_called_in_faker_mode(): void
    {
        $result = $this->factory->create('custom', ['type' => 'string'], function ($faker) {
            return 'custom_generated_value';
        });

        $this->assertEquals('custom_generated_value', $result);
    }

    public function test_enum_value_is_random_in_faker_mode(): void
    {
        $values = ['a', 'b', 'c'];
        $result = $this->factory->create('status', [
            'type' => 'string',
            'enum' => $values,
        ]);

        $this->assertContains($result, $values);
    }

    public function test_timestamp_field_created_at(): void
    {
        $result = $this->factory->create('created_at', ['type' => 'string']);

        // Should return a valid ISO 8601 date string
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $result);
    }

    public function test_timestamp_field_updated_at(): void
    {
        $result = $this->factory->create('updated_at', ['type' => 'string']);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $result);
    }

    public function test_timestamp_field_deleted_at(): void
    {
        // deleted_at can be null or a date
        $result = $this->factory->create('deleted_at', ['type' => 'string']);

        // Should return null or a valid ISO 8601 date string
        $this->assertTrue($result === null || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $result) === 1);
    }

    public function test_timestamp_field_expires_at(): void
    {
        $result = $this->factory->create('expires_at', ['type' => 'string']);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $result);
    }

    public function test_name_field_generates_name(): void
    {
        $result = $this->factory->create('name', ['type' => 'string']);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_phone_field_generates_phone_number(): void
    {
        $result = $this->factory->create('phone', ['type' => 'string']);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_image_field_avatar_generates_url(): void
    {
        $result = $this->factory->create('avatar', ['type' => 'string']);

        $this->assertIsString($result);
        $this->assertStringContainsString('200x200', $result);
    }

    public function test_image_field_thumbnail_generates_url(): void
    {
        $result = $this->factory->create('thumbnail', ['type' => 'string']);

        $this->assertIsString($result);
        $this->assertStringContainsString('150x150', $result);
    }

    public function test_image_field_banner_generates_url(): void
    {
        $result = $this->factory->create('banner', ['type' => 'string']);

        $this->assertIsString($result);
        $this->assertStringContainsString('1200x400', $result);
    }

    public function test_image_field_cover_generates_url(): void
    {
        $result = $this->factory->create('cover_image', ['type' => 'string']);

        $this->assertIsString($result);
        $this->assertStringContainsString('1200x600', $result);
    }

    public function test_image_field_generic_generates_url(): void
    {
        $result = $this->factory->create('product_image', ['type' => 'string']);

        $this->assertIsString($result);
        $this->assertStringContainsString('640x480', $result);
    }

    public function test_contextual_name_for_product(): void
    {
        $result = $this->factory->create('product_name', ['type' => 'string']);

        $this->assertIsString($result);
    }

    public function test_contextual_name_for_company(): void
    {
        $result = $this->factory->create('company_name', ['type' => 'string']);

        $this->assertIsString($result);
    }

    public function test_phone_field_generates_japanese_format_with_ja_locale(): void
    {
        config(['spectrum.example_generation.faker_locale' => 'ja_JP']);
        $factory = new ExampleValueFactory;

        $result = $factory->create('phone', ['type' => 'string']);

        // Japanese mobile phone format: 0X0-XXXX-XXXX
        $this->assertMatchesRegularExpression('/^0[789]0-\d{4}-\d{4}$/', $result);
    }
}
