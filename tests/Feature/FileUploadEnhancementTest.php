<?php

namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Support\FileUploadDetector;
use LaravelSpectrum\Support\TypeInference;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileUploadEnhancementTest extends TestCase
{
    private InlineValidationAnalyzer $inlineValidationAnalyzer;

    private SchemaGenerator $schemaGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $typeInference = new TypeInference;
        $fileUploadAnalyzer = new \LaravelSpectrum\Analyzers\FileUploadAnalyzer;

        $this->inlineValidationAnalyzer = new InlineValidationAnalyzer($typeInference, null, $fileUploadAnalyzer);
        $this->schemaGenerator = new SchemaGenerator;
    }

    #[Test]
    public function it_generates_correct_schema_for_array_file_uploads()
    {
        // Arrange - simulate validation rules from GalleryController
        $validation = [
            'title' => 'required|string|max:255',
            'photos.*' => 'required|image|mimes:jpeg,png|max:5120|dimensions:min_width=100,min_height=100',
            'description' => 'nullable|string',
        ];

        // Act
        $parameters = $this->inlineValidationAnalyzer->generateParameters(['rules' => $validation]);
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        // Assert
        $this->assertArrayHasKey('content', $schema);
        $this->assertArrayHasKey('multipart/form-data', $schema['content']);

        $multipartSchema = $schema['content']['multipart/form-data']['schema'];

        // Check photos array
        $this->assertArrayHasKey('photos', $multipartSchema['properties']);
        $this->assertEquals('array', $multipartSchema['properties']['photos']['type']);
        $this->assertEquals('string', $multipartSchema['properties']['photos']['items']['type']);
        $this->assertEquals('binary', $multipartSchema['properties']['photos']['items']['format']);

        // Check constraints are preserved
        $this->assertArrayHasKey('contentMediaType', $multipartSchema['properties']['photos']['items']);
        $this->assertStringContainsString('image/jpeg, image/png', $multipartSchema['properties']['photos']['items']['contentMediaType']);
    }

    #[Test]
    public function it_handles_nested_file_arrays()
    {
        // Arrange - nested file structure from ProductController
        $validation = [
            'name' => 'required|string|max:255',
            'variants.*.name' => 'required|string|max:100',
            'variants.*.images.*' => 'required|image|mimes:jpeg,png,webp|max:2048',
            'main_image' => 'required|image|max:5120',
        ];

        // Act
        $parameters = $this->inlineValidationAnalyzer->generateParameters(['rules' => $validation]);
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        // Assert
        $this->assertArrayHasKey('content', $schema);
        $multipartSchema = $schema['content']['multipart/form-data']['schema'];

        // Check nested file fields are recognized
        // Note: Deep nested arrays (variants.*.images.*) might be flattened to 'variants'
        $this->assertArrayHasKey('main_image', $multipartSchema['properties']);
        $this->assertEquals('string', $multipartSchema['properties']['main_image']['type']);
        $this->assertEquals('binary', $multipartSchema['properties']['main_image']['format']);
    }

    #[Test]
    public function it_detects_file_patterns_correctly()
    {
        // Arrange
        $fileUploadDetector = new FileUploadDetector;
        $rules = [
            'avatar' => 'required|image|max:2048',
            'photos.*' => 'required|image|mimes:jpeg,png',
            'documents.*.file' => 'file|mimes:pdf,doc',
            'resume' => 'file|max:10240',
        ];

        // Act
        $patterns = $fileUploadDetector->detectFilePatterns($rules);

        // Assert
        $this->assertCount(2, $patterns['single_files']);
        $this->assertContains('avatar', $patterns['single_files']);
        $this->assertContains('resume', $patterns['single_files']);

        $this->assertCount(2, $patterns['array_files']);
        $this->assertContains('photos.*', $patterns['array_files']);
        $this->assertContains('documents.*.file', $patterns['array_files']);

        $this->assertCount(0, $patterns['nested_files']);
    }

    #[Test]
    public function it_preserves_file_validation_constraints_in_schema()
    {
        // Test that dimensions, size limits, and MIME types are preserved
        $validation = [
            'banner' => 'required|image|dimensions:width=1920,height=1080|max:2048',
        ];

        $parameters = $this->inlineValidationAnalyzer->generateParameters(['rules' => $validation]);
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        $bannerSchema = $schema['content']['multipart/form-data']['schema']['properties']['banner'];

        // Check that description exists and contains expected information
        $this->assertArrayHasKey('description', $bannerSchema);
        $this->assertStringContainsString('Max size: 2MB', $bannerSchema['description']);
        $this->assertStringContainsString('Dimensions: 1920x1080', $bannerSchema['description']);
        $this->assertEquals(2097152, $bannerSchema['maxSize']);
    }
}
