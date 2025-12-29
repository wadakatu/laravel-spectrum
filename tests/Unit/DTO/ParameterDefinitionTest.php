<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\EnumBackingType;
use LaravelSpectrum\DTO\EnumInfo;
use LaravelSpectrum\DTO\FileUploadInfo;
use LaravelSpectrum\DTO\ParameterDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ParameterDefinitionTest extends TestCase
{
    #[Test]
    public function it_can_create_standard_parameter(): void
    {
        $param = new ParameterDefinition(
            name: 'email',
            in: 'body',
            required: true,
            type: 'string',
            description: 'User email address',
            example: 'user@example.com',
            validation: ['required', 'email'],
            format: 'email',
        );

        $this->assertEquals('email', $param->name);
        $this->assertEquals('body', $param->in);
        $this->assertTrue($param->required);
        $this->assertEquals('string', $param->type);
        $this->assertEquals('User email address', $param->description);
        $this->assertEquals('user@example.com', $param->example);
        $this->assertEquals(['required', 'email'], $param->validation);
        $this->assertEquals('email', $param->format);
        $this->assertNull($param->conditionalRequired);
        $this->assertNull($param->conditionalRules);
        $this->assertNull($param->enum);
        $this->assertNull($param->fileInfo);
    }

    #[Test]
    public function it_can_create_file_upload_parameter(): void
    {
        $fileInfo = new FileUploadInfo(isImage: true, mimes: ['jpeg', 'png']);

        $param = new ParameterDefinition(
            name: 'avatar',
            in: 'body',
            required: true,
            type: 'file',
            description: 'User avatar image',
            example: null,
            validation: ['required', 'image', 'max:2048'],
            format: 'binary',
            fileInfo: $fileInfo,
        );

        $this->assertEquals('avatar', $param->name);
        $this->assertEquals('file', $param->type);
        $this->assertEquals('binary', $param->format);
        $this->assertSame($fileInfo, $param->fileInfo);
    }

    #[Test]
    public function it_can_create_parameter_with_enum(): void
    {
        $enumInfo = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['active', 'inactive', 'pending'],
            backingType: EnumBackingType::STRING,
        );

        $param = new ParameterDefinition(
            name: 'status',
            in: 'body',
            required: true,
            type: 'string',
            description: 'User status',
            example: 'active',
            validation: ['required'],
            enum: $enumInfo,
        );

        $this->assertSame($enumInfo, $param->enum);
        $this->assertTrue($param->hasEnum());
    }

    #[Test]
    public function it_can_create_parameter_with_conditional_rules(): void
    {
        $conditionalRules = [
            ['conditions' => ['type' => 'http_method', 'method' => 'POST'], 'rules' => ['required']],
        ];

        $param = new ParameterDefinition(
            name: 'password',
            in: 'body',
            required: false,
            type: 'string',
            description: 'User password',
            example: 'secret123',
            validation: ['sometimes', 'string', 'min:8'],
            conditionalRequired: true,
            conditionalRules: $conditionalRules,
        );

        $this->assertTrue($param->conditionalRequired);
        $this->assertEquals($conditionalRules, $param->conditionalRules);
        $this->assertTrue($param->hasConditionalRules());
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'name' => 'username',
            'in' => 'body',
            'required' => true,
            'type' => 'string',
            'description' => 'Username for login',
            'example' => 'john_doe',
            'validation' => ['required', 'string', 'max:255'],
            'format' => null,
        ];

        $param = ParameterDefinition::fromArray($data);

        $this->assertEquals('username', $param->name);
        $this->assertEquals('body', $param->in);
        $this->assertTrue($param->required);
        $this->assertEquals('string', $param->type);
        $this->assertEquals('Username for login', $param->description);
        $this->assertEquals('john_doe', $param->example);
        $this->assertEquals(['required', 'string', 'max:255'], $param->validation);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [
            'name' => 'field',
        ];

        $param = ParameterDefinition::fromArray($data);

        $this->assertEquals('field', $param->name);
        $this->assertEquals('body', $param->in);
        $this->assertFalse($param->required);
        $this->assertEquals('string', $param->type);
        $this->assertEquals('', $param->description);
        $this->assertNull($param->example);
        $this->assertEquals([], $param->validation);
    }

    #[Test]
    public function it_creates_from_array_with_enum_info(): void
    {
        $enumInfo = new EnumInfo(
            class: 'App\\Enums\\Priority',
            values: [1, 2, 3],
            backingType: EnumBackingType::INTEGER,
        );

        $data = [
            'name' => 'priority',
            'in' => 'body',
            'required' => true,
            'type' => 'integer',
            'description' => 'Task priority',
            'example' => 1,
            'validation' => ['required'],
            'enum' => $enumInfo,
        ];

        $param = ParameterDefinition::fromArray($data);

        $this->assertSame($enumInfo, $param->enum);
        $this->assertTrue($param->hasEnum());
    }

    #[Test]
    public function it_creates_from_array_with_file_info(): void
    {
        $fileInfo = new FileUploadInfo(isImage: true, maxSize: 2048);

        $data = [
            'name' => 'document',
            'in' => 'body',
            'required' => false,
            'type' => 'file',
            'description' => 'Upload document',
            'example' => null,
            'validation' => ['file'],
            'format' => 'binary',
            'file_info' => $fileInfo,
        ];

        $param = ParameterDefinition::fromArray($data);

        $this->assertSame($fileInfo, $param->fileInfo);
        $this->assertTrue($param->isFileUpload());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $param = new ParameterDefinition(
            name: 'email',
            in: 'body',
            required: true,
            type: 'string',
            description: 'Email address',
            example: 'test@example.com',
            validation: ['required', 'email'],
            format: 'email',
        );

        $array = $param->toArray();

        $this->assertEquals([
            'name' => 'email',
            'in' => 'body',
            'required' => true,
            'type' => 'string',
            'description' => 'Email address',
            'example' => 'test@example.com',
            'validation' => ['required', 'email'],
            'format' => 'email',
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_with_optional_fields(): void
    {
        $enumInfo = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['active', 'inactive'],
            backingType: EnumBackingType::STRING,
        );

        $param = new ParameterDefinition(
            name: 'status',
            in: 'body',
            required: true,
            type: 'string',
            description: 'Status',
            example: 'active',
            validation: ['required'],
            conditionalRequired: true,
            conditionalRules: [['conditions' => [], 'rules' => ['required']]],
            enum: $enumInfo,
        );

        $array = $param->toArray();

        $this->assertArrayHasKey('conditional_required', $array);
        $this->assertTrue($array['conditional_required']);
        $this->assertArrayHasKey('conditional_rules', $array);
        $this->assertArrayHasKey('enum', $array);
        // enum is now serialized to array via toArray()
        $this->assertIsArray($array['enum']);
        $this->assertEquals($enumInfo->toArray(), $array['enum']);
    }

    #[Test]
    public function it_omits_null_optional_fields_in_to_array(): void
    {
        $param = new ParameterDefinition(
            name: 'simple',
            in: 'body',
            required: false,
            type: 'string',
            description: 'Simple field',
            example: null,
            validation: [],
        );

        $array = $param->toArray();

        $this->assertArrayNotHasKey('format', $array);
        $this->assertArrayNotHasKey('conditional_required', $array);
        $this->assertArrayNotHasKey('conditional_rules', $array);
        $this->assertArrayNotHasKey('enum', $array);
        $this->assertArrayNotHasKey('file_info', $array);
    }

    #[Test]
    public function it_is_file_upload_returns_true_for_file_type(): void
    {
        $param = new ParameterDefinition(
            name: 'upload',
            in: 'body',
            required: true,
            type: 'file',
            description: 'File upload',
            example: null,
            validation: ['required', 'file'],
        );

        $this->assertTrue($param->isFileUpload());
    }

    #[Test]
    public function it_is_file_upload_returns_false_for_non_file_type(): void
    {
        $param = new ParameterDefinition(
            name: 'name',
            in: 'body',
            required: true,
            type: 'string',
            description: 'Name field',
            example: 'John',
            validation: ['required', 'string'],
        );

        $this->assertFalse($param->isFileUpload());
    }

    #[Test]
    public function it_has_conditional_rules_returns_true_when_present(): void
    {
        $param = new ParameterDefinition(
            name: 'field',
            in: 'body',
            required: false,
            type: 'string',
            description: 'Field',
            example: null,
            validation: [],
            conditionalRules: [['conditions' => [], 'rules' => ['required']]],
        );

        $this->assertTrue($param->hasConditionalRules());
    }

    #[Test]
    public function it_has_conditional_rules_returns_false_when_null(): void
    {
        $param = new ParameterDefinition(
            name: 'field',
            in: 'body',
            required: false,
            type: 'string',
            description: 'Field',
            example: null,
            validation: [],
        );

        $this->assertFalse($param->hasConditionalRules());
    }

    #[Test]
    public function it_has_conditional_rules_returns_false_when_empty(): void
    {
        $param = new ParameterDefinition(
            name: 'field',
            in: 'body',
            required: false,
            type: 'string',
            description: 'Field',
            example: null,
            validation: [],
            conditionalRules: [],
        );

        $this->assertFalse($param->hasConditionalRules());
    }

    #[Test]
    public function it_has_enum_returns_true_when_enum_info_present(): void
    {
        $enumInfo = new EnumInfo('App\\Enums\\A', ['a'], EnumBackingType::STRING);

        $param = new ParameterDefinition(
            name: 'field',
            in: 'body',
            required: false,
            type: 'string',
            description: 'Field',
            example: null,
            validation: [],
            enum: $enumInfo,
        );

        $this->assertTrue($param->hasEnum());
    }

    #[Test]
    public function it_has_enum_returns_false_when_null(): void
    {
        $param = new ParameterDefinition(
            name: 'field',
            in: 'body',
            required: false,
            type: 'string',
            description: 'Field',
            example: null,
            validation: [],
        );

        $this->assertFalse($param->hasEnum());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new ParameterDefinition(
            name: 'email',
            in: 'body',
            required: true,
            type: 'string',
            description: 'Email address',
            example: 'test@example.com',
            validation: ['required', 'email'],
            format: 'email',
        );

        $restored = ParameterDefinition::fromArray($original->toArray());

        $this->assertEquals($original->name, $restored->name);
        $this->assertEquals($original->in, $restored->in);
        $this->assertEquals($original->required, $restored->required);
        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->description, $restored->description);
        $this->assertEquals($original->example, $restored->example);
        $this->assertEquals($original->validation, $restored->validation);
        $this->assertEquals($original->format, $restored->format);
    }

    #[Test]
    public function it_handles_integer_type(): void
    {
        $param = new ParameterDefinition(
            name: 'age',
            in: 'body',
            required: true,
            type: 'integer',
            description: 'User age',
            example: 25,
            validation: ['required', 'integer', 'min:0'],
        );

        $this->assertEquals('integer', $param->type);
        $this->assertEquals(25, $param->example);
    }

    #[Test]
    public function it_handles_boolean_type(): void
    {
        $param = new ParameterDefinition(
            name: 'active',
            in: 'body',
            required: false,
            type: 'boolean',
            description: 'Is active flag',
            example: true,
            validation: ['boolean'],
        );

        $this->assertEquals('boolean', $param->type);
        $this->assertTrue($param->example);
    }

    #[Test]
    public function it_handles_array_type(): void
    {
        $param = new ParameterDefinition(
            name: 'tags',
            in: 'body',
            required: false,
            type: 'array',
            description: 'List of tags',
            example: ['tag1', 'tag2'],
            validation: ['array'],
        );

        $this->assertEquals('array', $param->type);
        $this->assertEquals(['tag1', 'tag2'], $param->example);
    }
}
