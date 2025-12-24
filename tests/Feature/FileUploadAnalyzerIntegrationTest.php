<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Tests\Fixtures\FormRequests\FileUploadRequest;
use LaravelSpectrum\Tests\Fixtures\FormRequests\MultipleFilesRequest;
use LaravelSpectrum\Tests\TestCase;

class FileUploadAnalyzerIntegrationTest extends TestCase
{
    private FormRequestAnalyzer $formRequestAnalyzer;

    private InlineValidationAnalyzer $inlineValidationAnalyzer;

    private SchemaGenerator $schemaGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberFormRequest')->willReturnCallback(fn ($key, $callback) => $callback());

        // Register mock cache in container and get analyzers via DI
        $this->app->instance(DocumentationCache::class, $cache);
        $this->formRequestAnalyzer = $this->app->make(FormRequestAnalyzer::class);
        $this->inlineValidationAnalyzer = $this->app->make(InlineValidationAnalyzer::class);
        $this->schemaGenerator = new SchemaGenerator;
    }

    public function test_file_upload_request_generates_multipart_schema(): void
    {
        $parameters = $this->formRequestAnalyzer->analyze(FileUploadRequest::class);

        // Check file parameters
        $avatarParam = $this->findParameter($parameters, 'avatar');
        $this->assertNotNull($avatarParam);
        $this->assertEquals('file', $avatarParam['type']);
        $this->assertEquals('binary', $avatarParam['format']);
        // Debug: print actual parameter
        // var_dump($avatarParam);
        $this->assertStringContainsString('Profile Picture', $avatarParam['description']);
        $this->assertStringContainsString('Max size: 2MB', $avatarParam['description']);

        $resumeParam = $this->findParameter($parameters, 'resume');
        $this->assertNotNull($resumeParam);
        $this->assertEquals('file', $resumeParam['type']);
        $this->assertStringContainsString('Allowed types: pdf, doc, docx', $resumeParam['description']);
        $this->assertStringContainsString('Max size: 10MB', $resumeParam['description']);

        // Generate schema
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        // Check that it generates multipart/form-data
        $this->assertArrayHasKey('content', $schema);
        $this->assertArrayHasKey('multipart/form-data', $schema['content']);

        $multipartSchema = $schema['content']['multipart/form-data']['schema'];
        $this->assertEquals('object', $multipartSchema['type']);

        // Check properties
        $properties = $multipartSchema['properties'];
        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('email', $properties);
        $this->assertArrayHasKey('avatar', $properties);
        $this->assertArrayHasKey('resume', $properties);
        $this->assertArrayHasKey('portfolio', $properties);

        // Check file properties
        $this->assertEquals('string', $properties['avatar']['type']);
        $this->assertEquals('binary', $properties['avatar']['format']);
        $this->assertEquals('string', $properties['resume']['type']);
        $this->assertEquals('binary', $properties['resume']['format']);

        // Check required fields
        $this->assertContains('name', $multipartSchema['required']);
        $this->assertContains('email', $multipartSchema['required']);
        $this->assertContains('avatar', $multipartSchema['required']);
        $this->assertContains('resume', $multipartSchema['required']);
        $this->assertNotContains('portfolio', $multipartSchema['required']);
    }

    public function test_multiple_files_request_generates_array_schema(): void
    {
        $parameters = $this->formRequestAnalyzer->analyze(MultipleFilesRequest::class);

        // Check array file parameters
        $photosParam = $this->findParameter($parameters, 'photos.*');
        $this->assertNotNull($photosParam);
        $this->assertEquals('file', $photosParam['type']);
        $this->assertTrue($photosParam['file_info']['multiple']);
        $this->assertStringContainsString('Photo', $photosParam['description']);
        $this->assertStringContainsString('Max size: 5MB', $photosParam['description']);
        $this->assertStringContainsString('Min dimensions: 100x100', $photosParam['description']);

        // Generate schema
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        $this->assertArrayHasKey('content', $schema);
        $this->assertArrayHasKey('multipart/form-data', $schema['content']);

        $properties = $schema['content']['multipart/form-data']['schema']['properties'];

        // Check photos array - array notation is normalized
        $this->assertArrayHasKey('photos', $properties);
        $this->assertEquals('array', $properties['photos']['type']);
        $this->assertEquals('string', $properties['photos']['items']['type']);
        $this->assertEquals('binary', $properties['photos']['items']['format']);

        // Check documents array - array notation is normalized
        $this->assertArrayHasKey('documents', $properties);
        $this->assertEquals('array', $properties['documents']['type']);
    }

    public function test_inline_validation_with_file_upload(): void
    {
        $validation = [
            'rules' => [
                'title' => 'required|string|max:255',
                'thumbnail' => 'required|image|mimes:jpeg,png|max:1024',
                'attachment' => 'nullable|file|max:5120',
            ],
            'attributes' => [
                'title' => 'Article Title',
                'thumbnail' => 'Thumbnail Image',
                'attachment' => 'Additional File',
            ],
        ];

        $parameters = $this->inlineValidationAnalyzer->generateParameters($validation);

        // Check file parameters
        $thumbnailParam = $this->findParameter($parameters, 'thumbnail');
        $this->assertNotNull($thumbnailParam);
        $this->assertEquals('file', $thumbnailParam['type']);
        $this->assertStringContainsString('Allowed types: jpeg, png', $thumbnailParam['description']);
        $this->assertStringContainsString('Max size: 1MB', $thumbnailParam['description']);

        // Generate schema
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        $this->assertArrayHasKey('content', $schema);
        $this->assertArrayHasKey('multipart/form-data', $schema['content']);
    }

    public function test_mixed_content_with_files_and_regular_fields(): void
    {
        $validation = [
            'rules' => [
                'name' => 'required|string',
                'age' => 'required|integer|min:18',
                'profile_pic' => 'required|image',
                'documents' => 'array',
                'documents.*' => 'file|mimes:pdf',
                'bio' => 'nullable|string|max:1000',
            ],
        ];

        $parameters = $this->inlineValidationAnalyzer->generateParameters($validation);
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        $this->assertArrayHasKey('content', $schema);
        $properties = $schema['content']['multipart/form-data']['schema']['properties'];

        // Regular fields
        $this->assertEquals('string', $properties['name']['type']);
        $this->assertEquals('integer', $properties['age']['type']);
        $this->assertEquals('string', $properties['bio']['type']);

        // File fields
        $this->assertEquals('string', $properties['profile_pic']['type']);
        $this->assertEquals('binary', $properties['profile_pic']['format']);
        // Array notation is normalized
        $this->assertArrayHasKey('documents', $properties);
        $this->assertEquals('array', $properties['documents']['type']);
        $this->assertEquals('binary', $properties['documents']['items']['format']);
    }

    private function findParameter(array $parameters, string $name): ?array
    {
        foreach ($parameters as $param) {
            if ($param['name'] === $name) {
                return $param;
            }
        }

        return null;
    }
}
