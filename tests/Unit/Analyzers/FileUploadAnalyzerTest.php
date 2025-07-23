<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use PHPUnit\Framework\TestCase;
use Illuminate\Validation\Rules\File;

class FileUploadAnalyzerTest extends TestCase
{
    private FileUploadAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new FileUploadAnalyzer();
    }

    public function testAnalyzesBasicFileRule(): void
    {
        $rules = [
            'document' => 'required|file|max:2048',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('document', $result);
        $this->assertEquals('file', $result['document']['type']);
        $this->assertFalse($result['document']['is_image']);
        $this->assertEquals(2097152, $result['document']['max_size']); // 2048 KB in bytes
        $this->assertFalse($result['document']['multiple']);
    }

    public function testAnalyzesImageRule(): void
    {
        $rules = [
            'avatar' => 'required|image|mimes:jpeg,png|max:1024',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('avatar', $result);
        $this->assertEquals('file', $result['avatar']['type']);
        $this->assertTrue($result['avatar']['is_image']);
        $this->assertEquals(['jpeg', 'png'], $result['avatar']['mimes']);
        $this->assertContains('image/jpeg', $result['avatar']['mime_types']);
        $this->assertContains('image/png', $result['avatar']['mime_types']);
        $this->assertEquals(1048576, $result['avatar']['max_size']); // 1024 KB in bytes
    }

    public function testAnalyzesMultipleFiles(): void
    {
        $rules = [
            'photos' => 'required|array',
            'photos.*' => 'image|max:5120',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('photos.*', $result);
        $this->assertEquals('file', $result['photos.*']['type']);
        $this->assertTrue($result['photos.*']['is_image']);
        $this->assertTrue($result['photos.*']['multiple']);
        $this->assertEquals(5242880, $result['photos.*']['max_size']); // 5120 KB in bytes
    }

    public function testAnalyzesMimeTypes(): void
    {
        $rules = [
            'video' => 'mimetypes:video/mp4,video/avi',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('video', $result);
        $this->assertEquals(['video/mp4', 'video/avi'], $result['video']['mime_types']);
    }

    public function testAnalyzesDimensions(): void
    {
        $rules = [
            'profile_pic' => 'image|dimensions:min_width=100,min_height=100,ratio=3/2',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('profile_pic', $result);
        $this->assertEquals([
            'min_width' => 100,
            'min_height' => 100,
            'ratio' => '3/2',
        ], $result['profile_pic']['dimensions']);
    }

    public function testAnalyzesMinMaxSize(): void
    {
        $rules = [
            'document' => 'file|min:100|max:10240',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('document', $result);
        $this->assertEquals(102400, $result['document']['min_size']); // 100 KB in bytes
        $this->assertEquals(10485760, $result['document']['max_size']); // 10240 KB in bytes
    }

    public function testAnalyzesFileRuleObject(): void
    {
        $rules = [
            'video' => [
                'required',
                File::types(['mp4', 'avi'])
                    ->min(1024)
                    ->max(50 * 1024),
            ],
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('video', $result);
        $this->assertEquals('file', $result['video']['type']);
        $this->assertEquals(['mp4', 'avi'], $result['video']['mimes']);
        $this->assertEquals(1048576, $result['video']['min_size']); // 1024 KB in bytes
        $this->assertEquals(52428800, $result['video']['max_size']); // 50 * 1024 KB in bytes
    }

    public function testAnalyzesMixedFields(): void
    {
        $rules = [
            'title' => 'required|string',
            'description' => 'required|string',
            'thumbnail' => 'required|image',
            'document' => 'nullable|file|mimes:pdf',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('thumbnail', $result);
        $this->assertArrayHasKey('document', $result);
        $this->assertArrayNotHasKey('title', $result);
        $this->assertArrayNotHasKey('description', $result);
    }

    public function testInferMimeTypesForImage(): void
    {
        $fileRules = [
            'type' => 'file',
            'is_image' => true,
            'mimes' => [],
        ];

        $result = $this->analyzer->inferMimeTypes($fileRules);

        $this->assertContains('image/jpeg', $result);
        $this->assertContains('image/png', $result);
        $this->assertContains('image/gif', $result);
        $this->assertContains('image/bmp', $result);
        $this->assertContains('image/svg+xml', $result);
        $this->assertContains('image/webp', $result);
    }

    public function testInferMimeTypesFromExtensions(): void
    {
        $fileRules = [
            'mimes' => ['pdf', 'doc', 'docx'],
        ];

        $result = $this->analyzer->inferMimeTypes($fileRules);

        $this->assertEquals([
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], $result);
    }

    public function testIsMultipleFiles(): void
    {
        $this->assertTrue($this->analyzer->isMultipleFiles('photos.*'));
        $this->assertTrue($this->analyzer->isMultipleFiles('documents.*'));
        $this->assertTrue($this->analyzer->isMultipleFiles('files.*.document'));
        $this->assertFalse($this->analyzer->isMultipleFiles('photo'));
        $this->assertFalse($this->analyzer->isMultipleFiles('document'));
    }

    public function testAnalyzesOptionalFiles(): void
    {
        $rules = [
            'profile_image' => 'sometimes|image|mimes:jpeg,png|max:1024',
            'background_image' => 'nullable|image|dimensions:width=1920,height=1080',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('profile_image', $result);
        $this->assertArrayHasKey('background_image', $result);
        $this->assertTrue($result['profile_image']['is_image']);
        $this->assertTrue($result['background_image']['is_image']);
    }

    public function testAnalyzesComplexArrayStructure(): void
    {
        $rules = [
            'attachments' => 'array|max:5',
            'attachments.*' => 'file|max:5120',
            'images' => 'array',
            'images.*.file' => 'required|image',
            'images.*.caption' => 'required|string',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('attachments.*', $result);
        $this->assertArrayHasKey('images.*.file', $result);
        $this->assertArrayNotHasKey('images.*.caption', $result);
        $this->assertTrue($result['attachments.*']['multiple']);
        $this->assertTrue($result['images.*.file']['multiple']);
    }
}