<?php

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\OpenApiSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiSchemaTest extends TestCase
{
    #[Test]
    public function it_creates_string_schema(): void
    {
        $schema = OpenApiSchema::string();

        $this->assertEquals('string', $schema->type);
        $this->assertNull($schema->default);
    }

    #[Test]
    public function it_creates_string_schema_with_default(): void
    {
        $schema = OpenApiSchema::string('hello');

        $this->assertEquals('string', $schema->type);
        $this->assertEquals('hello', $schema->default);
    }

    #[Test]
    public function it_creates_integer_schema(): void
    {
        $schema = OpenApiSchema::integer();

        $this->assertEquals('integer', $schema->type);
        $this->assertNull($schema->default);
    }

    #[Test]
    public function it_creates_integer_schema_with_default(): void
    {
        $schema = OpenApiSchema::integer(42);

        $this->assertEquals('integer', $schema->type);
        $this->assertEquals(42, $schema->default);
    }

    #[Test]
    public function it_creates_number_schema(): void
    {
        $schema = OpenApiSchema::number(3.14);

        $this->assertEquals('number', $schema->type);
        $this->assertEquals(3.14, $schema->default);
    }

    #[Test]
    public function it_creates_boolean_schema(): void
    {
        $schema = OpenApiSchema::boolean(true);

        $this->assertEquals('boolean', $schema->type);
        $this->assertTrue($schema->default);
    }

    #[Test]
    public function it_creates_string_array_schema(): void
    {
        $schema = OpenApiSchema::stringArray();

        $this->assertEquals('array', $schema->type);
        $this->assertNotNull($schema->items);
        $this->assertEquals('string', $schema->items->type);
    }

    #[Test]
    public function it_creates_string_array_schema_with_default(): void
    {
        $schema = OpenApiSchema::stringArray(['a', 'b', 'c']);

        $this->assertEquals('array', $schema->type);
        $this->assertEquals(['a', 'b', 'c'], $schema->default);
    }

    #[Test]
    public function it_creates_from_type_string(): void
    {
        $this->assertEquals('string', OpenApiSchema::fromType('string')->type);
        $this->assertEquals('integer', OpenApiSchema::fromType('integer')->type);
        $this->assertEquals('integer', OpenApiSchema::fromType('int')->type);
        $this->assertEquals('number', OpenApiSchema::fromType('number')->type);
        $this->assertEquals('number', OpenApiSchema::fromType('float')->type);
        $this->assertEquals('number', OpenApiSchema::fromType('double')->type);
        $this->assertEquals('boolean', OpenApiSchema::fromType('boolean')->type);
        $this->assertEquals('boolean', OpenApiSchema::fromType('bool')->type);
        $this->assertEquals('array', OpenApiSchema::fromType('array')->type);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'type' => 'integer',
            'format' => 'int32',
            'default' => 10,
            'minimum' => 1,
            'maximum' => 100,
        ];

        $schema = OpenApiSchema::fromArray($data);

        $this->assertEquals('integer', $schema->type);
        $this->assertEquals('int32', $schema->format);
        $this->assertEquals(10, $schema->default);
        $this->assertEquals(1, $schema->minimum);
        $this->assertEquals(100, $schema->maximum);
    }

    #[Test]
    public function it_creates_from_array_with_nested_items(): void
    {
        $data = [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ];

        $schema = OpenApiSchema::fromArray($data);

        $this->assertEquals('array', $schema->type);
        $this->assertNotNull($schema->items);
        $this->assertEquals('string', $schema->items->type);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $schema = new OpenApiSchema(
            type: 'integer',
            format: 'int32',
            default: 10,
            minimum: 1,
            maximum: 100,
        );

        $array = $schema->toArray();

        $this->assertEquals('integer', $array['type']);
        $this->assertEquals('int32', $array['format']);
        $this->assertEquals(10, $array['default']);
        $this->assertEquals(1, $array['minimum']);
        $this->assertEquals(100, $array['maximum']);
    }

    #[Test]
    public function it_converts_to_array_with_items(): void
    {
        $schema = OpenApiSchema::stringArray();

        $array = $schema->toArray();

        $this->assertEquals('array', $array['type']);
        $this->assertIsArray($array['items']);
        $this->assertEquals('string', $array['items']['type']);
    }

    #[Test]
    public function it_only_includes_non_null_values_in_array(): void
    {
        $schema = OpenApiSchema::string();

        $array = $schema->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayNotHasKey('format', $array);
        $this->assertArrayNotHasKey('default', $array);
        $this->assertArrayNotHasKey('enum', $array);
        $this->assertArrayNotHasKey('minimum', $array);
        $this->assertArrayNotHasKey('maximum', $array);
    }

    #[Test]
    public function it_creates_immutable_copy_with_enum(): void
    {
        $original = OpenApiSchema::string();
        $updated = $original->withEnum(['a', 'b', 'c']);

        $this->assertNull($original->enum);
        $this->assertEquals(['a', 'b', 'c'], $updated->enum);
        $this->assertEquals('string', $updated->type);
    }

    #[Test]
    public function it_creates_immutable_copy_with_constraints(): void
    {
        $original = OpenApiSchema::integer();
        $updated = $original->withConstraints(minimum: 1, maximum: 100);

        $this->assertNull($original->minimum);
        $this->assertNull($original->maximum);
        $this->assertEquals(1, $updated->minimum);
        $this->assertEquals(100, $updated->maximum);
    }

    #[Test]
    public function it_preserves_existing_constraints_when_partial_update(): void
    {
        $original = new OpenApiSchema(
            type: 'integer',
            minimum: 1,
            maximum: 100,
        );
        $updated = $original->withConstraints(maximum: 200);

        $this->assertEquals(1, $updated->minimum);
        $this->assertEquals(200, $updated->maximum);
    }

    #[Test]
    public function it_handles_string_length_constraints(): void
    {
        $schema = new OpenApiSchema(
            type: 'string',
            minLength: 1,
            maxLength: 255,
        );

        $this->assertEquals(1, $schema->minLength);
        $this->assertEquals(255, $schema->maxLength);

        $array = $schema->toArray();
        $this->assertEquals(1, $array['minLength']);
        $this->assertEquals(255, $array['maxLength']);
    }

    #[Test]
    public function it_handles_pattern_constraint(): void
    {
        $schema = new OpenApiSchema(
            type: 'string',
            pattern: '^[a-zA-Z0-9]+$',
        );

        $this->assertEquals('^[a-zA-Z0-9]+$', $schema->pattern);

        $array = $schema->toArray();
        $this->assertEquals('^[a-zA-Z0-9]+$', $array['pattern']);
    }

    #[Test]
    public function it_handles_nullable_property(): void
    {
        $schema = new OpenApiSchema(
            type: 'string',
            nullable: true,
        );

        $this->assertTrue($schema->nullable);

        $array = $schema->toArray();
        $this->assertTrue($array['nullable']);
    }

    #[Test]
    public function it_creates_from_array_with_string_constraints(): void
    {
        $data = [
            'type' => 'string',
            'minLength' => 5,
            'maxLength' => 100,
            'pattern' => '^[a-z]+$',
            'nullable' => true,
        ];

        $schema = OpenApiSchema::fromArray($data);

        $this->assertEquals(5, $schema->minLength);
        $this->assertEquals(100, $schema->maxLength);
        $this->assertEquals('^[a-z]+$', $schema->pattern);
        $this->assertTrue($schema->nullable);
    }

    #[Test]
    public function it_creates_from_array_with_items_as_schema_object(): void
    {
        $itemsSchema = OpenApiSchema::integer();

        $data = [
            'type' => 'array',
            'items' => $itemsSchema,
        ];

        $schema = OpenApiSchema::fromArray($data);

        $this->assertEquals('array', $schema->type);
        $this->assertNotNull($schema->items);
        $this->assertEquals('integer', $schema->items->type);
    }
}
