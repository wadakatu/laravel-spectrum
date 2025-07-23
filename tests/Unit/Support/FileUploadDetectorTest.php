<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\FileUploadDetector;
use PHPUnit\Framework\TestCase;
use Illuminate\Validation\Rules\File;

class FileUploadDetectorTest extends TestCase
{
    private FileUploadDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new FileUploadDetector();
    }

    public function testExtractFileRulesFromStringRules(): void
    {
        $rules = [
            'name' => 'required|string',
            'avatar' => 'required|image|max:2048',
            'document' => 'file|mimes:pdf,doc|max:5120',
            'email' => 'required|email',
        ];

        $fileRules = $this->detector->extractFileRules($rules);

        $this->assertArrayHasKey('avatar', $fileRules);
        $this->assertArrayHasKey('document', $fileRules);
        $this->assertArrayNotHasKey('name', $fileRules);
        $this->assertArrayNotHasKey('email', $fileRules);
        $this->assertContains('image', $fileRules['avatar']);
        $this->assertContains('file', $fileRules['document']);
    }

    public function testExtractFileRulesFromArrayRules(): void
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'photo' => ['required', 'image', 'dimensions:min_width=100'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ];

        $fileRules = $this->detector->extractFileRules($rules);

        $this->assertArrayHasKey('photo', $fileRules);
        $this->assertArrayHasKey('attachment', $fileRules);
        $this->assertArrayNotHasKey('title', $fileRules);
    }

    public function testExtractFileRulesFromRuleObjects(): void
    {
        $rules = [
            'video' => [
                'required',
                File::types(['mp4', 'avi'])->max(50 * 1024),
            ],
            'description' => 'required|string',
        ];

        $fileRules = $this->detector->extractFileRules($rules);

        $this->assertArrayHasKey('video', $fileRules);
        $this->assertArrayNotHasKey('description', $fileRules);
        $this->assertCount(2, $fileRules['video']);
        $this->assertInstanceOf(File::class, $fileRules['video'][1]);
    }

    public function testGetMimeTypeMapping(): void
    {
        $mapping = $this->detector->getMimeTypeMapping();

        $this->assertEquals('image/jpeg', $mapping['jpg']);
        $this->assertEquals('image/jpeg', $mapping['jpeg']);
        $this->assertEquals('image/png', $mapping['png']);
        $this->assertEquals('application/pdf', $mapping['pdf']);
        $this->assertEquals('application/msword', $mapping['doc']);
        $this->assertEquals('video/mp4', $mapping['mp4']);
        $this->assertEquals('text/csv', $mapping['csv']);
    }

    public function testExtractSizeConstraints(): void
    {
        $rules = ['required', 'file', 'min:100', 'max:2048'];

        $constraints = $this->detector->extractSizeConstraints($rules);

        $this->assertEquals(102400, $constraints['min']); // 100 KB in bytes
        $this->assertEquals(2097152, $constraints['max']); // 2048 KB in bytes
    }

    public function testExtractSizeConstraintsFromString(): void
    {
        $rules = ['required|file|min:50|max:1024'];

        $constraints = $this->detector->extractSizeConstraints($rules);

        $this->assertEquals(51200, $constraints['min']); // 50 KB in bytes
        $this->assertEquals(1048576, $constraints['max']); // 1024 KB in bytes
    }

    public function testExtractSizeConstraintsEmpty(): void
    {
        $rules = ['required', 'image'];

        $constraints = $this->detector->extractSizeConstraints($rules);

        $this->assertArrayNotHasKey('min', $constraints);
        $this->assertArrayNotHasKey('max', $constraints);
    }

    public function testExtractDimensionConstraints(): void
    {
        $rules = [
            'required',
            'image',
            'dimensions:min_width=100,min_height=200,max_width=1000,max_height=2000,ratio=3/2',
        ];

        $dimensions = $this->detector->extractDimensionConstraints($rules);

        $this->assertEquals(100, $dimensions['min_width']);
        $this->assertEquals(200, $dimensions['min_height']);
        $this->assertEquals(1000, $dimensions['max_width']);
        $this->assertEquals(2000, $dimensions['max_height']);
        $this->assertEquals('3/2', $dimensions['ratio']);
    }

    public function testExtractDimensionConstraintsFromString(): void
    {
        $rules = ['required|image|dimensions:width=500,height=500'];

        $dimensions = $this->detector->extractDimensionConstraints($rules);

        $this->assertEquals(500, $dimensions['width']);
        $this->assertEquals(500, $dimensions['height']);
    }

    public function testExtractDimensionConstraintsEmpty(): void
    {
        $rules = ['required', 'file'];

        $dimensions = $this->detector->extractDimensionConstraints($rules);

        $this->assertEmpty($dimensions);
    }

    public function testExtractFileRulesHandlesComplexPatterns(): void
    {
        $rules = [
            'documents' => 'array|max:5',
            'documents.*' => 'file|mimes:pdf,doc,docx|max:10240',
            'images.*.file' => 'required|image|max:5120',
            'images.*.caption' => 'required|string|max:255',
        ];

        $fileRules = $this->detector->extractFileRules($rules);

        $this->assertArrayHasKey('documents.*', $fileRules);
        $this->assertArrayHasKey('images.*.file', $fileRules);
        $this->assertArrayNotHasKey('documents', $fileRules);
        $this->assertArrayNotHasKey('images.*.caption', $fileRules);
    }
}