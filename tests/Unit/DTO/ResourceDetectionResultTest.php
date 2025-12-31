<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ResourceDetectionResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceDetectionResultTest extends TestCase
{
    #[Test]
    public function it_can_create_single_resource(): void
    {
        $result = ResourceDetectionResult::single('App\Http\Resources\UserResource');

        $this->assertEquals('App\Http\Resources\UserResource', $result->resourceClass);
        $this->assertFalse($result->isCollection);
    }

    #[Test]
    public function it_can_create_collection_resource(): void
    {
        $result = ResourceDetectionResult::collection('App\Http\Resources\UserResource');

        $this->assertEquals('App\Http\Resources\UserResource', $result->resourceClass);
        $this->assertTrue($result->isCollection);
    }

    #[Test]
    public function it_can_create_not_found_result(): void
    {
        $result = ResourceDetectionResult::notFound();

        $this->assertNull($result->resourceClass);
        $this->assertFalse($result->isCollection);
    }

    #[Test]
    public function it_checks_if_resource_was_found(): void
    {
        $withResource = ResourceDetectionResult::single('App\Http\Resources\UserResource');
        $withoutResource = ResourceDetectionResult::notFound();

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
        $result = ResourceDetectionResult::collection('App\Http\Resources\UserResource');

        $array = $result->toArray();

        $this->assertEquals([
            'resourceClass' => 'App\Http\Resources\UserResource',
            'resourceClasses' => ['App\Http\Resources\UserResource'],
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
            'resourceClasses' => [],
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

    #[Test]
    public function it_creates_union_result_with_multiple_resources(): void
    {
        $result = ResourceDetectionResult::union([
            'App\Http\Resources\UserResource',
            'App\Http\Resources\PostResource',
        ]);

        $this->assertCount(2, $result->resourceClasses);
        $this->assertContains('App\Http\Resources\UserResource', $result->resourceClasses);
        $this->assertContains('App\Http\Resources\PostResource', $result->resourceClasses);
        // Backward compatibility: resourceClass returns first
        $this->assertSame('App\Http\Resources\UserResource', $result->resourceClass);
        $this->assertFalse($result->isCollection);
        $this->assertTrue($result->hasResource());
        $this->assertTrue($result->hasMultipleResources());
    }

    #[Test]
    public function it_returns_false_for_has_multiple_resources_with_single(): void
    {
        $result = ResourceDetectionResult::single('App\Http\Resources\UserResource');

        $this->assertFalse($result->hasMultipleResources());
        // resourceClasses should still have one element
        $this->assertCount(1, $result->resourceClasses);
        $this->assertSame('App\Http\Resources\UserResource', $result->resourceClasses[0]);
    }

    #[Test]
    public function it_converts_union_to_array(): void
    {
        $result = ResourceDetectionResult::union([
            'App\Http\Resources\UserResource',
            'App\Http\Resources\PostResource',
        ]);

        $array = $result->toArray();

        $this->assertEquals([
            'resourceClass' => 'App\Http\Resources\UserResource',
            'resourceClasses' => [
                'App\Http\Resources\UserResource',
                'App\Http\Resources\PostResource',
            ],
            'isCollection' => false,
        ], $array);
    }

    #[Test]
    public function it_creates_union_from_array(): void
    {
        $data = [
            'resourceClasses' => [
                'App\Http\Resources\UserResource',
                'App\Http\Resources\PostResource',
            ],
            'isCollection' => false,
        ];

        $result = ResourceDetectionResult::fromArray($data);

        $this->assertCount(2, $result->resourceClasses);
        $this->assertSame('App\Http\Resources\UserResource', $result->resourceClass);
        $this->assertTrue($result->hasMultipleResources());
    }

    #[Test]
    public function it_throws_exception_for_union_with_single_resource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Union requires at least 2 resource classes');

        ResourceDetectionResult::union(['App\Http\Resources\UserResource']);
    }

    #[Test]
    public function it_throws_exception_for_union_with_empty_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Union requires at least 2 resource classes');

        ResourceDetectionResult::union([]);
    }
}
