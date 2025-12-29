<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\EnumBackingType;
use LaravelSpectrum\DTO\EnumInfo;
use LaravelSpectrum\DTO\FileUploadInfo;
use LaravelSpectrum\DTO\InlineParameterInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InlineParameterInfoTest extends TestCase
{
    #[Test]
    public function it_can_create_standard_string_parameter(): void
    {
        $param = new InlineParameterInfo(
            name: 'email',
            type: 'string',
            required: true,
            rules: 'required|email|max:255',
            description: 'User email address',
            format: 'email',
            maxLength: 255,
        );

        $this->assertEquals('email', $param->name);
        $this->assertEquals('string', $param->type);
        $this->assertTrue($param->required);
        $this->assertEquals('required|email|max:255', $param->rules);
        $this->assertEquals('User email address', $param->description);
        $this->assertEquals('email', $param->format);
        $this->assertEquals(255, $param->maxLength);
        $this->assertNull($param->minLength);
        $this->assertNull($param->minimum);
        $this->assertNull($param->maximum);
        $this->assertFalse($param->isFileUpload());
    }

    #[Test]
    public function it_can_create_integer_parameter_with_constraints(): void
    {
        $param = new InlineParameterInfo(
            name: 'age',
            type: 'integer',
            required: true,
            rules: ['required', 'integer', 'min:18', 'max:100'],
            description: 'User age',
            minimum: 18,
            maximum: 100,
        );

        $this->assertEquals('age', $param->name);
        $this->assertEquals('integer', $param->type);
        $this->assertTrue($param->required);
        $this->assertEquals(['required', 'integer', 'min:18', 'max:100'], $param->rules);
        $this->assertEquals(18, $param->minimum);
        $this->assertEquals(100, $param->maximum);
        $this->assertNull($param->minLength);
        $this->assertNull($param->maxLength);
        $this->assertTrue($param->hasConstraints());
        $this->assertTrue($param->hasNumericConstraints());
        $this->assertFalse($param->hasLengthConstraints());
    }

    #[Test]
    public function it_can_create_string_parameter_with_length_constraints(): void
    {
        $param = new InlineParameterInfo(
            name: 'username',
            type: 'string',
            required: true,
            rules: 'required|string|min:3|max:20',
            description: 'Username',
            minLength: 3,
            maxLength: 20,
        );

        $this->assertEquals(3, $param->minLength);
        $this->assertEquals(20, $param->maxLength);
        $this->assertTrue($param->hasConstraints());
        $this->assertTrue($param->hasLengthConstraints());
        $this->assertFalse($param->hasNumericConstraints());
    }

    #[Test]
    public function it_can_create_parameter_with_inline_enum(): void
    {
        $param = new InlineParameterInfo(
            name: 'status',
            type: 'string',
            required: true,
            rules: 'required|in:active,inactive,pending',
            description: 'Status',
            inlineEnum: ['active', 'inactive', 'pending'],
        );

        $this->assertTrue($param->hasEnum());
        $this->assertEquals(['active', 'inactive', 'pending'], $param->inlineEnum);
        $this->assertNull($param->enumInfo);
        $this->assertEquals(['active', 'inactive', 'pending'], $param->getEnumValues());
    }

    #[Test]
    public function it_can_create_parameter_with_backed_enum(): void
    {
        $enumInfo = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['active', 'inactive'],
            backingType: EnumBackingType::STRING,
        );

        $param = new InlineParameterInfo(
            name: 'status',
            type: 'string',
            required: true,
            rules: 'required',
            description: 'Status',
            enumInfo: $enumInfo,
        );

        $this->assertTrue($param->hasEnum());
        $this->assertNull($param->inlineEnum);
        $this->assertSame($enumInfo, $param->enumInfo);
        $this->assertEquals(['active', 'inactive'], $param->getEnumValues());
    }

    #[Test]
    public function it_can_create_file_upload_parameter(): void
    {
        $fileInfo = new FileUploadInfo(isImage: true, mimes: ['jpeg', 'png'], maxSize: 2048);

        $param = new InlineParameterInfo(
            name: 'avatar',
            type: 'file',
            required: true,
            rules: 'required|image|max:2048',
            description: 'User avatar image',
            format: 'binary',
            fileInfo: $fileInfo,
        );

        $this->assertTrue($param->isFileUpload());
        $this->assertEquals('binary', $param->format);
        $this->assertSame($fileInfo, $param->fileInfo);
    }

    #[Test]
    public function it_creates_from_array_with_basic_fields(): void
    {
        $data = [
            'name' => 'email',
            'type' => 'string',
            'required' => true,
            'rules' => 'required|email',
            'description' => 'Email address',
            'format' => 'email',
        ];

        $param = InlineParameterInfo::fromArray($data);

        $this->assertEquals('email', $param->name);
        $this->assertEquals('string', $param->type);
        $this->assertTrue($param->required);
        $this->assertEquals('required|email', $param->rules);
        $this->assertEquals('Email address', $param->description);
        $this->assertEquals('email', $param->format);
    }

    #[Test]
    public function it_creates_from_array_with_constraints(): void
    {
        $data = [
            'name' => 'age',
            'type' => 'integer',
            'required' => true,
            'rules' => 'required|integer|min:0|max:120',
            'description' => 'Age',
            'minimum' => 0,
            'maximum' => 120,
        ];

        $param = InlineParameterInfo::fromArray($data);

        $this->assertEquals(0, $param->minimum);
        $this->assertEquals(120, $param->maximum);
    }

    #[Test]
    public function it_creates_from_array_with_inline_enum(): void
    {
        $data = [
            'name' => 'status',
            'type' => 'string',
            'required' => true,
            'rules' => 'required|in:a,b,c',
            'description' => 'Status',
            'enum' => ['a', 'b', 'c'],
        ];

        $param = InlineParameterInfo::fromArray($data);

        $this->assertEquals(['a', 'b', 'c'], $param->inlineEnum);
        $this->assertNull($param->enumInfo);
    }

    #[Test]
    public function it_creates_from_array_with_enum_info_object(): void
    {
        $enumInfo = new EnumInfo('App\\Enums\\Status', ['active'], EnumBackingType::STRING);

        $data = [
            'name' => 'status',
            'type' => 'string',
            'required' => true,
            'rules' => 'required',
            'description' => 'Status',
            'enum' => $enumInfo,
        ];

        $param = InlineParameterInfo::fromArray($data);

        $this->assertSame($enumInfo, $param->enumInfo);
        $this->assertNull($param->inlineEnum);
    }

    #[Test]
    public function it_creates_from_array_with_enum_info_array(): void
    {
        $data = [
            'name' => 'status',
            'type' => 'string',
            'required' => true,
            'rules' => 'required',
            'description' => 'Status',
            'enum' => [
                'class' => 'App\\Enums\\Status',
                'values' => ['active', 'inactive'],
                'type' => 'string',
            ],
        ];

        $param = InlineParameterInfo::fromArray($data);

        $this->assertInstanceOf(EnumInfo::class, $param->enumInfo);
        $this->assertEquals('App\\Enums\\Status', $param->enumInfo->class);
        $this->assertNull($param->inlineEnum);
    }

    #[Test]
    public function it_creates_from_array_with_file_info(): void
    {
        $fileInfo = new FileUploadInfo(isImage: true);

        $data = [
            'name' => 'document',
            'type' => 'file',
            'required' => false,
            'rules' => 'file',
            'description' => 'Document',
            'format' => 'binary',
            'file_info' => $fileInfo,
        ];

        $param = InlineParameterInfo::fromArray($data);

        $this->assertSame($fileInfo, $param->fileInfo);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [
            'name' => 'field',
        ];

        $param = InlineParameterInfo::fromArray($data);

        $this->assertEquals('field', $param->name);
        $this->assertEquals('string', $param->type);
        $this->assertFalse($param->required);
        $this->assertEquals([], $param->rules);
        $this->assertEquals('', $param->description);
        $this->assertNull($param->format);
    }

    #[Test]
    public function it_converts_to_array_with_basic_fields(): void
    {
        $param = new InlineParameterInfo(
            name: 'email',
            type: 'string',
            required: true,
            rules: 'required|email',
            description: 'Email address',
            format: 'email',
        );

        $array = $param->toArray();

        $this->assertEquals([
            'name' => 'email',
            'type' => 'string',
            'required' => true,
            'rules' => 'required|email',
            'description' => 'Email address',
            'format' => 'email',
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_with_constraints(): void
    {
        $param = new InlineParameterInfo(
            name: 'age',
            type: 'integer',
            required: true,
            rules: 'required|integer|min:0|max:100',
            description: 'Age',
            minimum: 0,
            maximum: 100,
        );

        $array = $param->toArray();

        $this->assertArrayHasKey('minimum', $array);
        $this->assertArrayHasKey('maximum', $array);
        $this->assertEquals(0, $array['minimum']);
        $this->assertEquals(100, $array['maximum']);
    }

    #[Test]
    public function it_converts_to_array_with_inline_enum(): void
    {
        $param = new InlineParameterInfo(
            name: 'status',
            type: 'string',
            required: true,
            rules: 'required|in:a,b',
            description: 'Status',
            inlineEnum: ['a', 'b'],
        );

        $array = $param->toArray();

        $this->assertArrayHasKey('enum', $array);
        $this->assertEquals(['a', 'b'], $array['enum']);
    }

    #[Test]
    public function it_converts_to_array_with_enum_info(): void
    {
        $enumInfo = new EnumInfo('App\\Enums\\Status', ['active'], EnumBackingType::STRING);

        $param = new InlineParameterInfo(
            name: 'status',
            type: 'string',
            required: true,
            rules: 'required',
            description: 'Status',
            enumInfo: $enumInfo,
        );

        $array = $param->toArray();

        $this->assertArrayHasKey('enum', $array);
        $this->assertIsArray($array['enum']);
        $this->assertEquals($enumInfo->toArray(), $array['enum']);
    }

    #[Test]
    public function it_omits_null_optional_fields_in_to_array(): void
    {
        $param = new InlineParameterInfo(
            name: 'simple',
            type: 'string',
            required: false,
            rules: [],
            description: 'Simple field',
        );

        $array = $param->toArray();

        $this->assertArrayNotHasKey('format', $array);
        $this->assertArrayNotHasKey('minLength', $array);
        $this->assertArrayNotHasKey('maxLength', $array);
        $this->assertArrayNotHasKey('minimum', $array);
        $this->assertArrayNotHasKey('maximum', $array);
        $this->assertArrayNotHasKey('enum', $array);
        $this->assertArrayNotHasKey('file_info', $array);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new InlineParameterInfo(
            name: 'email',
            type: 'string',
            required: true,
            rules: 'required|email|max:255',
            description: 'Email address',
            format: 'email',
            maxLength: 255,
        );

        $restored = InlineParameterInfo::fromArray($original->toArray());

        $this->assertEquals($original->name, $restored->name);
        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->required, $restored->required);
        $this->assertEquals($original->rules, $restored->rules);
        $this->assertEquals($original->description, $restored->description);
        $this->assertEquals($original->format, $restored->format);
        $this->assertEquals($original->maxLength, $restored->maxLength);
    }

    #[Test]
    public function it_survives_round_trip_with_constraints(): void
    {
        $original = new InlineParameterInfo(
            name: 'score',
            type: 'integer',
            required: true,
            rules: 'required|integer|min:0|max:100',
            description: 'Score',
            minimum: 0,
            maximum: 100,
        );

        $restored = InlineParameterInfo::fromArray($original->toArray());

        $this->assertEquals($original->minimum, $restored->minimum);
        $this->assertEquals($original->maximum, $restored->maximum);
    }

    #[Test]
    public function it_has_enum_returns_false_when_no_enum(): void
    {
        $param = new InlineParameterInfo(
            name: 'field',
            type: 'string',
            required: false,
            rules: [],
            description: 'Field',
        );

        $this->assertFalse($param->hasEnum());
    }

    #[Test]
    public function it_get_enum_values_returns_null_when_no_enum(): void
    {
        $param = new InlineParameterInfo(
            name: 'field',
            type: 'string',
            required: false,
            rules: [],
            description: 'Field',
        );

        $this->assertNull($param->getEnumValues());
    }

    #[Test]
    public function it_has_constraints_returns_false_when_no_constraints(): void
    {
        $param = new InlineParameterInfo(
            name: 'field',
            type: 'string',
            required: false,
            rules: [],
            description: 'Field',
        );

        $this->assertFalse($param->hasConstraints());
        $this->assertFalse($param->hasLengthConstraints());
        $this->assertFalse($param->hasNumericConstraints());
    }

    #[Test]
    public function it_handles_array_rules(): void
    {
        $param = new InlineParameterInfo(
            name: 'tags',
            type: 'array',
            required: false,
            rules: ['array', 'max:10'],
            description: 'Tags list',
        );

        $this->assertEquals('array', $param->type);
        $this->assertEquals(['array', 'max:10'], $param->rules);
    }

    #[Test]
    public function it_handles_various_formats(): void
    {
        $formats = ['email', 'uri', 'uuid', 'date', 'date-time', 'binary'];

        foreach ($formats as $format) {
            $param = new InlineParameterInfo(
                name: 'field',
                type: 'string',
                required: false,
                rules: [],
                description: 'Field',
                format: $format,
            );

            $this->assertEquals($format, $param->format);
        }
    }

    #[Test]
    public function it_creates_from_array_with_file_info_as_array(): void
    {
        $data = [
            'name' => 'document',
            'type' => 'file',
            'required' => true,
            'rules' => 'required|file|max:5120',
            'description' => 'Document upload',
            'format' => 'binary',
            'file_info' => [
                'is_image' => false,
                'mimes' => ['pdf', 'doc'],
                'max_size' => 5120,
            ],
        ];

        $param = InlineParameterInfo::fromArray($data);

        $this->assertInstanceOf(FileUploadInfo::class, $param->fileInfo);
        $this->assertFalse($param->fileInfo->isImage);
        $this->assertEquals(['pdf', 'doc'], $param->fileInfo->mimes);
        $this->assertEquals(5120, $param->fileInfo->maxSize);
    }

    #[Test]
    public function it_converts_to_array_with_length_constraints(): void
    {
        $param = new InlineParameterInfo(
            name: 'username',
            type: 'string',
            required: true,
            rules: 'required|string|min:3|max:20',
            description: 'Username',
            minLength: 3,
            maxLength: 20,
        );

        $array = $param->toArray();

        $this->assertArrayHasKey('minLength', $array);
        $this->assertArrayHasKey('maxLength', $array);
        $this->assertEquals(3, $array['minLength']);
        $this->assertEquals(20, $array['maxLength']);
    }

    #[Test]
    public function it_survives_round_trip_with_file_info(): void
    {
        $fileInfo = new FileUploadInfo(
            isImage: true,
            mimes: ['jpeg', 'png'],
            maxSize: 2048
        );

        $original = new InlineParameterInfo(
            name: 'avatar',
            type: 'file',
            required: true,
            rules: 'required|image|max:2048',
            description: 'User avatar',
            format: 'binary',
            fileInfo: $fileInfo,
        );

        $restored = InlineParameterInfo::fromArray($original->toArray());

        $this->assertTrue($restored->isFileUpload());
        $this->assertInstanceOf(FileUploadInfo::class, $restored->fileInfo);
        $this->assertEquals($original->fileInfo->isImage, $restored->fileInfo->isImage);
        $this->assertEquals($original->fileInfo->mimes, $restored->fileInfo->mimes);
        $this->assertEquals($original->fileInfo->maxSize, $restored->fileInfo->maxSize);
    }
}
