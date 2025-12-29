<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use Illuminate\Validation\Rules\File;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\DTO\FileUploadInfo;
use PHPUnit\Framework\TestCase;

class FileUploadAnalyzerTest extends TestCase
{
    private FileUploadAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new FileUploadAnalyzer;
    }

    public function test_analyzes_basic_file_rule(): void
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

    public function test_analyzes_image_rule(): void
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

    public function test_analyzes_multiple_files(): void
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

    public function test_analyzes_mime_types(): void
    {
        $rules = [
            'video' => 'mimetypes:video/mp4,video/avi',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('video', $result);
        $this->assertEquals(['video/mp4', 'video/avi'], $result['video']['mime_types']);
    }

    public function test_analyzes_dimensions(): void
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

    public function test_analyzes_min_max_size(): void
    {
        $rules = [
            'document' => 'file|min:100|max:10240',
        ];

        $result = $this->analyzer->analyzeRules($rules);

        $this->assertArrayHasKey('document', $result);
        $this->assertEquals(102400, $result['document']['min_size']); // 100 KB in bytes
        $this->assertEquals(10485760, $result['document']['max_size']); // 10240 KB in bytes
    }

    public function test_analyzes_file_rule_object(): void
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

    public function test_analyzes_mixed_fields(): void
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

    public function test_infer_mime_types_for_image(): void
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

    public function test_infer_mime_types_from_extensions(): void
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

    public function test_is_multiple_files(): void
    {
        $this->assertTrue($this->analyzer->isMultipleFiles('photos.*'));
        $this->assertTrue($this->analyzer->isMultipleFiles('documents.*'));
        $this->assertTrue($this->analyzer->isMultipleFiles('files.*.document'));
        $this->assertFalse($this->analyzer->isMultipleFiles('photo'));
        $this->assertFalse($this->analyzer->isMultipleFiles('document'));
    }

    public function test_analyzes_optional_files(): void
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

    public function test_analyzes_complex_array_structure(): void
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

    // Tests for analyzeRulesToResult() returning FileUploadInfo DTOs

    public function test_analyze_rules_to_result_returns_file_upload_info_dto(): void
    {
        $rules = [
            'document' => 'required|file|max:2048',
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $this->assertArrayHasKey('document', $result);
        $this->assertInstanceOf(FileUploadInfo::class, $result['document']);
    }

    public function test_analyze_rules_to_result_basic_file(): void
    {
        $rules = [
            'document' => 'required|file|max:2048',
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $info = $result['document'];
        $this->assertFalse($info->isImage);
        $this->assertEquals(2097152, $info->maxSize); // 2048 KB in bytes
        $this->assertFalse($info->multiple);
    }

    public function test_analyze_rules_to_result_image(): void
    {
        $rules = [
            'avatar' => 'required|image|mimes:jpeg,png|max:1024',
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $info = $result['avatar'];
        $this->assertTrue($info->isImage);
        $this->assertEquals(['jpeg', 'png'], $info->mimes);
        $this->assertContains('image/jpeg', $info->mimeTypes);
        $this->assertContains('image/png', $info->mimeTypes);
        $this->assertEquals(1048576, $info->maxSize);
    }

    public function test_analyze_rules_to_result_multiple_files(): void
    {
        $rules = [
            'photos.*' => 'image|max:5120',
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $info = $result['photos.*'];
        $this->assertTrue($info->isImage);
        $this->assertTrue($info->multiple);
        $this->assertEquals(5242880, $info->maxSize);
    }

    public function test_analyze_rules_to_result_with_dimensions(): void
    {
        $rules = [
            'profile_pic' => 'image|dimensions:min_width=100,min_height=100,ratio=3/2',
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $info = $result['profile_pic'];
        $this->assertTrue($info->isImage);
        $this->assertNotNull($info->dimensions);
        $this->assertEquals(100, $info->dimensions->minWidth);
        $this->assertEquals(100, $info->dimensions->minHeight);
        $this->assertEquals('3/2', $info->dimensions->ratio);
    }

    public function test_analyze_rules_to_result_with_exact_dimensions(): void
    {
        $rules = [
            'banner' => 'image|dimensions:width=1920,height=1080',
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $info = $result['banner'];
        $this->assertTrue($info->isImage);
        $this->assertNotNull($info->dimensions);
        $this->assertEquals(1920, $info->dimensions->width);
        $this->assertEquals(1080, $info->dimensions->height);
        $this->assertNull($info->dimensions->minWidth);
        $this->assertNull($info->dimensions->maxWidth);
        $this->assertNull($info->dimensions->minHeight);
        $this->assertNull($info->dimensions->maxHeight);
    }

    public function test_analyze_rules_to_result_min_max_size(): void
    {
        $rules = [
            'document' => 'file|min:100|max:10240',
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $info = $result['document'];
        $this->assertEquals(102400, $info->minSize);
        $this->assertEquals(10485760, $info->maxSize);
    }

    public function test_analyze_rules_to_result_file_rule_object(): void
    {
        $rules = [
            'video' => [
                'required',
                File::types(['mp4', 'avi'])
                    ->min(1024)
                    ->max(50 * 1024),
            ],
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $info = $result['video'];
        $this->assertEquals(['mp4', 'avi'], $info->mimes);
        $this->assertEquals(1048576, $info->minSize);
        $this->assertEquals(52428800, $info->maxSize);
    }

    public function test_analyze_rules_to_result_mixed_fields(): void
    {
        $rules = [
            'title' => 'required|string',
            'thumbnail' => 'required|image',
            'document' => 'nullable|file|mimes:pdf',
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('thumbnail', $result);
        $this->assertArrayHasKey('document', $result);
        $this->assertArrayNotHasKey('title', $result);
    }

    public function test_analyze_rules_to_result_helper_methods(): void
    {
        $rules = [
            'image' => 'image|mimes:jpeg|max:1024',
        ];

        $result = $this->analyzer->analyzeRulesToResult($rules);

        $info = $result['image'];
        $this->assertTrue($info->isImageUpload());
        $this->assertTrue($info->hasSizeConstraints());
        $this->assertTrue($info->hasMimeRestrictions());
        $this->assertFalse($info->isMultipleUpload());
    }

    public function test_analyze_rules_to_result_to_array_compatibility(): void
    {
        $rules = [
            'document' => 'required|file|max:2048',
        ];

        $resultDto = $this->analyzer->analyzeRulesToResult($rules);
        $resultArray = $this->analyzer->analyzeRules($rules);

        // The array result should match the DTO's toArray output
        $this->assertEquals($resultArray['document']['type'], 'file');
        $this->assertEquals($resultArray['document']['max_size'], $resultDto['document']->maxSize);
    }
}
