<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\TypeInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TypeInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_type_only(): void
    {
        $info = new TypeInfo(type: 'string');

        $this->assertEquals('string', $info->type);
        $this->assertNull($info->properties);
        $this->assertNull($info->format);
    }

    #[Test]
    public function it_can_be_constructed_with_all_properties(): void
    {
        $properties = [
            'name' => new TypeInfo(type: 'string'),
            'age' => new TypeInfo(type: 'integer'),
        ];

        $info = new TypeInfo(
            type: 'object',
            properties: $properties,
            format: null,
        );

        $this->assertEquals('object', $info->type);
        $this->assertEquals($properties, $info->properties);
        $this->assertNull($info->format);
    }

    #[Test]
    public function it_can_be_constructed_with_format(): void
    {
        $info = new TypeInfo(
            type: 'string',
            format: 'date-time',
        );

        $this->assertEquals('string', $info->type);
        $this->assertNull($info->properties);
        $this->assertEquals('date-time', $info->format);
    }

    #[Test]
    public function it_creates_string_type(): void
    {
        $info = TypeInfo::string();

        $this->assertEquals('string', $info->type);
        $this->assertNull($info->format);
    }

    #[Test]
    public function it_creates_integer_type(): void
    {
        $info = TypeInfo::integer();

        $this->assertEquals('integer', $info->type);
    }

    #[Test]
    public function it_creates_number_type(): void
    {
        $info = TypeInfo::number();

        $this->assertEquals('number', $info->type);
    }

    #[Test]
    public function it_creates_boolean_type(): void
    {
        $info = TypeInfo::boolean();

        $this->assertEquals('boolean', $info->type);
    }

    #[Test]
    public function it_creates_array_type(): void
    {
        $info = TypeInfo::array();

        $this->assertEquals('array', $info->type);
    }

    #[Test]
    public function it_creates_null_type(): void
    {
        $info = TypeInfo::null();

        $this->assertEquals('null', $info->type);
    }

    #[Test]
    public function it_creates_object_type_with_properties(): void
    {
        $properties = [
            'id' => TypeInfo::integer(),
            'name' => TypeInfo::string(),
        ];

        $info = TypeInfo::object($properties);

        $this->assertEquals('object', $info->type);
        $this->assertEquals($properties, $info->properties);
    }

    #[Test]
    public function it_creates_object_type_without_properties(): void
    {
        $info = TypeInfo::object();

        $this->assertEquals('object', $info->type);
        $this->assertEquals([], $info->properties);
    }

    #[Test]
    public function it_creates_string_with_format(): void
    {
        $info = TypeInfo::stringWithFormat('email');

        $this->assertEquals('string', $info->type);
        $this->assertEquals('email', $info->format);
    }

    #[Test]
    public function it_checks_if_is_object(): void
    {
        $object = TypeInfo::object();
        $string = TypeInfo::string();

        $this->assertTrue($object->isObject());
        $this->assertFalse($string->isObject());
    }

    #[Test]
    public function it_checks_if_is_array(): void
    {
        $array = TypeInfo::array();
        $string = TypeInfo::string();

        $this->assertTrue($array->isArray());
        $this->assertFalse($string->isArray());
    }

    #[Test]
    public function it_checks_if_is_scalar(): void
    {
        $string = TypeInfo::string();
        $integer = TypeInfo::integer();
        $number = TypeInfo::number();
        $boolean = TypeInfo::boolean();
        $object = TypeInfo::object();
        $array = TypeInfo::array();

        $this->assertTrue($string->isScalar());
        $this->assertTrue($integer->isScalar());
        $this->assertTrue($number->isScalar());
        $this->assertTrue($boolean->isScalar());
        $this->assertFalse($object->isScalar());
        $this->assertFalse($array->isScalar());
    }

    #[Test]
    public function it_checks_if_has_format(): void
    {
        $withFormat = TypeInfo::stringWithFormat('uuid');
        $withoutFormat = TypeInfo::string();

        $this->assertTrue($withFormat->hasFormat());
        $this->assertFalse($withoutFormat->hasFormat());
    }

    #[Test]
    public function it_checks_if_has_properties(): void
    {
        $withProperties = TypeInfo::object(['id' => TypeInfo::integer()]);
        $withoutProperties = TypeInfo::object();
        $string = TypeInfo::string();

        $this->assertTrue($withProperties->hasProperties());
        $this->assertFalse($withoutProperties->hasProperties());
        $this->assertFalse($string->hasProperties());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = TypeInfo::string();

        $array = $info->toArray();

        $this->assertEquals(['type' => 'string'], $array);
    }

    #[Test]
    public function it_converts_to_array_with_format(): void
    {
        $info = TypeInfo::stringWithFormat('date-time');

        $array = $info->toArray();

        $this->assertEquals([
            'type' => 'string',
            'format' => 'date-time',
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_with_properties(): void
    {
        $info = TypeInfo::object([
            'id' => TypeInfo::integer(),
            'name' => TypeInfo::string(),
        ]);

        $array = $info->toArray();

        $this->assertEquals([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ], $array);
    }

    #[Test]
    public function it_converts_nested_objects_to_array(): void
    {
        $info = TypeInfo::object([
            'user' => TypeInfo::object([
                'id' => TypeInfo::integer(),
                'email' => TypeInfo::stringWithFormat('email'),
            ]),
        ]);

        $array = $info->toArray();

        $this->assertEquals([
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                    ],
                ],
            ],
        ], $array);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = ['type' => 'string'];

        $info = TypeInfo::fromArray($data);

        $this->assertEquals('string', $info->type);
        $this->assertNull($info->properties);
        $this->assertNull($info->format);
    }

    #[Test]
    public function it_creates_from_array_with_format(): void
    {
        $data = [
            'type' => 'string',
            'format' => 'uuid',
        ];

        $info = TypeInfo::fromArray($data);

        $this->assertEquals('string', $info->type);
        $this->assertEquals('uuid', $info->format);
    }

    #[Test]
    public function it_creates_from_array_with_properties(): void
    {
        $data = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ];

        $info = TypeInfo::fromArray($data);

        $this->assertEquals('object', $info->type);
        $this->assertCount(2, $info->properties);
        $this->assertInstanceOf(TypeInfo::class, $info->properties['id']);
        $this->assertEquals('integer', $info->properties['id']->type);
        $this->assertInstanceOf(TypeInfo::class, $info->properties['name']);
        $this->assertEquals('string', $info->properties['name']->type);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = TypeInfo::object([
            'id' => TypeInfo::integer(),
            'email' => TypeInfo::stringWithFormat('email'),
            'profile' => TypeInfo::object([
                'name' => TypeInfo::string(),
            ]),
        ]);

        $restored = TypeInfo::fromArray($original->toArray());

        $this->assertEquals($original->type, $restored->type);
        $this->assertCount(3, $restored->properties);
        $this->assertEquals('integer', $restored->properties['id']->type);
        $this->assertEquals('string', $restored->properties['email']->type);
        $this->assertEquals('email', $restored->properties['email']->format);
        $this->assertEquals('object', $restored->properties['profile']->type);
        $this->assertEquals('string', $restored->properties['profile']->properties['name']->type);
    }

    #[Test]
    public function it_handles_empty_properties_in_from_array(): void
    {
        $data = [
            'type' => 'object',
            'properties' => [],
        ];

        $info = TypeInfo::fromArray($data);

        $this->assertEquals('object', $info->type);
        $this->assertEquals([], $info->properties);
    }

    #[Test]
    public function it_uses_string_as_default_type(): void
    {
        $info = TypeInfo::fromArray([]);

        $this->assertEquals('string', $info->type);
    }

    #[Test]
    public function it_creates_from_array_with_mixed_type_info_and_arrays(): void
    {
        $data = [
            'type' => 'object',
            'properties' => [
                'id' => TypeInfo::integer(),
                'name' => ['type' => 'string'],
            ],
        ];

        $info = TypeInfo::fromArray($data);

        $this->assertEquals('object', $info->type);
        $this->assertCount(2, $info->properties);
        $this->assertEquals('integer', $info->properties['id']->type);
        $this->assertEquals('string', $info->properties['name']->type);
    }

    #[Test]
    public function it_ignores_non_array_properties_in_from_array(): void
    {
        $data = [
            'type' => 'object',
            'properties' => 'invalid',
        ];

        $info = TypeInfo::fromArray($data);

        $this->assertEquals('object', $info->type);
        $this->assertNull($info->properties);
    }

    #[Test]
    public function it_checks_null_type_is_not_scalar(): void
    {
        $null = TypeInfo::null();

        $this->assertFalse($null->isScalar());
    }
}
