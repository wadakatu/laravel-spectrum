<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\FieldPatternConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldPatternConfigTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_all_properties(): void
    {
        $config = new FieldPatternConfig(
            type: 'email',
            format: 'email',
            fakerMethod: 'safeEmail',
            fakerArgs: [],
            staticValue: 'user@example.com',
        );

        $this->assertEquals('email', $config->type);
        $this->assertEquals('email', $config->format);
        $this->assertEquals('safeEmail', $config->fakerMethod);
        $this->assertEquals([], $config->fakerArgs);
        $this->assertEquals('user@example.com', $config->staticValue);
    }

    #[Test]
    public function it_can_be_constructed_with_nullable_properties(): void
    {
        $config = new FieldPatternConfig(
            type: 'password',
            format: 'password',
            fakerMethod: null,
            fakerArgs: [],
            staticValue: '********',
        );

        $this->assertEquals('password', $config->type);
        $this->assertEquals('password', $config->format);
        $this->assertNull($config->fakerMethod);
        $this->assertEquals([], $config->fakerArgs);
        $this->assertEquals('********', $config->staticValue);
    }

    #[Test]
    public function it_can_be_constructed_with_faker_args(): void
    {
        $config = new FieldPatternConfig(
            type: 'id',
            format: 'integer',
            fakerMethod: 'numberBetween',
            fakerArgs: [1, 1000],
            staticValue: 1,
        );

        $this->assertEquals([1, 1000], $config->fakerArgs);
    }

    #[Test]
    public function it_can_be_constructed_with_null_static_value(): void
    {
        $config = new FieldPatternConfig(
            type: 'timestamp',
            format: 'datetime',
            fakerMethod: null,
            fakerArgs: [],
            staticValue: null,
        );

        $this->assertNull($config->staticValue);
    }

    #[Test]
    public function it_checks_if_has_faker_method(): void
    {
        $withFaker = new FieldPatternConfig(
            type: 'email',
            format: 'email',
            fakerMethod: 'safeEmail',
            fakerArgs: [],
            staticValue: 'user@example.com',
        );

        $withoutFaker = new FieldPatternConfig(
            type: 'password',
            format: 'password',
            fakerMethod: null,
            fakerArgs: [],
            staticValue: '********',
        );

        $this->assertTrue($withFaker->hasFakerMethod());
        $this->assertFalse($withoutFaker->hasFakerMethod());
    }

    #[Test]
    public function it_checks_if_has_format(): void
    {
        $withFormat = new FieldPatternConfig(
            type: 'email',
            format: 'email',
            fakerMethod: 'safeEmail',
            fakerArgs: [],
            staticValue: 'user@example.com',
        );

        $withoutFormat = new FieldPatternConfig(
            type: 'text',
            format: null,
            fakerMethod: 'sentence',
            fakerArgs: [4],
            staticValue: 'Example text',
        );

        $this->assertTrue($withFormat->hasFormat());
        $this->assertFalse($withoutFormat->hasFormat());
    }

    #[Test]
    public function it_checks_if_has_static_value(): void
    {
        $withValue = new FieldPatternConfig(
            type: 'email',
            format: 'email',
            fakerMethod: 'safeEmail',
            fakerArgs: [],
            staticValue: 'user@example.com',
        );

        $withNullValue = new FieldPatternConfig(
            type: 'timestamp',
            format: 'datetime',
            fakerMethod: null,
            fakerArgs: [],
            staticValue: null,
        );

        $this->assertTrue($withValue->hasStaticValue());
        $this->assertFalse($withNullValue->hasStaticValue());
    }

    #[Test]
    public function it_checks_if_has_faker_args(): void
    {
        $withArgs = new FieldPatternConfig(
            type: 'id',
            format: 'integer',
            fakerMethod: 'numberBetween',
            fakerArgs: [1, 1000],
            staticValue: 1,
        );

        $withoutArgs = new FieldPatternConfig(
            type: 'email',
            format: 'email',
            fakerMethod: 'safeEmail',
            fakerArgs: [],
            staticValue: 'user@example.com',
        );

        $this->assertTrue($withArgs->hasFakerArgs());
        $this->assertFalse($withoutArgs->hasFakerArgs());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $config = new FieldPatternConfig(
            type: 'email',
            format: 'email',
            fakerMethod: 'safeEmail',
            fakerArgs: [],
            staticValue: 'user@example.com',
        );

        $array = $config->toArray();

        $this->assertEquals([
            'type' => 'email',
            'format' => 'email',
            'fakerMethod' => 'safeEmail',
            'fakerArgs' => [],
            'staticValue' => 'user@example.com',
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_with_null_values(): void
    {
        $config = new FieldPatternConfig(
            type: 'password',
            format: 'password',
            fakerMethod: null,
            fakerArgs: [],
            staticValue: '********',
        );

        $array = $config->toArray();

        $this->assertEquals([
            'type' => 'password',
            'format' => 'password',
            'fakerMethod' => null,
            'fakerArgs' => [],
            'staticValue' => '********',
        ], $array);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'type' => 'email',
            'format' => 'email',
            'fakerMethod' => 'safeEmail',
            'fakerArgs' => [],
            'staticValue' => 'user@example.com',
        ];

        $config = FieldPatternConfig::fromArray($data);

        $this->assertEquals('email', $config->type);
        $this->assertEquals('email', $config->format);
        $this->assertEquals('safeEmail', $config->fakerMethod);
        $this->assertEquals([], $config->fakerArgs);
        $this->assertEquals('user@example.com', $config->staticValue);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [
            'type' => 'text',
            'staticValue' => 'Hello',
        ];

        $config = FieldPatternConfig::fromArray($data);

        $this->assertEquals('text', $config->type);
        $this->assertNull($config->format);
        $this->assertNull($config->fakerMethod);
        $this->assertEquals([], $config->fakerArgs);
        $this->assertEquals('Hello', $config->staticValue);
    }

    #[Test]
    public function it_creates_from_array_with_complex_faker_args(): void
    {
        $data = [
            'type' => 'status',
            'format' => 'string',
            'fakerMethod' => 'randomElement',
            'fakerArgs' => [['active', 'inactive', 'pending']],
            'staticValue' => 'active',
        ];

        $config = FieldPatternConfig::fromArray($data);

        $this->assertEquals('randomElement', $config->fakerMethod);
        $this->assertEquals([['active', 'inactive', 'pending']], $config->fakerArgs);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new FieldPatternConfig(
            type: 'id',
            format: 'integer',
            fakerMethod: 'unique->numberBetween',
            fakerArgs: [1, 10000],
            staticValue: 1,
        );

        $restored = FieldPatternConfig::fromArray($original->toArray());

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->format, $restored->format);
        $this->assertEquals($original->fakerMethod, $restored->fakerMethod);
        $this->assertEquals($original->fakerArgs, $restored->fakerArgs);
        $this->assertEquals($original->staticValue, $restored->staticValue);
    }

    #[Test]
    public function it_handles_numeric_static_values(): void
    {
        $config = new FieldPatternConfig(
            type: 'money',
            format: 'decimal',
            fakerMethod: 'randomFloat',
            fakerArgs: [2, 10, 1000],
            staticValue: 99.99,
        );

        $this->assertEquals(99.99, $config->staticValue);
        $this->assertTrue($config->hasStaticValue());
    }

    #[Test]
    public function it_handles_boolean_static_values(): void
    {
        $config = new FieldPatternConfig(
            type: 'boolean',
            format: 'boolean',
            fakerMethod: 'boolean',
            fakerArgs: [],
            staticValue: true,
        );

        $this->assertTrue($config->staticValue);
        $this->assertTrue($config->hasStaticValue());
    }

    #[Test]
    public function it_handles_zero_as_valid_static_value(): void
    {
        $config = new FieldPatternConfig(
            type: 'quantity',
            format: 'integer',
            fakerMethod: 'numberBetween',
            fakerArgs: [0, 100],
            staticValue: 0,
        );

        // 0 is a valid value, so hasStaticValue should return true
        $this->assertEquals(0, $config->staticValue);
        $this->assertTrue($config->hasStaticValue());
    }

    #[Test]
    public function it_handles_false_as_valid_static_value(): void
    {
        $config = new FieldPatternConfig(
            type: 'boolean',
            format: 'boolean',
            fakerMethod: 'boolean',
            fakerArgs: [],
            staticValue: false,
        );

        // false is a valid value, so hasStaticValue should return true
        $this->assertFalse($config->staticValue);
        $this->assertTrue($config->hasStaticValue());
    }

    #[Test]
    public function it_handles_empty_string_as_valid_static_value(): void
    {
        $config = new FieldPatternConfig(
            type: 'text',
            format: null,
            fakerMethod: null,
            fakerArgs: [],
            staticValue: '',
        );

        // Empty string is a valid value
        $this->assertEquals('', $config->staticValue);
        $this->assertTrue($config->hasStaticValue());
    }

    #[Test]
    public function it_checks_is_chained_faker_method(): void
    {
        $chained = new FieldPatternConfig(
            type: 'id',
            format: 'integer',
            fakerMethod: 'unique->numberBetween',
            fakerArgs: [1, 10000],
            staticValue: 1,
        );

        $simple = new FieldPatternConfig(
            type: 'email',
            format: 'email',
            fakerMethod: 'safeEmail',
            fakerArgs: [],
            staticValue: 'user@example.com',
        );

        $noMethod = new FieldPatternConfig(
            type: 'password',
            format: 'password',
            fakerMethod: null,
            fakerArgs: [],
            staticValue: '********',
        );

        $this->assertTrue($chained->isChainedFakerMethod());
        $this->assertFalse($simple->isChainedFakerMethod());
        $this->assertFalse($noMethod->isChainedFakerMethod());
    }
}
