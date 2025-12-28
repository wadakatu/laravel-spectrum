<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ResponseInfo;
use LaravelSpectrum\DTO\ResponseType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResponseInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $info = new ResponseInfo(
            type: ResponseType::OBJECT,
            properties: ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
            resourceClass: null,
            error: null,
        );

        $this->assertEquals(ResponseType::OBJECT, $info->type);
        $this->assertCount(2, $info->properties);
        $this->assertNull($info->resourceClass);
        $this->assertNull($info->error);
    }

    #[Test]
    public function it_can_be_constructed_with_resource_class(): void
    {
        $info = new ResponseInfo(
            type: ResponseType::RESOURCE,
            properties: [],
            resourceClass: 'App\\Http\\Resources\\UserResource',
        );

        $this->assertEquals(ResponseType::RESOURCE, $info->type);
        $this->assertEquals('App\\Http\\Resources\\UserResource', $info->resourceClass);
    }

    #[Test]
    public function it_can_be_constructed_with_error(): void
    {
        $info = new ResponseInfo(
            type: ResponseType::UNKNOWN,
            properties: [],
            error: 'Failed to analyze response',
        );

        $this->assertEquals(ResponseType::UNKNOWN, $info->type);
        $this->assertEquals('Failed to analyze response', $info->error);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
        ];

        $info = ResponseInfo::fromArray($array);

        $this->assertEquals(ResponseType::OBJECT, $info->type);
        $this->assertCount(1, $info->properties);
        $this->assertNull($info->resourceClass);
        $this->assertNull($info->error);
    }

    #[Test]
    public function it_creates_from_array_with_resource_class(): void
    {
        $array = [
            'type' => 'resource',
            'class' => 'App\\Http\\Resources\\PostResource',
        ];

        $info = ResponseInfo::fromArray($array);

        $this->assertEquals(ResponseType::RESOURCE, $info->type);
        $this->assertEquals('App\\Http\\Resources\\PostResource', $info->resourceClass);
    }

    #[Test]
    public function it_creates_from_array_with_error(): void
    {
        $array = [
            'type' => 'unknown',
            'error' => 'Some error occurred',
        ];

        $info = ResponseInfo::fromArray($array);

        $this->assertEquals(ResponseType::UNKNOWN, $info->type);
        $this->assertEquals('Some error occurred', $info->error);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = ['type' => 'void'];

        $info = ResponseInfo::fromArray($array);

        $this->assertEquals(ResponseType::VOID, $info->type);
        $this->assertEquals([], $info->properties);
        $this->assertNull($info->resourceClass);
        $this->assertNull($info->error);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new ResponseInfo(
            type: ResponseType::OBJECT,
            properties: ['id' => ['type' => 'integer']],
            resourceClass: null,
            error: null,
        );

        $array = $info->toArray();

        $this->assertEquals('object', $array['type']);
        $this->assertEquals(['id' => ['type' => 'integer']], $array['properties']);
        $this->assertArrayNotHasKey('class', $array);
        $this->assertArrayNotHasKey('error', $array);
    }

    #[Test]
    public function it_converts_to_array_with_resource_class(): void
    {
        $info = new ResponseInfo(
            type: ResponseType::RESOURCE,
            properties: [],
            resourceClass: 'UserResource',
        );

        $array = $info->toArray();

        $this->assertEquals('resource', $array['type']);
        $this->assertEquals('UserResource', $array['class']);
    }

    #[Test]
    public function it_converts_to_array_with_error(): void
    {
        $info = new ResponseInfo(
            type: ResponseType::UNKNOWN,
            properties: [],
            error: 'Analysis failed',
        );

        $array = $info->toArray();

        $this->assertEquals('unknown', $array['type']);
        $this->assertEquals('Analysis failed', $array['error']);
    }

    #[Test]
    public function it_creates_void_instance(): void
    {
        $info = ResponseInfo::void();

        $this->assertEquals(ResponseType::VOID, $info->type);
        $this->assertEquals([], $info->properties);
        $this->assertNull($info->resourceClass);
        $this->assertNull($info->error);
    }

    #[Test]
    public function it_creates_unknown_instance(): void
    {
        $info = ResponseInfo::unknown();

        $this->assertEquals(ResponseType::UNKNOWN, $info->type);
        $this->assertEquals([], $info->properties);
    }

    #[Test]
    public function it_creates_unknown_with_error(): void
    {
        $info = ResponseInfo::unknownWithError('Something went wrong');

        $this->assertEquals(ResponseType::UNKNOWN, $info->type);
        $this->assertEquals('Something went wrong', $info->error);
    }

    #[Test]
    public function it_checks_if_void(): void
    {
        $void = ResponseInfo::void();
        $object = new ResponseInfo(type: ResponseType::OBJECT, properties: []);

        $this->assertTrue($void->isVoid());
        $this->assertFalse($object->isVoid());
    }

    #[Test]
    public function it_checks_if_collection(): void
    {
        $collection = new ResponseInfo(type: ResponseType::COLLECTION, properties: []);
        $object = new ResponseInfo(type: ResponseType::OBJECT, properties: []);

        $this->assertTrue($collection->isCollection());
        $this->assertFalse($object->isCollection());
    }

    #[Test]
    public function it_checks_if_resource(): void
    {
        $resource = new ResponseInfo(type: ResponseType::RESOURCE, properties: [], resourceClass: 'UserResource');
        $object = new ResponseInfo(type: ResponseType::OBJECT, properties: []);

        $this->assertTrue($resource->isResource());
        $this->assertFalse($object->isResource());
    }

    #[Test]
    public function it_checks_if_has_error(): void
    {
        $withError = ResponseInfo::unknownWithError('Error');
        $withoutError = ResponseInfo::unknown();

        $this->assertTrue($withError->hasError());
        $this->assertFalse($withoutError->hasError());
    }

    #[Test]
    public function it_checks_if_has_properties(): void
    {
        $withProps = new ResponseInfo(type: ResponseType::OBJECT, properties: ['id' => []]);
        $withoutProps = ResponseInfo::void();

        $this->assertTrue($withProps->hasProperties());
        $this->assertFalse($withoutProps->hasProperties());
    }

    #[Test]
    public function it_checks_if_has_resource_class(): void
    {
        $withClass = new ResponseInfo(type: ResponseType::RESOURCE, properties: [], resourceClass: 'UserResource');
        $withoutClass = new ResponseInfo(type: ResponseType::RESOURCE, properties: []);

        $this->assertTrue($withClass->hasResourceClass());
        $this->assertFalse($withoutClass->hasResourceClass());
    }

    #[Test]
    public function it_gets_property_names(): void
    {
        $info = new ResponseInfo(
            type: ResponseType::OBJECT,
            properties: ['id' => [], 'name' => [], 'email' => []],
        );

        $names = $info->getPropertyNames();

        $this->assertEquals(['id', 'name', 'email'], $names);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new ResponseInfo(
            type: ResponseType::OBJECT,
            properties: ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
            resourceClass: null,
            error: null,
        );

        $restored = ResponseInfo::fromArray($original->toArray());

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->properties, $restored->properties);
        $this->assertEquals($original->resourceClass, $restored->resourceClass);
        $this->assertEquals($original->error, $restored->error);
    }

    #[Test]
    public function it_survives_serialization_round_trip_with_resource(): void
    {
        $original = new ResponseInfo(
            type: ResponseType::RESOURCE,
            properties: [],
            resourceClass: 'App\\Http\\Resources\\UserResource',
        );

        $restored = ResponseInfo::fromArray($original->toArray());

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->resourceClass, $restored->resourceClass);
    }
}
