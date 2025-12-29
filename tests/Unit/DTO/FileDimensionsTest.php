<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\FileDimensions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileDimensionsTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $dimensions = new FileDimensions(
            minWidth: 100,
            maxWidth: 1920,
            minHeight: 50,
            maxHeight: 1080,
            ratio: '16/9',
        );

        $this->assertEquals(100, $dimensions->minWidth);
        $this->assertEquals(1920, $dimensions->maxWidth);
        $this->assertEquals(50, $dimensions->minHeight);
        $this->assertEquals(1080, $dimensions->maxHeight);
        $this->assertEquals('16/9', $dimensions->ratio);
    }

    #[Test]
    public function it_can_be_constructed_with_exact_dimensions(): void
    {
        $dimensions = new FileDimensions(
            width: 1920,
            height: 1080,
        );

        $this->assertEquals(1920, $dimensions->width);
        $this->assertEquals(1080, $dimensions->height);
        $this->assertNull($dimensions->minWidth);
        $this->assertNull($dimensions->maxWidth);
        $this->assertNull($dimensions->minHeight);
        $this->assertNull($dimensions->maxHeight);
        $this->assertNull($dimensions->ratio);
    }

    #[Test]
    public function it_can_be_constructed_with_defaults(): void
    {
        $dimensions = new FileDimensions;

        $this->assertNull($dimensions->width);
        $this->assertNull($dimensions->height);
        $this->assertNull($dimensions->minWidth);
        $this->assertNull($dimensions->maxWidth);
        $this->assertNull($dimensions->minHeight);
        $this->assertNull($dimensions->maxHeight);
        $this->assertNull($dimensions->ratio);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'min_width' => 200,
            'max_width' => 800,
            'min_height' => 100,
            'max_height' => 600,
            'ratio' => '4/3',
        ];

        $dimensions = FileDimensions::fromArray($array);

        $this->assertEquals(200, $dimensions->minWidth);
        $this->assertEquals(800, $dimensions->maxWidth);
        $this->assertEquals(100, $dimensions->minHeight);
        $this->assertEquals(600, $dimensions->maxHeight);
        $this->assertEquals('4/3', $dimensions->ratio);
    }

    #[Test]
    public function it_creates_from_array_with_exact_dimensions(): void
    {
        $array = [
            'width' => 1920,
            'height' => 1080,
        ];

        $dimensions = FileDimensions::fromArray($array);

        $this->assertEquals(1920, $dimensions->width);
        $this->assertEquals(1080, $dimensions->height);
        $this->assertNull($dimensions->minWidth);
        $this->assertNull($dimensions->maxWidth);
        $this->assertNull($dimensions->minHeight);
        $this->assertNull($dimensions->maxHeight);
        $this->assertNull($dimensions->ratio);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $dimensions = FileDimensions::fromArray([]);

        $this->assertNull($dimensions->width);
        $this->assertNull($dimensions->height);
        $this->assertNull($dimensions->minWidth);
        $this->assertNull($dimensions->maxWidth);
        $this->assertNull($dimensions->minHeight);
        $this->assertNull($dimensions->maxHeight);
        $this->assertNull($dimensions->ratio);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $dimensions = new FileDimensions(
            minWidth: 100,
            maxWidth: 500,
            minHeight: 50,
            maxHeight: 300,
            ratio: '3/2',
        );

        $array = $dimensions->toArray();

        $this->assertEquals([
            'min_width' => 100,
            'max_width' => 500,
            'min_height' => 50,
            'max_height' => 300,
            'ratio' => '3/2',
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_without_null_values(): void
    {
        $dimensions = new FileDimensions(
            minWidth: 100,
            maxWidth: 500,
        );

        $array = $dimensions->toArray();

        $this->assertEquals([
            'min_width' => 100,
            'max_width' => 500,
        ], $array);
        $this->assertArrayNotHasKey('width', $array);
        $this->assertArrayNotHasKey('height', $array);
        $this->assertArrayNotHasKey('min_height', $array);
        $this->assertArrayNotHasKey('max_height', $array);
        $this->assertArrayNotHasKey('ratio', $array);
    }

    #[Test]
    public function it_converts_exact_dimensions_to_array(): void
    {
        $dimensions = new FileDimensions(
            width: 1920,
            height: 1080,
        );

        $array = $dimensions->toArray();

        $this->assertEquals([
            'width' => 1920,
            'height' => 1080,
        ], $array);
    }

    #[Test]
    public function it_creates_empty_instance(): void
    {
        $dimensions = FileDimensions::empty();

        $this->assertNull($dimensions->minWidth);
        $this->assertNull($dimensions->maxWidth);
        $this->assertNull($dimensions->minHeight);
        $this->assertNull($dimensions->maxHeight);
        $this->assertNull($dimensions->ratio);
    }

    #[Test]
    public function it_checks_if_empty(): void
    {
        $empty = FileDimensions::empty();
        $notEmpty = new FileDimensions(minWidth: 100);
        $withExactWidth = new FileDimensions(width: 1920);
        $withExactHeight = new FileDimensions(height: 1080);

        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($notEmpty->isEmpty());
        $this->assertFalse($withExactWidth->isEmpty());
        $this->assertFalse($withExactHeight->isEmpty());
    }

    #[Test]
    public function it_checks_if_has_width_constraints(): void
    {
        $withExact = new FileDimensions(width: 1920);
        $withMin = new FileDimensions(minWidth: 100);
        $withMax = new FileDimensions(maxWidth: 500);
        $withBoth = new FileDimensions(minWidth: 100, maxWidth: 500);
        $without = new FileDimensions;

        $this->assertTrue($withExact->hasWidthConstraints());
        $this->assertTrue($withMin->hasWidthConstraints());
        $this->assertTrue($withMax->hasWidthConstraints());
        $this->assertTrue($withBoth->hasWidthConstraints());
        $this->assertFalse($without->hasWidthConstraints());
    }

    #[Test]
    public function it_checks_if_has_height_constraints(): void
    {
        $withExact = new FileDimensions(height: 1080);
        $withMin = new FileDimensions(minHeight: 50);
        $withMax = new FileDimensions(maxHeight: 300);
        $withBoth = new FileDimensions(minHeight: 50, maxHeight: 300);
        $without = new FileDimensions;

        $this->assertTrue($withExact->hasHeightConstraints());
        $this->assertTrue($withMin->hasHeightConstraints());
        $this->assertTrue($withMax->hasHeightConstraints());
        $this->assertTrue($withBoth->hasHeightConstraints());
        $this->assertFalse($without->hasHeightConstraints());
    }

    #[Test]
    public function it_checks_if_has_ratio(): void
    {
        $withRatio = new FileDimensions(ratio: '16/9');
        $withoutRatio = new FileDimensions;

        $this->assertTrue($withRatio->hasRatio());
        $this->assertFalse($withoutRatio->hasRatio());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new FileDimensions(
            minWidth: 100,
            maxWidth: 1920,
            minHeight: 50,
            maxHeight: 1080,
            ratio: '16/9',
        );

        $restored = FileDimensions::fromArray($original->toArray());

        $this->assertEquals($original->minWidth, $restored->minWidth);
        $this->assertEquals($original->maxWidth, $restored->maxWidth);
        $this->assertEquals($original->minHeight, $restored->minHeight);
        $this->assertEquals($original->maxHeight, $restored->maxHeight);
        $this->assertEquals($original->ratio, $restored->ratio);
    }

    #[Test]
    public function it_survives_serialization_round_trip_with_exact_dimensions(): void
    {
        $original = new FileDimensions(
            width: 1920,
            height: 1080,
        );

        $restored = FileDimensions::fromArray($original->toArray());

        $this->assertEquals($original->width, $restored->width);
        $this->assertEquals($original->height, $restored->height);
        $this->assertNull($restored->minWidth);
        $this->assertNull($restored->maxWidth);
        $this->assertNull($restored->minHeight);
        $this->assertNull($restored->maxHeight);
        $this->assertNull($restored->ratio);
    }

    #[Test]
    public function it_preserves_zero_dimension_values(): void
    {
        $dimensions = new FileDimensions(minWidth: 0, minHeight: 0);

        $array = $dimensions->toArray();

        $this->assertArrayHasKey('min_width', $array);
        $this->assertArrayHasKey('min_height', $array);
        $this->assertEquals(0, $array['min_width']);
        $this->assertEquals(0, $array['min_height']);
    }

    #[Test]
    public function it_treats_zero_as_valid_constraint(): void
    {
        $dimensions = new FileDimensions(minWidth: 0);

        $this->assertFalse($dimensions->isEmpty());
        $this->assertTrue($dimensions->hasWidthConstraints());
    }
}
