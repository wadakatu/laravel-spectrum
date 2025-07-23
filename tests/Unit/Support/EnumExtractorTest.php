<?php

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\EnumExtractor;
use LaravelSpectrum\Tests\Fixtures\Enums\PriorityEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\SimpleEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;
use LaravelSpectrum\Tests\TestCase;

class EnumExtractorTest extends TestCase
{
    private EnumExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new EnumExtractor;
    }

    public function test_extracts_string_backed_enum_values(): void
    {
        $values = $this->extractor->extractValues(StatusEnum::class);

        $this->assertEquals(['active', 'inactive', 'pending'], $values);
    }

    public function test_extracts_int_backed_enum_values(): void
    {
        $values = $this->extractor->extractValues(PriorityEnum::class);

        $this->assertEquals([1, 2, 3], $values);
    }

    public function test_extracts_unit_enum_values(): void
    {
        $values = $this->extractor->extractValues(SimpleEnum::class);

        $this->assertEquals(['OPTION_A', 'OPTION_B', 'OPTION_C'], $values);
    }

    public function test_detects_string_enum_type(): void
    {
        $type = $this->extractor->getEnumType(StatusEnum::class);

        $this->assertEquals('string', $type);
    }

    public function test_detects_integer_enum_type(): void
    {
        $type = $this->extractor->getEnumType(PriorityEnum::class);

        $this->assertEquals('integer', $type);
    }

    public function test_defaults_to_string_for_unit_enum(): void
    {
        $type = $this->extractor->getEnumType(SimpleEnum::class);

        $this->assertEquals('string', $type);
    }

    public function test_identifies_backed_enum(): void
    {
        $this->assertTrue($this->extractor->isBackedEnum(StatusEnum::class));
        $this->assertTrue($this->extractor->isBackedEnum(PriorityEnum::class));
    }

    public function test_identifies_unit_enum(): void
    {
        $this->assertFalse($this->extractor->isBackedEnum(SimpleEnum::class));
    }

    public function test_throws_exception_for_non_enum_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->extractor->extractValues(\stdClass::class);
    }

    public function test_throws_exception_for_non_existent_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->extractor->extractValues('NonExistentClass');
    }

    public function test_preserves_value_order(): void
    {
        $values = $this->extractor->extractValues(PriorityEnum::class);

        $this->assertSame([1, 2, 3], $values);
        $this->assertNotSame([3, 2, 1], $values);
    }
}
