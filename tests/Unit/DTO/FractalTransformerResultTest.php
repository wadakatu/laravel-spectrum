<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\FractalTransformerResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FractalTransformerResultTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_all_properties(): void
    {
        $result = new FractalTransformerResult(
            properties: [
                'id' => ['type' => 'integer', 'example' => 1, 'nullable' => false],
                'name' => ['type' => 'string', 'example' => 'Test', 'nullable' => false],
            ],
            availableIncludes: [
                'posts' => ['type' => 'object', 'transformer' => 'PostTransformer', 'collection' => true],
            ],
            defaultIncludes: ['profile'],
            meta: ['version' => '1.0'],
        );

        $this->assertEquals('fractal', $result->type);
        $this->assertCount(2, $result->properties);
        $this->assertCount(1, $result->availableIncludes);
        $this->assertCount(1, $result->defaultIncludes);
        $this->assertEquals(['version' => '1.0'], $result->meta);
    }

    #[Test]
    public function it_can_be_created_with_empty_values(): void
    {
        $result = FractalTransformerResult::empty();

        $this->assertEquals('fractal', $result->type);
        $this->assertEmpty($result->properties);
        $this->assertEmpty($result->availableIncludes);
        $this->assertEmpty($result->defaultIncludes);
        $this->assertEmpty($result->meta);
        $this->assertFalse($result->isValid);
    }

    #[Test]
    public function it_can_create_from_array(): void
    {
        $data = [
            'type' => 'fractal',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1, 'nullable' => false],
            ],
            'availableIncludes' => [
                'comments' => ['type' => 'object', 'transformer' => null, 'collection' => true],
            ],
            'defaultIncludes' => ['author'],
            'meta' => [],
        ];

        $result = FractalTransformerResult::fromArray($data);

        $this->assertEquals('fractal', $result->type);
        $this->assertCount(1, $result->properties);
        $this->assertEquals('integer', $result->properties['id']['type']);
        $this->assertCount(1, $result->availableIncludes);
        $this->assertTrue($result->availableIncludes['comments']['collection']);
        $this->assertEquals(['author'], $result->defaultIncludes);
    }

    #[Test]
    public function it_can_convert_to_array(): void
    {
        $result = new FractalTransformerResult(
            properties: [
                'title' => ['type' => 'string', 'example' => 'Hello', 'nullable' => false],
            ],
            availableIncludes: [
                'tags' => ['type' => 'object', 'transformer' => 'TagTransformer', 'collection' => true],
            ],
            defaultIncludes: ['category'],
            meta: ['cache' => true],
        );

        $array = $result->toArray();

        $this->assertEquals('fractal', $array['type']);
        $this->assertArrayHasKey('properties', $array);
        $this->assertArrayHasKey('availableIncludes', $array);
        $this->assertArrayHasKey('defaultIncludes', $array);
        $this->assertArrayHasKey('meta', $array);
        $this->assertEquals('string', $array['properties']['title']['type']);
    }

    #[Test]
    public function it_reports_empty_when_no_properties(): void
    {
        $result = FractalTransformerResult::empty();

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_reports_not_empty_when_has_properties(): void
    {
        $result = new FractalTransformerResult(
            properties: ['id' => ['type' => 'integer', 'example' => 1, 'nullable' => false]],
        );

        $this->assertFalse($result->isEmpty());
    }

    #[Test]
    public function it_can_check_for_available_includes(): void
    {
        $result = new FractalTransformerResult(
            properties: [],
            availableIncludes: [
                'posts' => ['type' => 'object', 'transformer' => 'PostTransformer', 'collection' => true],
            ],
        );

        $this->assertTrue($result->hasAvailableIncludes());
        $this->assertTrue($result->hasInclude('posts'));
        $this->assertFalse($result->hasInclude('comments'));
    }

    #[Test]
    public function it_returns_false_when_no_available_includes(): void
    {
        $result = new FractalTransformerResult(
            properties: ['id' => ['type' => 'integer', 'example' => 1, 'nullable' => false]],
            availableIncludes: [],
        );

        $this->assertFalse($result->hasAvailableIncludes());
        $this->assertFalse($result->hasInclude('posts'));
    }

    #[Test]
    public function it_can_check_for_default_includes(): void
    {
        $result = new FractalTransformerResult(
            properties: [],
            defaultIncludes: ['author', 'category'],
        );

        $this->assertTrue($result->hasDefaultIncludes());
        $this->assertTrue($result->isDefaultInclude('author'));
        $this->assertFalse($result->isDefaultInclude('tags'));
    }

    #[Test]
    public function it_returns_false_when_no_default_includes(): void
    {
        $result = new FractalTransformerResult(
            properties: ['id' => ['type' => 'integer', 'example' => 1, 'nullable' => false]],
            defaultIncludes: [],
        );

        $this->assertFalse($result->hasDefaultIncludes());
        $this->assertFalse($result->isDefaultInclude('author'));
    }

    #[Test]
    public function it_can_get_property_by_name(): void
    {
        $result = new FractalTransformerResult(
            properties: [
                'id' => ['type' => 'integer', 'example' => 1, 'nullable' => false],
                'name' => ['type' => 'string', 'example' => 'Test', 'nullable' => true],
            ],
        );

        $property = $result->getProperty('id');
        $this->assertNotNull($property);
        $this->assertEquals('integer', $property['type']);

        $this->assertNull($result->getProperty('unknown'));
    }

    #[Test]
    public function it_can_get_include_by_name(): void
    {
        $result = new FractalTransformerResult(
            properties: [],
            availableIncludes: [
                'posts' => ['type' => 'object', 'transformer' => 'PostTransformer', 'collection' => true],
            ],
        );

        $include = $result->getInclude('posts');
        $this->assertNotNull($include);
        $this->assertEquals('PostTransformer', $include['transformer']);

        $this->assertNull($result->getInclude('unknown'));
    }

    #[Test]
    public function it_can_get_property_names(): void
    {
        $result = new FractalTransformerResult(
            properties: [
                'id' => ['type' => 'integer', 'example' => 1, 'nullable' => false],
                'name' => ['type' => 'string', 'example' => 'Test', 'nullable' => false],
                'email' => ['type' => 'string', 'example' => 'test@example.com', 'nullable' => false],
            ],
        );

        $names = $result->getPropertyNames();

        $this->assertEquals(['id', 'name', 'email'], $names);
    }

    #[Test]
    public function it_can_count_properties(): void
    {
        $result = new FractalTransformerResult(
            properties: [
                'id' => ['type' => 'integer', 'example' => 1, 'nullable' => false],
                'name' => ['type' => 'string', 'example' => 'Test', 'nullable' => false],
            ],
        );

        $this->assertEquals(2, $result->count());
    }

    #[Test]
    public function it_handles_nested_properties(): void
    {
        $result = new FractalTransformerResult(
            properties: [
                'address' => [
                    'type' => 'object',
                    'example' => null,
                    'nullable' => false,
                    'properties' => [
                        'street' => ['type' => 'string', 'example' => '123 Main St', 'nullable' => false],
                        'city' => ['type' => 'string', 'example' => 'New York', 'nullable' => false],
                    ],
                ],
            ],
        );

        $this->assertFalse($result->isEmpty());
        $address = $result->getProperty('address');
        $this->assertEquals('object', $address['type']);
        $this->assertArrayHasKey('properties', $address);
        $this->assertCount(2, $address['properties']);
    }

    #[Test]
    public function from_array_handles_missing_keys(): void
    {
        $data = [
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1, 'nullable' => false],
            ],
        ];

        $result = FractalTransformerResult::fromArray($data);

        $this->assertEquals('fractal', $result->type);
        $this->assertCount(1, $result->properties);
        $this->assertEmpty($result->availableIncludes);
        $this->assertEmpty($result->defaultIncludes);
        $this->assertEmpty($result->meta);
        $this->assertTrue($result->isValid);
    }

    #[Test]
    public function from_array_returns_invalid_result_for_empty_array(): void
    {
        $result = FractalTransformerResult::fromArray([]);

        $this->assertFalse($result->isValid);
        $this->assertEmpty($result->toArray());
    }

    #[Test]
    public function to_array_returns_empty_for_invalid_result(): void
    {
        $result = FractalTransformerResult::empty();

        $this->assertEmpty($result->toArray());
    }

    #[Test]
    public function to_array_preserves_structure(): void
    {
        $original = [
            'type' => 'fractal',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1, 'nullable' => false],
            ],
            'availableIncludes' => [
                'posts' => ['type' => 'object', 'transformer' => 'PostTransformer', 'collection' => true],
            ],
            'defaultIncludes' => ['author'],
            'meta' => [],
        ];

        $result = FractalTransformerResult::fromArray($original);
        $array = $result->toArray();

        $this->assertEquals($original['type'], $array['type']);
        $this->assertEquals($original['properties'], $array['properties']);
        $this->assertEquals($original['availableIncludes'], $array['availableIncludes']);
        $this->assertEquals($original['defaultIncludes'], $array['defaultIncludes']);
        $this->assertEquals($original['meta'], $array['meta']);
    }
}
