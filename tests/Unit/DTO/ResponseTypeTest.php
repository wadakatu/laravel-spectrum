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

        $this->assertCount(11, $cases);
        $this->assertContains(ResponseType::VOID, $cases);
        $this->assertContains(ResponseType::RESOURCE, $cases);
        $this->assertContains(ResponseType::OBJECT, $cases);
        $this->assertContains(ResponseType::COLLECTION, $cases);
        $this->assertContains(ResponseType::UNKNOWN, $cases);
        $this->assertContains(ResponseType::BINARY_FILE, $cases);
        $this->assertContains(ResponseType::STREAMED, $cases);
        $this->assertContains(ResponseType::PLAIN_TEXT, $cases);
        $this->assertContains(ResponseType::XML, $cases);
        $this->assertContains(ResponseType::HTML, $cases);
        $this->assertContains(ResponseType::CUSTOM, $cases);
    }

    #[Test]
    public function it_has_correct_string_values(): void
    {
        $this->assertEquals('void', ResponseType::VOID->value);
        $this->assertEquals('resource', ResponseType::RESOURCE->value);
        $this->assertEquals('object', ResponseType::OBJECT->value);
        $this->assertEquals('collection', ResponseType::COLLECTION->value);
        $this->assertEquals('unknown', ResponseType::UNKNOWN->value);
        $this->assertEquals('binary_file', ResponseType::BINARY_FILE->value);
        $this->assertEquals('streamed', ResponseType::STREAMED->value);
        $this->assertEquals('plain_text', ResponseType::PLAIN_TEXT->value);
        $this->assertEquals('xml', ResponseType::XML->value);
        $this->assertEquals('html', ResponseType::HTML->value);
        $this->assertEquals('custom', ResponseType::CUSTOM->value);
    }

    #[Test]
    public function it_creates_from_string(): void
    {
        $this->assertEquals(ResponseType::VOID, ResponseType::from('void'));
        $this->assertEquals(ResponseType::RESOURCE, ResponseType::from('resource'));
        $this->assertEquals(ResponseType::OBJECT, ResponseType::from('object'));
        $this->assertEquals(ResponseType::COLLECTION, ResponseType::from('collection'));
        $this->assertEquals(ResponseType::UNKNOWN, ResponseType::from('unknown'));
        $this->assertEquals(ResponseType::BINARY_FILE, ResponseType::from('binary_file'));
        $this->assertEquals(ResponseType::STREAMED, ResponseType::from('streamed'));
        $this->assertEquals(ResponseType::PLAIN_TEXT, ResponseType::from('plain_text'));
        $this->assertEquals(ResponseType::XML, ResponseType::from('xml'));
        $this->assertEquals(ResponseType::HTML, ResponseType::from('html'));
        $this->assertEquals(ResponseType::CUSTOM, ResponseType::from('custom'));
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

    #[Test]
    public function it_checks_if_unknown(): void
    {
        $this->assertTrue(ResponseType::UNKNOWN->isUnknown());
        $this->assertFalse(ResponseType::VOID->isUnknown());
        $this->assertFalse(ResponseType::RESOURCE->isUnknown());
        $this->assertFalse(ResponseType::OBJECT->isUnknown());
        $this->assertFalse(ResponseType::COLLECTION->isUnknown());
    }

    #[Test]
    public function it_checks_if_binary_response(): void
    {
        $this->assertTrue(ResponseType::BINARY_FILE->isBinaryResponse());
        $this->assertTrue(ResponseType::STREAMED->isBinaryResponse());
        $this->assertFalse(ResponseType::VOID->isBinaryResponse());
        $this->assertFalse(ResponseType::RESOURCE->isBinaryResponse());
        $this->assertFalse(ResponseType::OBJECT->isBinaryResponse());
        $this->assertFalse(ResponseType::COLLECTION->isBinaryResponse());
        $this->assertFalse(ResponseType::UNKNOWN->isBinaryResponse());
        $this->assertFalse(ResponseType::PLAIN_TEXT->isBinaryResponse());
        $this->assertFalse(ResponseType::XML->isBinaryResponse());
        $this->assertFalse(ResponseType::HTML->isBinaryResponse());
        $this->assertFalse(ResponseType::CUSTOM->isBinaryResponse());
    }

    #[Test]
    public function it_checks_if_non_json_response(): void
    {
        // Non-JSON response types should return true
        $this->assertTrue(ResponseType::BINARY_FILE->isNonJsonResponse());
        $this->assertTrue(ResponseType::STREAMED->isNonJsonResponse());
        $this->assertTrue(ResponseType::PLAIN_TEXT->isNonJsonResponse());
        $this->assertTrue(ResponseType::XML->isNonJsonResponse());
        $this->assertTrue(ResponseType::HTML->isNonJsonResponse());
        $this->assertTrue(ResponseType::CUSTOM->isNonJsonResponse());

        // JSON response types should return false
        $this->assertFalse(ResponseType::VOID->isNonJsonResponse());
        $this->assertFalse(ResponseType::RESOURCE->isNonJsonResponse());
        $this->assertFalse(ResponseType::OBJECT->isNonJsonResponse());
        $this->assertFalse(ResponseType::COLLECTION->isNonJsonResponse());
        $this->assertFalse(ResponseType::UNKNOWN->isNonJsonResponse());
    }

    #[Test]
    public function it_checks_has_structure_for_new_types(): void
    {
        // New types should not have structure
        $this->assertFalse(ResponseType::BINARY_FILE->hasStructure());
        $this->assertFalse(ResponseType::STREAMED->hasStructure());
        $this->assertFalse(ResponseType::PLAIN_TEXT->hasStructure());
        $this->assertFalse(ResponseType::XML->hasStructure());
        $this->assertFalse(ResponseType::HTML->hasStructure());
        $this->assertFalse(ResponseType::CUSTOM->hasStructure());
    }
}
