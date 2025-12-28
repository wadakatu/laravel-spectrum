<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ResourceInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
            with: ['meta' => ['type' => 'object']],
            hasExamples: true,
            customExample: ['id' => 1, 'name' => 'John'],
            customExamples: [['id' => 1], ['id' => 2]],
            isCollection: false,
        );

        $this->assertCount(2, $info->properties);
        $this->assertCount(1, $info->with);
        $this->assertTrue($info->hasExamples);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $info->customExample);
        $this->assertCount(2, $info->customExamples);
        $this->assertFalse($info->isCollection);
    }

    #[Test]
    public function it_can_be_constructed_with_defaults(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => ['type' => 'integer']],
        );

        $this->assertCount(1, $info->properties);
        $this->assertEquals([], $info->with);
        $this->assertFalse($info->hasExamples);
        $this->assertNull($info->customExample);
        $this->assertNull($info->customExamples);
        $this->assertFalse($info->isCollection);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'properties' => ['id' => ['type' => 'integer']],
            'with' => ['links' => ['type' => 'object']],
            'hasExamples' => true,
            'customExample' => ['id' => 42],
            'customExamples' => [['id' => 1], ['id' => 2]],
            'isCollection' => true,
        ];

        $info = ResourceInfo::fromArray($array);

        $this->assertCount(1, $info->properties);
        $this->assertCount(1, $info->with);
        $this->assertTrue($info->hasExamples);
        $this->assertEquals(['id' => 42], $info->customExample);
        $this->assertCount(2, $info->customExamples);
        $this->assertTrue($info->isCollection);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [];

        $info = ResourceInfo::fromArray($array);

        $this->assertEquals([], $info->properties);
        $this->assertEquals([], $info->with);
        $this->assertFalse($info->hasExamples);
        $this->assertNull($info->customExample);
        $this->assertNull($info->customExamples);
        $this->assertFalse($info->isCollection);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => ['type' => 'integer']],
            with: ['meta' => []],
            hasExamples: true,
            customExample: ['id' => 1],
            customExamples: [['id' => 1]],
            isCollection: true,
        );

        $array = $info->toArray();

        $this->assertArrayHasKey('properties', $array);
        $this->assertArrayHasKey('with', $array);
        $this->assertArrayHasKey('hasExamples', $array);
        $this->assertArrayHasKey('customExample', $array);
        $this->assertArrayHasKey('customExamples', $array);
        $this->assertArrayHasKey('isCollection', $array);
        $this->assertTrue($array['isCollection']);
    }

    #[Test]
    public function it_converts_to_array_without_optional_fields_when_empty(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => []],
        );

        $array = $info->toArray();

        $this->assertArrayHasKey('properties', $array);
        $this->assertArrayNotHasKey('with', $array);
        $this->assertArrayNotHasKey('hasExamples', $array);
        $this->assertArrayNotHasKey('customExample', $array);
        $this->assertArrayNotHasKey('customExamples', $array);
        $this->assertArrayNotHasKey('isCollection', $array);
    }

    #[Test]
    public function it_creates_empty_instance(): void
    {
        $info = ResourceInfo::empty();

        $this->assertEquals([], $info->properties);
        $this->assertEquals([], $info->with);
        $this->assertFalse($info->hasExamples);
        $this->assertNull($info->customExample);
        $this->assertNull($info->customExamples);
        $this->assertFalse($info->isCollection);
    }

    #[Test]
    public function it_checks_if_empty(): void
    {
        $empty = ResourceInfo::empty();
        $notEmpty = new ResourceInfo(properties: ['id' => []]);

        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($notEmpty->isEmpty());
    }

    #[Test]
    public function it_checks_if_has_custom_example(): void
    {
        $withExample = new ResourceInfo(properties: [], customExample: ['id' => 1]);
        $withoutExample = new ResourceInfo(properties: []);

        $this->assertTrue($withExample->hasCustomExample());
        $this->assertFalse($withoutExample->hasCustomExample());
    }

    #[Test]
    public function it_checks_if_has_custom_examples(): void
    {
        $withExamples = new ResourceInfo(properties: [], customExamples: [['id' => 1]]);
        $withoutExamples = new ResourceInfo(properties: []);

        $this->assertTrue($withExamples->hasCustomExamples());
        $this->assertFalse($withoutExamples->hasCustomExamples());
    }

    #[Test]
    public function it_checks_if_has_with_data(): void
    {
        $withData = new ResourceInfo(properties: [], with: ['meta' => []]);
        $withoutData = new ResourceInfo(properties: []);

        $this->assertTrue($withData->hasWithData());
        $this->assertFalse($withoutData->hasWithData());
    }

    #[Test]
    public function it_gets_property_by_name(): void
    {
        $info = new ResourceInfo(
            properties: [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        );

        $id = $info->getPropertyByName('id');
        $name = $info->getPropertyByName('name');
        $missing = $info->getPropertyByName('nonexistent');

        $this->assertEquals(['type' => 'integer'], $id);
        $this->assertEquals(['type' => 'string'], $name);
        $this->assertNull($missing);
    }

    #[Test]
    public function it_gets_property_names(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => [], 'name' => [], 'email' => []],
        );

        $names = $info->getPropertyNames();

        $this->assertEquals(['id', 'name', 'email'], $names);
    }

    #[Test]
    public function it_counts_properties(): void
    {
        $empty = ResourceInfo::empty();
        $withProps = new ResourceInfo(properties: ['a' => [], 'b' => [], 'c' => []]);

        $this->assertEquals(0, $empty->count());
        $this->assertEquals(3, $withProps->count());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new ResourceInfo(
            properties: ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
            with: ['meta' => ['type' => 'object']],
            hasExamples: true,
            customExample: ['id' => 1, 'name' => 'Test'],
            customExamples: [['id' => 1], ['id' => 2]],
            isCollection: true,
        );

        $restored = ResourceInfo::fromArray($original->toArray());

        $this->assertEquals($original->properties, $restored->properties);
        $this->assertEquals($original->with, $restored->with);
        $this->assertEquals($original->hasExamples, $restored->hasExamples);
        $this->assertEquals($original->customExample, $restored->customExample);
        $this->assertEquals($original->customExamples, $restored->customExamples);
        $this->assertEquals($original->isCollection, $restored->isCollection);
    }

    #[Test]
    public function it_merges_with_data_into_properties(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => ['type' => 'integer']],
            with: ['meta' => ['type' => 'object']],
        );

        $merged = $info->getAllProperties();

        $this->assertArrayHasKey('id', $merged);
        $this->assertArrayHasKey('meta', $merged);
        $this->assertEquals(['type' => 'integer'], $merged['id']);
        $this->assertEquals(['type' => 'object'], $merged['meta']);
    }

    #[Test]
    public function it_can_be_constructed_with_conditional_fields(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => ['type' => 'integer']],
            conditionalFields: ['secret' => ['condition' => 'when_admin', 'type' => 'string']],
        );

        $this->assertCount(1, $info->conditionalFields);
        $this->assertArrayHasKey('secret', $info->conditionalFields);
        $this->assertEquals('when_admin', $info->conditionalFields['secret']['condition']);
    }

    #[Test]
    public function it_can_be_constructed_with_nested_resources(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => ['type' => 'integer']],
            nestedResources: ['UserResource', 'PostResource'],
        );

        $this->assertCount(2, $info->nestedResources);
        $this->assertContains('UserResource', $info->nestedResources);
        $this->assertContains('PostResource', $info->nestedResources);
    }

    #[Test]
    public function it_creates_from_array_with_conditional_fields_and_nested_resources(): void
    {
        $array = [
            'properties' => ['id' => ['type' => 'integer']],
            'conditionalFields' => ['secret' => ['condition' => 'when_admin']],
            'nestedResources' => ['UserResource', 'ProfileResource'],
        ];

        $info = ResourceInfo::fromArray($array);

        $this->assertCount(1, $info->conditionalFields);
        $this->assertEquals(['condition' => 'when_admin'], $info->conditionalFields['secret']);
        $this->assertCount(2, $info->nestedResources);
        $this->assertEquals(['UserResource', 'ProfileResource'], $info->nestedResources);
    }

    #[Test]
    public function it_converts_to_array_with_conditional_fields_and_nested_resources(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => ['type' => 'integer']],
            conditionalFields: ['secret' => ['condition' => 'when_admin']],
            nestedResources: ['UserResource', 'ProfileResource'],
        );

        $array = $info->toArray();

        $this->assertArrayHasKey('conditionalFields', $array);
        $this->assertArrayHasKey('nestedResources', $array);
        $this->assertEquals(['secret' => ['condition' => 'when_admin']], $array['conditionalFields']);
        $this->assertEquals(['UserResource', 'ProfileResource'], $array['nestedResources']);
    }

    #[Test]
    public function it_does_not_include_empty_conditional_fields_and_nested_resources_in_array(): void
    {
        $info = new ResourceInfo(
            properties: ['id' => ['type' => 'integer']],
            conditionalFields: [],
            nestedResources: [],
        );

        $array = $info->toArray();

        $this->assertArrayNotHasKey('conditionalFields', $array);
        $this->assertArrayNotHasKey('nestedResources', $array);
    }

    #[Test]
    public function it_survives_serialization_round_trip_with_conditional_fields_and_nested_resources(): void
    {
        $original = new ResourceInfo(
            properties: ['id' => ['type' => 'integer']],
            conditionalFields: ['secret' => ['condition' => 'when_admin']],
            nestedResources: ['UserResource', 'ProfileResource'],
        );

        $restored = ResourceInfo::fromArray($original->toArray());

        $this->assertEquals($original->conditionalFields, $restored->conditionalFields);
        $this->assertEquals($original->nestedResources, $restored->nestedResources);
    }

    #[Test]
    public function it_throws_exception_for_invalid_properties(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ResourceInfo properties must be an array');

        ResourceInfo::fromArray(['properties' => 'not an array']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_with(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ResourceInfo with must be an array');

        ResourceInfo::fromArray(['with' => 'not an array']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_conditional_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ResourceInfo conditionalFields must be an array');

        ResourceInfo::fromArray(['conditionalFields' => 'not an array']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_nested_resources(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ResourceInfo nestedResources must be an array');

        ResourceInfo::fromArray(['nestedResources' => 'not an array']);
    }
}
