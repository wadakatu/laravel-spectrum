<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ResourceDetectionResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceDetectionResultTest extends TestCase
{
    #[Test]
    public function it_can_create_with_resource_class(): void
    {
        $result = new ResourceDetectionResult(
            resourceClass: 'App\Http\Resources\UserResource',
            isCollection: false,
        );

        $this->assertEquals('App\Http\Resources\UserResource', $result->resourceClass);
        $this->assertFalse($result->isCollection);
    }

    #[Test]
    public function it_can_create_collection_resource(): void
    {
        $result = new ResourceDetectionResult(
            resourceClass: 'App\Http\Resources\UserResource',
            isCollection: true,
        );

        $this->assertEquals('App\Http\Resources\UserResource', $result->resourceClass);
        $this->assertTrue($result->isCollection);
    }

    #[Test]
    public function it_can_create_with_null_resource(): void
    {
        $result = new ResourceDetectionResult(
            resourceClass: null,
            isCollection: false,
        );

        $this->assertNull($result->resourceClass);
        $this->assertFalse($result->isCollection);
    }

    #[Test]
    public function it_checks_if_resource_was_found(): void
    {
        $withResource = new ResourceDetectionResult(
            resourceClass: 'App\Http\Resources\UserResource',
            isCollection: false,
        );

        $withoutResource = new ResourceDetectionResult(
            resourceClass: null,
            isCollection: false,
        );

        $this->assertTrue($withResource->hasResource());
        $this->assertFalse($withoutResource->hasResource());
    }

    #[Test]
    public function it_creates_empty_result(): void
    {
        $result = ResourceDetectionResult::notFound();

        $this->assertNull($result->resourceClass);
        $this->assertFalse($result->isCollection);
        $this->assertFalse($result->hasResource());
    }

    #[Test]
    public function it_creates_single_resource_result(): void
    {
        $result = ResourceDetectionResult::single('App\Http\Resources\UserResource');

        $this->assertEquals('App\Http\Resources\UserResource', $result->resourceClass);
        $this->assertFalse($result->isCollection);
        $this->assertTrue($result->hasResource());
    }

    #[Test]
    public function it_creates_collection_resource_result(): void
    {
        $result = ResourceDetectionResult::collection('App\Http\Resources\UserResource');

        $this->assertEquals('App\Http\Resources\UserResource', $result->resourceClass);
        $this->assertTrue($result->isCollection);
        $this->assertTrue($result->hasResource());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $result = new ResourceDetectionResult(
            resourceClass: 'App\Http\Resources\UserResource',
            isCollection: true,
        );

        $array = $result->toArray();

        $this->assertEquals([
            'resourceClass' => 'App\Http\Resources\UserResource',
            'isCollection' => true,
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_with_null_resource(): void
    {
        $result = ResourceDetectionResult::notFound();

        $array = $result->toArray();

        $this->assertEquals([
            'resourceClass' => null,
            'isCollection' => false,
        ], $array);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'resourceClass' => 'App\Http\Resources\PostResource',
            'isCollection' => true,
        ];

        $result = ResourceDetectionResult::fromArray($data);

        $this->assertEquals('App\Http\Resources\PostResource', $result->resourceClass);
        $this->assertTrue($result->isCollection);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $result = ResourceDetectionResult::fromArray([]);

        $this->assertNull($result->resourceClass);
        $this->assertFalse($result->isCollection);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = ResourceDetectionResult::collection('App\Http\Resources\UserResource');

        $restored = ResourceDetectionResult::fromArray($original->toArray());

        $this->assertEquals($original->resourceClass, $restored->resourceClass);
        $this->assertEquals($original->isCollection, $restored->isCollection);
    }
}
