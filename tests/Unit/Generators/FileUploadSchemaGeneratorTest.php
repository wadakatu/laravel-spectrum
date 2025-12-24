<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\FileUploadSchemaGenerator;
use PHPUnit\Framework\TestCase;

class FileUploadSchemaGeneratorTest extends TestCase
{
    private FileUploadSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new FileUploadSchemaGenerator;
    }

    public function test_generate_basic_file_schema(): void
    {
        $fileField = [
            'type' => 'file',
            'is_image' => false,
            'mimes' => [],
            'max_size' => null,
        ];

        $schema = $this->generator->generate($fileField);

        $this->assertEquals('string', $schema['type']);
        $this->assertEquals('binary', $schema['format']);
        $this->assertArrayNotHasKey('description', $schema);
    }

    public function test_generate_file_schema_with_description(): void
    {
        $fileField = [
            'type' => 'file',
            'is_image' => true,
            'mimes' => ['jpeg', 'png'],
            'max_size' => 2097152, // 2MB in bytes
        ];

        $schema = $this->generator->generate($fileField);

        $this->assertEquals('string', $schema['type']);
        $this->assertEquals('binary', $schema['format']);
        $this->assertArrayHasKey('description', $schema);
        $this->assertStringContainsString('Allowed types: jpeg, png', $schema['description']);
        $this->assertStringContainsString('Max size: 2MB', $schema['description']);
    }

    public function test_generate_file_schema_with_all_constraints(): void
    {
        $fileField = [
            'type' => 'file',
            'is_image' => true,
            'mimes' => ['jpeg', 'png', 'gif'],
            'max_size' => 5242880, // 5MB
            'min_size' => 102400, // 100KB
            'dimensions' => [
                'min_width' => 100,
                'min_height' => 100,
                'max_width' => 2000,
                'max_height' => 2000,
            ],
        ];

        $schema = $this->generator->generate($fileField);

        $this->assertEquals('string', $schema['type']);
        $this->assertEquals('binary', $schema['format']);
        $this->assertStringContainsString('Allowed types: jpeg, png, gif', $schema['description']);
        $this->assertStringContainsString('Max size: 5MB', $schema['description']);
        $this->assertStringContainsString('Min size: 100KB', $schema['description']);
        $this->assertStringContainsString('Min dimensions: 100x100', $schema['description']);
        $this->assertStringContainsString('Max dimensions: 2000x2000', $schema['description']);
    }

    public function test_generate_multipart_schema(): void
    {
        $fields = [
            'title' => [
                'type' => 'string',
                'required' => true,
                'maxLength' => 255,
            ],
            'description' => [
                'type' => 'string',
                'required' => true,
            ],
        ];

        $fileFields = [
            'thumbnail' => [
                'type' => 'string',
                'format' => 'binary',
                'description' => 'Allowed types: jpeg, png. Max size: 2MB',
                'required' => true,
            ],
            'document' => [
                'type' => 'string',
                'format' => 'binary',
                'description' => 'Allowed types: pdf. Max size: 10MB',
                'required' => false,
            ],
        ];

        $schema = $this->generator->generateMultipartSchema($fields, $fileFields);

        $this->assertArrayHasKey('content', $schema);
        $this->assertArrayHasKey('multipart/form-data', $schema['content']);

        $multipartSchema = $schema['content']['multipart/form-data']['schema'];
        $this->assertEquals('object', $multipartSchema['type']);

        $properties = $multipartSchema['properties'];
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('description', $properties);
        $this->assertArrayHasKey('thumbnail', $properties);
        $this->assertArrayHasKey('document', $properties);

        $this->assertEquals('string', $properties['thumbnail']['type']);
        $this->assertEquals('binary', $properties['thumbnail']['format']);

        $required = $multipartSchema['required'];
        $this->assertContains('title', $required);
        $this->assertContains('description', $required);
        $this->assertContains('thumbnail', $required);
        $this->assertNotContains('document', $required);
    }

    public function test_generate_multipart_schema_with_array_files(): void
    {
        $fields = [
            'title' => [
                'type' => 'string',
                'required' => true,
            ],
        ];

        $fileFields = [
            'photos' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'format' => 'binary',
                    'description' => 'Image files. Max size: 5MB',
                ],
                'required' => true,
            ],
        ];

        $schema = $this->generator->generateMultipartSchema($fields, $fileFields);

        $properties = $schema['content']['multipart/form-data']['schema']['properties'];
        $this->assertArrayHasKey('photos', $properties);
        $this->assertEquals('array', $properties['photos']['type']);
        $this->assertEquals('string', $properties['photos']['items']['type']);
        $this->assertEquals('binary', $properties['photos']['items']['format']);
    }

    public function test_generate_description_with_ratio(): void
    {
        $fileField = [
            'type' => 'file',
            'is_image' => true,
            'mimes' => ['jpeg'],
            'dimensions' => [
                'ratio' => '16/9',
            ],
        ];

        $schema = $this->generator->generate($fileField);

        $this->assertStringContainsString('Aspect ratio: 16/9', $schema['description']);
    }

    public function test_generate_description_with_exact_dimensions(): void
    {
        $fileField = [
            'type' => 'file',
            'is_image' => true,
            'dimensions' => [
                'width' => 1920,
                'height' => 1080,
            ],
        ];

        $schema = $this->generator->generate($fileField);

        $this->assertStringContainsString('Required dimensions: 1920x1080', $schema['description']);
    }
}
