<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ResourceFieldInfo;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ResourceFieldInfoTest extends TestCase
{
    #[Test]
    public function it_creates_basic_field_info(): void
    {
        $info = ResourceFieldInfo::basic('string');

        $this->assertEquals('string', $info->type);
        $this->assertFalse($info->nullable);
        $this->assertNull($info->source);
        $this->assertNull($info->property);
        $this->assertNull($info->example);
        $this->assertNull($info->format);
        $this->assertFalse($info->conditional);
        $this->assertNull($info->condition);
        $this->assertNull($info->relation);
        $this->assertFalse($info->hasTransformation);
        $this->assertNull($info->properties);
        $this->assertNull($info->items);
        $this->assertNull($info->resource);
        $this->assertNull($info->expression);
    }

    #[Test]
    public function it_creates_mixed_type(): void
    {
        $info = ResourceFieldInfo::mixed();

        $this->assertEquals('mixed', $info->type);
        $this->assertFalse($info->nullable);
    }

    #[Test]
    public function it_creates_string_type(): void
    {
        $info = ResourceFieldInfo::string();

        $this->assertEquals('string', $info->type);
        $this->assertFalse($info->nullable);
    }

    #[Test]
    public function it_creates_integer_type(): void
    {
        $info = ResourceFieldInfo::integer();

        $this->assertEquals('integer', $info->type);
        $this->assertFalse($info->nullable);
    }

    #[Test]
    public function it_creates_number_type(): void
    {
        $info = ResourceFieldInfo::number();

        $this->assertEquals('number', $info->type);
        $this->assertFalse($info->nullable);
    }

    #[Test]
    public function it_creates_boolean_type(): void
    {
        $info = ResourceFieldInfo::boolean();

        $this->assertEquals('boolean', $info->type);
        $this->assertFalse($info->nullable);
    }

    #[Test]
    public function it_creates_array_type(): void
    {
        $info = ResourceFieldInfo::array();

        $this->assertEquals('array', $info->type);
        $this->assertFalse($info->nullable);
    }

    #[Test]
    public function it_creates_object_type(): void
    {
        $info = ResourceFieldInfo::object();

        $this->assertEquals('object', $info->type);
        $this->assertFalse($info->nullable);
    }

    #[Test]
    public function it_creates_property_field_info(): void
    {
        $info = ResourceFieldInfo::property('email', 'string', 'user@example.com');

        $this->assertEquals('string', $info->type);
        $this->assertEquals('property', $info->source);
        $this->assertEquals('email', $info->property);
        $this->assertEquals('user@example.com', $info->example);
        $this->assertFalse($info->nullable);
    }

    #[Test]
    public function it_creates_enum_field_info(): void
    {
        $info = ResourceFieldInfo::enum();

        $this->assertEquals('string', $info->type);
        $this->assertEquals('enum', $info->source);
    }

    #[Test]
    public function it_creates_nullable_enum_field_info(): void
    {
        $info = ResourceFieldInfo::enum(nullable: true);

        $this->assertEquals('string', $info->type);
        $this->assertEquals('enum', $info->source);
        $this->assertTrue($info->nullable);
    }

    #[Test]
    public function it_creates_conditional_field_info(): void
    {
        $info = ResourceFieldInfo::conditional('when', 'string');

        $this->assertEquals('string', $info->type);
        $this->assertTrue($info->conditional);
        $this->assertEquals('when', $info->condition);
    }

    #[Test]
    public function it_creates_when_loaded_field_info(): void
    {
        $info = ResourceFieldInfo::whenLoaded('comments', 'array', true);

        $this->assertEquals('array', $info->type);
        $this->assertTrue($info->conditional);
        $this->assertEquals('whenLoaded', $info->condition);
        $this->assertEquals('comments', $info->relation);
        $this->assertTrue($info->hasTransformation);
    }

    #[Test]
    public function it_creates_resource_collection_field_info(): void
    {
        $items = ['type' => 'object', 'resource' => 'UserResource'];
        $info = ResourceFieldInfo::resourceCollection('UserResource', $items);

        $this->assertEquals('array', $info->type);
        $this->assertEquals('UserResource', $info->resource);
        $this->assertEquals($items, $info->items);
    }

    #[Test]
    public function it_creates_nested_resource_field_info(): void
    {
        $info = ResourceFieldInfo::nestedResource('ProfileResource');

        $this->assertEquals('object', $info->type);
        $this->assertEquals('ProfileResource', $info->resource);
    }

    #[Test]
    public function it_creates_date_time_field_info(): void
    {
        $example = date('Y-m-d H:i:s');
        $info = ResourceFieldInfo::dateTime($example);

        $this->assertEquals('string', $info->type);
        $this->assertEquals('date-time', $info->format);
        $this->assertEquals($example, $info->example);
    }

    #[Test]
    public function it_creates_with_expression(): void
    {
        $info = ResourceFieldInfo::withExpression('$this->foo ? $this->bar : null');

        $this->assertEquals('mixed', $info->type);
        $this->assertEquals('$this->foo ? $this->bar : null', $info->expression);
    }

    #[Test]
    public function it_creates_with_nullable(): void
    {
        $info = ResourceFieldInfo::mixed()->withNullable();

        $this->assertEquals('mixed', $info->type);
        $this->assertTrue($info->nullable);
    }

    #[Test]
    public function it_identifies_type_checks_correctly(): void
    {
        $this->assertTrue(ResourceFieldInfo::mixed()->isMixed());
        $this->assertTrue(ResourceFieldInfo::string()->isString());
        $this->assertTrue(ResourceFieldInfo::integer()->isInteger());
        $this->assertTrue(ResourceFieldInfo::number()->isNumber());
        $this->assertTrue(ResourceFieldInfo::boolean()->isBoolean());
        $this->assertTrue(ResourceFieldInfo::array()->isArray());
        $this->assertTrue(ResourceFieldInfo::object()->isObject());
    }

    #[Test]
    public function it_identifies_scalar_types(): void
    {
        $this->assertTrue(ResourceFieldInfo::string()->isScalar());
        $this->assertTrue(ResourceFieldInfo::integer()->isScalar());
        $this->assertTrue(ResourceFieldInfo::number()->isScalar());
        $this->assertTrue(ResourceFieldInfo::boolean()->isScalar());

        $this->assertFalse(ResourceFieldInfo::array()->isScalar());
        $this->assertFalse(ResourceFieldInfo::object()->isScalar());
        $this->assertFalse(ResourceFieldInfo::mixed()->isScalar());
    }

    #[Test]
    public function it_identifies_nullable_fields(): void
    {
        $nullable = ResourceFieldInfo::mixed()->withNullable();
        $nonNullable = ResourceFieldInfo::string();

        $this->assertTrue($nullable->isNullable());
        $this->assertFalse($nonNullable->isNullable());
    }

    #[Test]
    public function it_identifies_conditional_fields(): void
    {
        $conditional = ResourceFieldInfo::conditional('when', 'string');
        $nonConditional = ResourceFieldInfo::string();

        $this->assertTrue($conditional->isConditional());
        $this->assertFalse($nonConditional->isConditional());
    }

    #[Test]
    public function it_identifies_resource_reference(): void
    {
        $withResource = ResourceFieldInfo::nestedResource('UserResource');
        $withoutResource = ResourceFieldInfo::object();

        $this->assertTrue($withResource->isResourceReference());
        $this->assertFalse($withoutResource->isResourceReference());
    }

    #[Test]
    public function it_identifies_fields_with_format(): void
    {
        $withFormat = ResourceFieldInfo::dateTime();
        $withoutFormat = ResourceFieldInfo::string();

        $this->assertTrue($withFormat->hasFormat());
        $this->assertFalse($withoutFormat->hasFormat());
    }

    #[Test]
    public function it_identifies_fields_with_properties(): void
    {
        $withProperties = ResourceFieldInfo::object()->withProperties(['id' => ['type' => 'integer']]);
        $withoutProperties = ResourceFieldInfo::object();

        $this->assertTrue($withProperties->hasProperties());
        $this->assertFalse($withoutProperties->hasProperties());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = ResourceFieldInfo::property('name', 'string', 'John Doe');
        $array = $info->toArray();

        $this->assertEquals('string', $array['type']);
        $this->assertFalse($array['nullable']);
        $this->assertEquals('property', $array['source']);
        $this->assertEquals('name', $array['property']);
        $this->assertEquals('John Doe', $array['example']);
    }

    #[Test]
    public function it_converts_conditional_to_array(): void
    {
        $info = ResourceFieldInfo::whenLoaded('comments', 'array', true);
        $array = $info->toArray();

        $this->assertEquals('array', $array['type']);
        $this->assertTrue($array['conditional']);
        $this->assertEquals('whenLoaded', $array['condition']);
        $this->assertEquals('comments', $array['relation']);
        $this->assertTrue($array['hasTransformation']);
    }

    #[Test]
    public function it_omits_null_values_in_array(): void
    {
        $info = ResourceFieldInfo::string();
        $array = $info->toArray();

        $this->assertArrayNotHasKey('source', $array);
        $this->assertArrayNotHasKey('property', $array);
        $this->assertArrayNotHasKey('example', $array);
        $this->assertArrayNotHasKey('format', $array);
        $this->assertArrayNotHasKey('condition', $array);
        $this->assertArrayNotHasKey('relation', $array);
        $this->assertArrayNotHasKey('properties', $array);
        $this->assertArrayNotHasKey('items', $array);
        $this->assertArrayNotHasKey('resource', $array);
        $this->assertArrayNotHasKey('expression', $array);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'type' => 'string',
            'nullable' => true,
            'source' => 'property',
            'property' => 'email',
            'example' => 'user@example.com',
        ];

        $info = ResourceFieldInfo::fromArray($data);

        $this->assertEquals('string', $info->type);
        $this->assertTrue($info->nullable);
        $this->assertEquals('property', $info->source);
        $this->assertEquals('email', $info->property);
        $this->assertEquals('user@example.com', $info->example);
    }

    #[Test]
    public function it_creates_from_array_with_conditional(): void
    {
        $data = [
            'type' => 'array',
            'conditional' => true,
            'condition' => 'whenLoaded',
            'relation' => 'comments',
            'hasTransformation' => true,
        ];

        $info = ResourceFieldInfo::fromArray($data);

        $this->assertEquals('array', $info->type);
        $this->assertTrue($info->conditional);
        $this->assertEquals('whenLoaded', $info->condition);
        $this->assertEquals('comments', $info->relation);
        $this->assertTrue($info->hasTransformation);
    }

    #[Test]
    public function it_creates_from_minimal_array(): void
    {
        $data = ['type' => 'integer'];

        $info = ResourceFieldInfo::fromArray($data);

        $this->assertEquals('integer', $info->type);
        $this->assertFalse($info->nullable);
        $this->assertNull($info->source);
    }

    #[Test]
    public function it_creates_from_empty_array_with_defaults(): void
    {
        $info = ResourceFieldInfo::fromArray([]);

        $this->assertEquals('mixed', $info->type);
        $this->assertFalse($info->nullable);
    }

    #[Test]
    public function it_performs_roundtrip_conversion(): void
    {
        $original = ResourceFieldInfo::whenLoaded('users', 'array', true);
        $array = $original->toArray();
        $restored = ResourceFieldInfo::fromArray($array);

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->nullable, $restored->nullable);
        $this->assertEquals($original->conditional, $restored->conditional);
        $this->assertEquals($original->condition, $restored->condition);
        $this->assertEquals($original->relation, $restored->relation);
        $this->assertEquals($original->hasTransformation, $restored->hasTransformation);
    }

    #[Test]
    public function it_performs_roundtrip_for_resource_collection(): void
    {
        $items = ['type' => 'object', 'resource' => 'CommentResource'];
        $original = ResourceFieldInfo::resourceCollection('CommentResource', $items);
        $array = $original->toArray();
        $restored = ResourceFieldInfo::fromArray($array);

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->resource, $restored->resource);
        $this->assertEquals($original->items, $restored->items);
    }

    #[Test]
    public function it_creates_with_properties(): void
    {
        $properties = [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
        ];

        $info = ResourceFieldInfo::object()->withProperties($properties);

        $this->assertEquals('object', $info->type);
        $this->assertEquals($properties, $info->properties);
    }

    #[Test]
    public function it_creates_with_items(): void
    {
        $items = ['type' => 'string'];

        $info = ResourceFieldInfo::array()->withItems($items);

        $this->assertEquals('array', $info->type);
        $this->assertEquals($items, $info->items);
    }

    #[Test]
    public function it_creates_with_example(): void
    {
        $info = ResourceFieldInfo::string()->withExample('Test value');

        $this->assertEquals('string', $info->type);
        $this->assertEquals('Test value', $info->example);
    }
}
