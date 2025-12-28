<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\EnumBackingType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnumBackingTypeTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_cases(): void
    {
        $cases = EnumBackingType::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(EnumBackingType::STRING, $cases);
        $this->assertContains(EnumBackingType::INTEGER, $cases);
    }

    #[Test]
    public function it_has_correct_string_values(): void
    {
        $this->assertEquals('string', EnumBackingType::STRING->value);
        $this->assertEquals('int', EnumBackingType::INTEGER->value);
    }

    #[Test]
    public function it_creates_from_string(): void
    {
        $this->assertEquals(EnumBackingType::STRING, EnumBackingType::from('string'));
        $this->assertEquals(EnumBackingType::INTEGER, EnumBackingType::from('int'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(EnumBackingType::tryFrom('invalid'));
        $this->assertNull(EnumBackingType::tryFrom(''));
        $this->assertNull(EnumBackingType::tryFrom('integer')); // Must be 'int'
    }

    #[Test]
    public function it_checks_if_string(): void
    {
        $this->assertTrue(EnumBackingType::STRING->isString());
        $this->assertFalse(EnumBackingType::INTEGER->isString());
    }

    #[Test]
    public function it_checks_if_integer(): void
    {
        $this->assertTrue(EnumBackingType::INTEGER->isInteger());
        $this->assertFalse(EnumBackingType::STRING->isInteger());
    }

    #[Test]
    public function it_gets_openapi_type(): void
    {
        $this->assertEquals('string', EnumBackingType::STRING->toOpenApiType());
        $this->assertEquals('integer', EnumBackingType::INTEGER->toOpenApiType());
    }
}
