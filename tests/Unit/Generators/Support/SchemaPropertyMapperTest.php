<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Generators\Support;

use LaravelSpectrum\DTO\EnumBackingType;
use LaravelSpectrum\DTO\EnumInfo;
use LaravelSpectrum\Generators\Support\SchemaPropertyMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SchemaPropertyMapperTest extends TestCase
{
    private SchemaPropertyMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new SchemaPropertyMapper;
    }

    #[Test]
    public function it_maps_type_with_default(): void
    {
        $source = ['type' => 'integer'];
        $result = $this->mapper->mapType($source);

        $this->assertEquals('integer', $result['type']);
    }

    #[Test]
    public function it_maps_type_with_custom_default(): void
    {
        $source = [];
        $result = $this->mapper->mapType($source, [], 'object');

        $this->assertEquals('object', $result['type']);
    }

    #[Test]
    public function it_maps_type_uses_string_as_default(): void
    {
        $source = [];
        $result = $this->mapper->mapType($source);

        $this->assertEquals('string', $result['type']);
    }

    #[Test]
    public function it_maps_simple_properties(): void
    {
        $source = [
            'description' => 'User name',
            'example' => 'John Doe',
            'format' => 'email',
            'pattern' => '^[a-z]+$',
            'default' => 'unknown',
        ];

        $result = $this->mapper->mapSimpleProperties($source);

        $this->assertEquals('User name', $result['description']);
        $this->assertEquals('John Doe', $result['example']);
        $this->assertEquals('email', $result['format']);
        $this->assertEquals('^[a-z]+$', $result['pattern']);
        $this->assertEquals('unknown', $result['default']);
    }

    #[Test]
    public function it_maps_simple_properties_only_when_set(): void
    {
        $source = [
            'description' => 'Test description',
            'other_property' => 'ignored',
        ];

        $result = $this->mapper->mapSimpleProperties($source);

        $this->assertEquals('Test description', $result['description']);
        $this->assertArrayNotHasKey('example', $result);
        $this->assertArrayNotHasKey('format', $result);
        $this->assertArrayNotHasKey('other_property', $result);
    }

    #[Test]
    public function it_maps_numeric_constraints(): void
    {
        $source = [
            'minimum' => 0,
            'maximum' => 100,
            'exclusiveMinimum' => 1,
            'exclusiveMaximum' => 99,
            'multipleOf' => 5,
        ];

        $result = $this->mapper->mapConstraints($source);

        $this->assertEquals(0, $result['minimum']);
        $this->assertEquals(100, $result['maximum']);
        $this->assertEquals(1, $result['exclusiveMinimum']);
        $this->assertEquals(99, $result['exclusiveMaximum']);
        $this->assertEquals(5, $result['multipleOf']);
    }

    #[Test]
    public function it_maps_string_constraints(): void
    {
        $source = [
            'minLength' => 1,
            'maxLength' => 255,
        ];

        $result = $this->mapper->mapConstraints($source);

        $this->assertEquals(1, $result['minLength']);
        $this->assertEquals(255, $result['maxLength']);
    }

    #[Test]
    public function it_maps_array_constraints(): void
    {
        $source = [
            'minItems' => 1,
            'maxItems' => 10,
            'uniqueItems' => true,
        ];

        $result = $this->mapper->mapConstraints($source);

        $this->assertEquals(1, $result['minItems']);
        $this->assertEquals(10, $result['maxItems']);
        $this->assertTrue($result['uniqueItems']);
    }

    #[Test]
    public function it_maps_simple_enum_array(): void
    {
        $source = [
            'enum' => ['active', 'inactive', 'pending'],
        ];

        $result = $this->mapper->mapEnum($source);

        $this->assertEquals(['active', 'inactive', 'pending'], $result['enum']);
    }

    #[Test]
    public function it_maps_structured_enum_from_enum_analyzer(): void
    {
        $source = [
            'enum' => [
                'values' => ['active', 'inactive'],
                'type' => 'string',
            ],
        ];

        $result = $this->mapper->mapEnum($source);

        $this->assertEquals(['active', 'inactive'], $result['enum']);
        $this->assertEquals('string', $result['type']);
    }

    #[Test]
    public function it_maps_structured_enum_with_integer_type(): void
    {
        $source = [
            'enum' => [
                'values' => [1, 2, 3],
                'type' => 'integer',
            ],
        ];

        $result = $this->mapper->mapEnum($source);

        $this->assertEquals([1, 2, 3], $result['enum']);
        $this->assertEquals('integer', $result['type']);
    }

    #[Test]
    public function it_returns_target_unchanged_when_no_enum(): void
    {
        $source = ['type' => 'string'];
        $target = ['existing' => 'value'];

        $result = $this->mapper->mapEnum($source, $target);

        $this->assertEquals(['existing' => 'value'], $result);
    }

    #[Test]
    public function it_maps_enum_info_dto_with_string_backing(): void
    {
        $enumInfo = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['active', 'inactive'],
            backingType: EnumBackingType::STRING,
        );
        $source = ['enum' => $enumInfo];

        $result = $this->mapper->mapEnum($source);

        $this->assertEquals(['active', 'inactive'], $result['enum']);
        $this->assertEquals('string', $result['type']);
    }

    #[Test]
    public function it_maps_enum_info_dto_with_integer_backing(): void
    {
        $enumInfo = new EnumInfo(
            class: 'App\\Enums\\Priority',
            values: [1, 2, 3],
            backingType: EnumBackingType::INTEGER,
        );
        $source = ['enum' => $enumInfo];

        $result = $this->mapper->mapEnum($source);

        $this->assertEquals([1, 2, 3], $result['enum']);
        $this->assertEquals('integer', $result['type']);
    }

    #[Test]
    public function it_maps_boolean_properties_when_true(): void
    {
        $source = [
            'nullable' => true,
            'readOnly' => true,
            'writeOnly' => true,
            'deprecated' => true,
        ];

        $result = $this->mapper->mapBooleanProperties($source);

        $this->assertTrue($result['nullable']);
        $this->assertTrue($result['readOnly']);
        $this->assertTrue($result['writeOnly']);
        $this->assertTrue($result['deprecated']);
    }

    #[Test]
    public function it_does_not_map_boolean_properties_when_false(): void
    {
        $source = [
            'nullable' => false,
            'readOnly' => false,
        ];

        $result = $this->mapper->mapBooleanProperties($source);

        $this->assertArrayNotHasKey('nullable', $result);
        $this->assertArrayNotHasKey('readOnly', $result);
    }

    #[Test]
    public function it_does_not_map_boolean_properties_when_not_set(): void
    {
        $source = ['type' => 'string'];

        $result = $this->mapper->mapBooleanProperties($source);

        $this->assertArrayNotHasKey('nullable', $result);
        $this->assertArrayNotHasKey('readOnly', $result);
        $this->assertArrayNotHasKey('writeOnly', $result);
        $this->assertArrayNotHasKey('deprecated', $result);
    }

    #[Test]
    public function it_maps_specific_properties(): void
    {
        $source = [
            'type' => 'string',
            'format' => 'email',
            'pattern' => '^[a-z]+$',
            'description' => 'ignored',
        ];

        $result = $this->mapper->mapSpecificProperties($source, ['format', 'pattern']);

        $this->assertEquals('email', $result['format']);
        $this->assertEquals('^[a-z]+$', $result['pattern']);
        $this->assertArrayNotHasKey('type', $result);
        $this->assertArrayNotHasKey('description', $result);
    }

    #[Test]
    public function it_merges_with_existing_target(): void
    {
        $source = ['description' => 'Test'];
        $target = ['type' => 'string'];

        $result = $this->mapper->mapSimpleProperties($source, $target);

        $this->assertEquals('string', $result['type']);
        $this->assertEquals('Test', $result['description']);
    }

    #[Test]
    public function it_maps_all_properties_combined(): void
    {
        $source = [
            'type' => 'integer',
            'description' => 'User age',
            'example' => 25,
            'minimum' => 0,
            'maximum' => 150,
            'enum' => [18, 25, 30, 40, 50],
            'nullable' => true,
            'deprecated' => true,
        ];

        $result = $this->mapper->mapAll($source);

        // Simple properties
        $this->assertEquals('User age', $result['description']);
        $this->assertEquals(25, $result['example']);

        // Constraints
        $this->assertEquals(0, $result['minimum']);
        $this->assertEquals(150, $result['maximum']);

        // Enum
        $this->assertEquals([18, 25, 30, 40, 50], $result['enum']);

        // Boolean properties
        $this->assertTrue($result['nullable']);
        $this->assertTrue($result['deprecated']);
    }

    #[Test]
    public function it_maps_all_with_existing_target(): void
    {
        $source = [
            'description' => 'Test',
            'minLength' => 1,
        ];
        $target = ['type' => 'string'];

        $result = $this->mapper->mapAll($source, $target);

        $this->assertEquals('string', $result['type']);
        $this->assertEquals('Test', $result['description']);
        $this->assertEquals(1, $result['minLength']);
    }

    #[Test]
    public function it_handles_empty_source(): void
    {
        $result = $this->mapper->mapAll([]);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_preserves_target_values_not_in_source(): void
    {
        $source = ['description' => 'New description'];
        $target = [
            'type' => 'string',
            'format' => 'email',
            'custom' => 'value',
        ];

        $result = $this->mapper->mapSimpleProperties($source, $target);

        $this->assertEquals('string', $result['type']);
        $this->assertEquals('email', $result['format']);
        $this->assertEquals('value', $result['custom']);
        $this->assertEquals('New description', $result['description']);
    }
}
