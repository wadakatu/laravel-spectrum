<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ResponseType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResponseTypeTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_cases(): void
    {
        $cases = ResponseType::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(ResponseType::VOID, $cases);
        $this->assertContains(ResponseType::RESOURCE, $cases);
        $this->assertContains(ResponseType::OBJECT, $cases);
        $this->assertContains(ResponseType::COLLECTION, $cases);
        $this->assertContains(ResponseType::UNKNOWN, $cases);
    }

    #[Test]
    public function it_has_correct_string_values(): void
    {
        $this->assertEquals('void', ResponseType::VOID->value);
        $this->assertEquals('resource', ResponseType::RESOURCE->value);
        $this->assertEquals('object', ResponseType::OBJECT->value);
        $this->assertEquals('collection', ResponseType::COLLECTION->value);
        $this->assertEquals('unknown', ResponseType::UNKNOWN->value);
    }

    #[Test]
    public function it_creates_from_string(): void
    {
        $this->assertEquals(ResponseType::VOID, ResponseType::from('void'));
        $this->assertEquals(ResponseType::RESOURCE, ResponseType::from('resource'));
        $this->assertEquals(ResponseType::OBJECT, ResponseType::from('object'));
        $this->assertEquals(ResponseType::COLLECTION, ResponseType::from('collection'));
        $this->assertEquals(ResponseType::UNKNOWN, ResponseType::from('unknown'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(ResponseType::tryFrom('invalid'));
        $this->assertNull(ResponseType::tryFrom(''));
    }

    #[Test]
    public function it_checks_if_void(): void
    {
        $this->assertTrue(ResponseType::VOID->isVoid());
        $this->assertFalse(ResponseType::RESOURCE->isVoid());
        $this->assertFalse(ResponseType::OBJECT->isVoid());
        $this->assertFalse(ResponseType::COLLECTION->isVoid());
        $this->assertFalse(ResponseType::UNKNOWN->isVoid());
    }

    #[Test]
    public function it_checks_if_collection(): void
    {
        $this->assertTrue(ResponseType::COLLECTION->isCollection());
        $this->assertFalse(ResponseType::VOID->isCollection());
        $this->assertFalse(ResponseType::RESOURCE->isCollection());
        $this->assertFalse(ResponseType::OBJECT->isCollection());
        $this->assertFalse(ResponseType::UNKNOWN->isCollection());
    }

    #[Test]
    public function it_checks_if_resource(): void
    {
        $this->assertTrue(ResponseType::RESOURCE->isResource());
        $this->assertFalse(ResponseType::VOID->isResource());
        $this->assertFalse(ResponseType::OBJECT->isResource());
        $this->assertFalse(ResponseType::COLLECTION->isResource());
        $this->assertFalse(ResponseType::UNKNOWN->isResource());
    }

    #[Test]
    public function it_checks_if_has_structure(): void
    {
        $this->assertTrue(ResponseType::OBJECT->hasStructure());
        $this->assertTrue(ResponseType::COLLECTION->hasStructure());
        $this->assertTrue(ResponseType::RESOURCE->hasStructure());
        $this->assertFalse(ResponseType::VOID->hasStructure());
        $this->assertFalse(ResponseType::UNKNOWN->hasStructure());
    }
}
