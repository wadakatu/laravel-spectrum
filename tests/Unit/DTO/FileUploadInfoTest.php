<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\FileDimensions;
use LaravelSpectrum\DTO\FileUploadInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileUploadInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $dimensions = new FileDimensions(minWidth: 100, maxWidth: 500);
        $info = new FileUploadInfo(
            isImage: true,
            mimes: ['jpeg', 'png'],
            mimeTypes: ['image/jpeg', 'image/png'],
            maxSize: 2048,
            minSize: 10,
            dimensions: $dimensions,
            multiple: false,
        );

        $this->assertTrue($info->isImage);
        $this->assertEquals(['jpeg', 'png'], $info->mimes);
        $this->assertEquals(['image/jpeg', 'image/png'], $info->mimeTypes);
        $this->assertEquals(2048, $info->maxSize);
        $this->assertEquals(10, $info->minSize);
        $this->assertSame($dimensions, $info->dimensions);
        $this->assertFalse($info->multiple);
    }

    #[Test]
    public function it_can_be_constructed_with_defaults(): void
    {
        $info = new FileUploadInfo;

        $this->assertFalse($info->isImage);
        $this->assertEquals([], $info->mimes);
        $this->assertEquals([], $info->mimeTypes);
        $this->assertNull($info->maxSize);
        $this->assertNull($info->minSize);
        $this->assertNull($info->dimensions);
        $this->assertFalse($info->multiple);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'is_image' => true,
            'mimes' => ['gif', 'webp'],
            'mime_types' => ['image/gif', 'image/webp'],
            'max_size' => 5120,
            'min_size' => 50,
            'dimensions' => [
                'min_width' => 200,
                'max_width' => 1000,
            ],
            'multiple' => true,
        ];

        $info = FileUploadInfo::fromArray($array);

        $this->assertTrue($info->isImage);
        $this->assertEquals(['gif', 'webp'], $info->mimes);
        $this->assertEquals(['image/gif', 'image/webp'], $info->mimeTypes);
        $this->assertEquals(5120, $info->maxSize);
        $this->assertEquals(50, $info->minSize);
        $this->assertInstanceOf(FileDimensions::class, $info->dimensions);
        $this->assertEquals(200, $info->dimensions->minWidth);
        $this->assertEquals(1000, $info->dimensions->maxWidth);
        $this->assertTrue($info->multiple);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $info = FileUploadInfo::fromArray([]);

        $this->assertFalse($info->isImage);
        $this->assertEquals([], $info->mimes);
        $this->assertEquals([], $info->mimeTypes);
        $this->assertNull($info->maxSize);
        $this->assertNull($info->minSize);
        $this->assertNull($info->dimensions);
        $this->assertFalse($info->multiple);
    }

    #[Test]
    public function it_creates_from_array_with_empty_dimensions(): void
    {
        $array = [
            'is_image' => true,
            'dimensions' => [],
        ];

        $info = FileUploadInfo::fromArray($array);

        $this->assertNull($info->dimensions);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new FileUploadInfo(
            isImage: true,
            mimes: ['png'],
            mimeTypes: ['image/png'],
            maxSize: 1024,
            minSize: 5,
            dimensions: new FileDimensions(minWidth: 100),
            multiple: true,
        );

        $array = $info->toArray();

        $this->assertEquals([
            'type' => 'file',
            'is_image' => true,
            'mimes' => ['png'],
            'mime_types' => ['image/png'],
            'max_size' => 1024,
            'min_size' => 5,
            'dimensions' => ['min_width' => 100],
            'multiple' => true,
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_without_optional_fields_when_empty(): void
    {
        $info = new FileUploadInfo;

        $array = $info->toArray();

        $this->assertEquals(['type' => 'file'], $array);
        $this->assertArrayNotHasKey('is_image', $array);
        $this->assertArrayNotHasKey('mimes', $array);
        $this->assertArrayNotHasKey('mime_types', $array);
        $this->assertArrayNotHasKey('max_size', $array);
        $this->assertArrayNotHasKey('min_size', $array);
        $this->assertArrayNotHasKey('dimensions', $array);
        $this->assertArrayNotHasKey('multiple', $array);
    }

    #[Test]
    public function it_creates_image_upload(): void
    {
        $info = FileUploadInfo::image();

        $this->assertTrue($info->isImage);
        $this->assertEquals([], $info->mimes);
        $this->assertFalse($info->multiple);
    }

    #[Test]
    public function it_creates_file_upload(): void
    {
        $info = FileUploadInfo::file();

        $this->assertFalse($info->isImage);
        $this->assertEquals([], $info->mimes);
        $this->assertFalse($info->multiple);
    }

    #[Test]
    public function it_checks_if_is_image_upload(): void
    {
        $image = FileUploadInfo::image();
        $file = FileUploadInfo::file();

        $this->assertTrue($image->isImageUpload());
        $this->assertFalse($file->isImageUpload());
    }

    #[Test]
    public function it_checks_if_has_size_constraints(): void
    {
        $withMax = new FileUploadInfo(maxSize: 1024);
        $withMin = new FileUploadInfo(minSize: 10);
        $withBoth = new FileUploadInfo(maxSize: 1024, minSize: 10);
        $without = new FileUploadInfo;

        $this->assertTrue($withMax->hasSizeConstraints());
        $this->assertTrue($withMin->hasSizeConstraints());
        $this->assertTrue($withBoth->hasSizeConstraints());
        $this->assertFalse($without->hasSizeConstraints());
    }

    #[Test]
    public function it_checks_if_has_dimensions(): void
    {
        $withDimensions = new FileUploadInfo(dimensions: new FileDimensions(minWidth: 100));
        $withEmptyDimensions = new FileUploadInfo(dimensions: FileDimensions::empty());
        $without = new FileUploadInfo;

        $this->assertTrue($withDimensions->hasDimensions());
        $this->assertFalse($withEmptyDimensions->hasDimensions());
        $this->assertFalse($without->hasDimensions());
    }

    #[Test]
    public function it_checks_if_has_mime_restrictions(): void
    {
        $withMimes = new FileUploadInfo(mimes: ['pdf', 'doc']);
        $withMimeTypes = new FileUploadInfo(mimeTypes: ['application/pdf']);
        $withBoth = new FileUploadInfo(mimes: ['pdf'], mimeTypes: ['application/pdf']);
        $without = new FileUploadInfo;

        $this->assertTrue($withMimes->hasMimeRestrictions());
        $this->assertTrue($withMimeTypes->hasMimeRestrictions());
        $this->assertTrue($withBoth->hasMimeRestrictions());
        $this->assertFalse($without->hasMimeRestrictions());
    }

    #[Test]
    public function it_checks_if_is_multiple_upload(): void
    {
        $multiple = new FileUploadInfo(multiple: true);
        $single = new FileUploadInfo(multiple: false);

        $this->assertTrue($multiple->isMultipleUpload());
        $this->assertFalse($single->isMultipleUpload());
    }

    #[Test]
    public function it_gets_accepted_mime_types_string(): void
    {
        $info = new FileUploadInfo(mimeTypes: ['image/jpeg', 'image/png', 'image/gif']);

        $this->assertEquals('image/jpeg, image/png, image/gif', $info->getAcceptedMimeTypesString());
    }

    #[Test]
    public function it_gets_empty_string_for_no_mime_types(): void
    {
        $info = new FileUploadInfo;

        $this->assertEquals('', $info->getAcceptedMimeTypesString());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new FileUploadInfo(
            isImage: true,
            mimes: ['jpeg', 'png'],
            mimeTypes: ['image/jpeg', 'image/png'],
            maxSize: 2048,
            minSize: 10,
            dimensions: new FileDimensions(minWidth: 100, maxWidth: 500),
            multiple: true,
        );

        $restored = FileUploadInfo::fromArray($original->toArray());

        $this->assertEquals($original->isImage, $restored->isImage);
        $this->assertEquals($original->mimes, $restored->mimes);
        $this->assertEquals($original->mimeTypes, $restored->mimeTypes);
        $this->assertEquals($original->maxSize, $restored->maxSize);
        $this->assertEquals($original->minSize, $restored->minSize);
        $this->assertEquals($original->multiple, $restored->multiple);
        $this->assertEquals($original->dimensions->minWidth, $restored->dimensions->minWidth);
        $this->assertEquals($original->dimensions->maxWidth, $restored->dimensions->maxWidth);
    }

    #[Test]
    public function it_creates_from_array_with_partial_dimensions(): void
    {
        $array = [
            'is_image' => true,
            'dimensions' => [
                'min_width' => 150,
                'ratio' => '16/9',
            ],
        ];

        $info = FileUploadInfo::fromArray($array);

        $this->assertInstanceOf(FileDimensions::class, $info->dimensions);
        $this->assertEquals(150, $info->dimensions->minWidth);
        $this->assertNull($info->dimensions->maxWidth);
        $this->assertNull($info->dimensions->minHeight);
        $this->assertNull($info->dimensions->maxHeight);
        $this->assertEquals('16/9', $info->dimensions->ratio);
    }

    #[Test]
    public function it_preserves_zero_size_values(): void
    {
        $info = new FileUploadInfo(minSize: 0, maxSize: 1024);

        $array = $info->toArray();

        $this->assertArrayHasKey('min_size', $array);
        $this->assertArrayHasKey('max_size', $array);
        $this->assertEquals(0, $array['min_size']);
        $this->assertEquals(1024, $array['max_size']);
    }

    #[Test]
    public function it_treats_zero_size_as_valid_constraint(): void
    {
        $info = new FileUploadInfo(minSize: 0);

        $this->assertTrue($info->hasSizeConstraints());
    }

    #[Test]
    public function it_excludes_false_boolean_fields_from_array(): void
    {
        $info = new FileUploadInfo(
            isImage: false,
            multiple: false,
            mimes: ['pdf'],
        );

        $array = $info->toArray();

        $this->assertArrayNotHasKey('is_image', $array);
        $this->assertArrayNotHasKey('multiple', $array);
        $this->assertArrayHasKey('mimes', $array);
    }
}
