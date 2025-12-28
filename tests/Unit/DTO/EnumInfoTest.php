<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\EnumBackingType;
use LaravelSpectrum\DTO\EnumInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnumInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $info = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['pending', 'active', 'completed'],
            backingType: EnumBackingType::STRING,
        );

        $this->assertEquals('App\\Enums\\Status', $info->class);
        $this->assertEquals(['pending', 'active', 'completed'], $info->values);
        $this->assertEquals(EnumBackingType::STRING, $info->backingType);
    }

    #[Test]
    public function it_can_be_constructed_with_integer_backing(): void
    {
        $info = new EnumInfo(
            class: 'App\\Enums\\Priority',
            values: [1, 2, 3],
            backingType: EnumBackingType::INTEGER,
        );

        $this->assertEquals('App\\Enums\\Priority', $info->class);
        $this->assertEquals([1, 2, 3], $info->values);
        $this->assertEquals(EnumBackingType::INTEGER, $info->backingType);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'class' => 'App\\Enums\\Color',
            'values' => ['red', 'green', 'blue'],
            'type' => 'string',
        ];

        $info = EnumInfo::fromArray($array);

        $this->assertEquals('App\\Enums\\Color', $info->class);
        $this->assertEquals(['red', 'green', 'blue'], $info->values);
        $this->assertEquals(EnumBackingType::STRING, $info->backingType);
    }

    #[Test]
    public function it_creates_from_array_with_integer_type(): void
    {
        $array = [
            'class' => 'App\\Enums\\Level',
            'values' => [10, 20, 30],
            'type' => 'int',
        ];

        $info = EnumInfo::fromArray($array);

        $this->assertEquals(EnumBackingType::INTEGER, $info->backingType);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [
            'class' => 'App\\Enums\\Simple',
        ];

        $info = EnumInfo::fromArray($array);

        $this->assertEquals('App\\Enums\\Simple', $info->class);
        $this->assertEquals([], $info->values);
        $this->assertEquals(EnumBackingType::STRING, $info->backingType);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['draft', 'published'],
            backingType: EnumBackingType::STRING,
        );

        $array = $info->toArray();

        $this->assertEquals([
            'class' => 'App\\Enums\\Status',
            'values' => ['draft', 'published'],
            'type' => 'string',
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_with_integer_type(): void
    {
        $info = new EnumInfo(
            class: 'App\\Enums\\Priority',
            values: [1, 2, 3],
            backingType: EnumBackingType::INTEGER,
        );

        $array = $info->toArray();

        $this->assertEquals('int', $array['type']);
    }

    #[Test]
    public function it_checks_if_string_backed(): void
    {
        $stringBacked = new EnumInfo('App\\Enums\\A', [], EnumBackingType::STRING);
        $intBacked = new EnumInfo('App\\Enums\\B', [], EnumBackingType::INTEGER);

        $this->assertTrue($stringBacked->isStringBacked());
        $this->assertFalse($intBacked->isStringBacked());
    }

    #[Test]
    public function it_checks_if_integer_backed(): void
    {
        $stringBacked = new EnumInfo('App\\Enums\\A', [], EnumBackingType::STRING);
        $intBacked = new EnumInfo('App\\Enums\\B', [], EnumBackingType::INTEGER);

        $this->assertFalse($stringBacked->isIntegerBacked());
        $this->assertTrue($intBacked->isIntegerBacked());
    }

    #[Test]
    public function it_gets_short_class_name(): void
    {
        $info = new EnumInfo('App\\Enums\\UserStatus', [], EnumBackingType::STRING);

        $this->assertEquals('UserStatus', $info->getShortClassName());
    }

    #[Test]
    public function it_gets_short_class_name_for_root_class(): void
    {
        $info = new EnumInfo('Status', [], EnumBackingType::STRING);

        $this->assertEquals('Status', $info->getShortClassName());
    }

    #[Test]
    public function it_checks_if_has_values(): void
    {
        $withValues = new EnumInfo('App\\Enums\\A', ['a', 'b'], EnumBackingType::STRING);
        $withoutValues = new EnumInfo('App\\Enums\\B', [], EnumBackingType::STRING);

        $this->assertTrue($withValues->hasValues());
        $this->assertFalse($withoutValues->hasValues());
    }

    #[Test]
    public function it_counts_values(): void
    {
        $info = new EnumInfo('App\\Enums\\A', ['one', 'two', 'three'], EnumBackingType::STRING);

        $this->assertEquals(3, $info->count());
    }

    #[Test]
    public function it_gets_openapi_type(): void
    {
        $stringBacked = new EnumInfo('App\\Enums\\A', [], EnumBackingType::STRING);
        $intBacked = new EnumInfo('App\\Enums\\B', [], EnumBackingType::INTEGER);

        $this->assertEquals('string', $stringBacked->getOpenApiType());
        $this->assertEquals('integer', $intBacked->getOpenApiType());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['pending', 'active', 'completed'],
            backingType: EnumBackingType::STRING,
        );

        $restored = EnumInfo::fromArray($original->toArray());

        $this->assertEquals($original->class, $restored->class);
        $this->assertEquals($original->values, $restored->values);
        $this->assertEquals($original->backingType, $restored->backingType);
    }

    #[Test]
    public function it_survives_serialization_round_trip_with_integer(): void
    {
        $original = new EnumInfo(
            class: 'App\\Enums\\Priority',
            values: [1, 2, 3],
            backingType: EnumBackingType::INTEGER,
        );

        $restored = EnumInfo::fromArray($original->toArray());

        $this->assertEquals($original->class, $restored->class);
        $this->assertEquals($original->values, $restored->values);
        $this->assertEquals($original->backingType, $restored->backingType);
    }

    #[Test]
    public function it_defaults_to_string_type_when_invalid_type_provided(): void
    {
        $array = [
            'class' => 'App\\Enums\\Test',
            'values' => ['a', 'b'],
            'type' => 'invalid_type',
        ];

        $info = EnumInfo::fromArray($array);

        $this->assertEquals(EnumBackingType::STRING, $info->backingType);
    }

    #[Test]
    public function it_handles_empty_class_name(): void
    {
        $info = new EnumInfo('', [], EnumBackingType::STRING);

        $this->assertEquals('', $info->class);
        $this->assertEquals('', $info->getShortClassName());
    }

    #[Test]
    public function it_counts_zero_values(): void
    {
        $info = new EnumInfo('App\\Enums\\Empty', [], EnumBackingType::STRING);

        $this->assertEquals(0, $info->count());
        $this->assertFalse($info->hasValues());
    }
}
