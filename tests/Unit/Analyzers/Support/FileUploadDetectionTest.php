<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\Analyzers\Support\FormatInferrer;
use LaravelSpectrum\Analyzers\Support\ParameterBuilder;
use LaravelSpectrum\Analyzers\Support\RuleRequirementAnalyzer;
use LaravelSpectrum\Analyzers\Support\ValidationDescriptionGenerator;
use LaravelSpectrum\DTO\Collections\ValidationRuleCollection;
use LaravelSpectrum\DTO\ParameterDefinition;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test file upload detection for Issue #312:
 * "File validation rules (image, file, mimes) not detected as file uploads"
 */
class FileUploadDetectionTest extends TestCase
{
    private ParameterBuilder $builder;

    private FileUploadAnalyzer $fileUploadAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileUploadAnalyzer = new FileUploadAnalyzer;
        $enumAnalyzer = new EnumAnalyzer;

        $this->builder = new ParameterBuilder(
            new TypeInference,
            new RuleRequirementAnalyzer,
            new FormatInferrer,
            new ValidationDescriptionGenerator($enumAnalyzer),
            $enumAnalyzer,
            $this->fileUploadAnalyzer
        );
    }

    #[Test]
    public function it_detects_image_rule_as_file_upload(): void
    {
        // This is the format that the AST extractor returns
        $rules = [
            'avatar' => ['nullable', 'image'],
        ];

        // Verify ValidationRuleCollection detects it
        $collection = ValidationRuleCollection::from($rules['avatar']);
        $this->assertTrue($collection->hasFileRule(), 'ValidationRuleCollection should detect image rule');

        // Verify FileUploadAnalyzer detects it
        $fileFields = $this->fileUploadAnalyzer->analyzeRulesToResult($rules);
        $this->assertArrayHasKey('avatar', $fileFields, 'FileUploadAnalyzer should detect avatar as file field');

        // Verify ParameterBuilder builds it correctly
        $parameters = $this->builder->buildFromRules($rules);
        $avatar = $this->findParameter($parameters, 'avatar');

        $this->assertNotNull($avatar, 'avatar parameter should exist');
        $this->assertEquals('file', $avatar->type, 'avatar should have type=file');
        $this->assertEquals('binary', $avatar->format, 'avatar should have format=binary');
        $this->assertTrue($avatar->isFileUpload(), 'avatar should be marked as file upload');
    }

    #[Test]
    public function it_detects_file_rule_as_file_upload(): void
    {
        $rules = [
            'document' => ['required', 'file'],
        ];

        $parameters = $this->builder->buildFromRules($rules);
        $document = $this->findParameter($parameters, 'document');

        $this->assertNotNull($document, 'document parameter should exist');
        $this->assertEquals('file', $document->type, 'document should have type=file');
    }

    #[Test]
    public function it_detects_mimes_rule_as_file_upload(): void
    {
        $rules = [
            'resume' => ['nullable', 'mimes:pdf,doc,docx'],
        ];

        $parameters = $this->builder->buildFromRules($rules);
        $resume = $this->findParameter($parameters, 'resume');

        $this->assertNotNull($resume, 'resume parameter should exist');
        $this->assertEquals('file', $resume->type, 'resume should have type=file');
    }

    #[Test]
    public function it_detects_mimetypes_rule_as_file_upload(): void
    {
        $rules = [
            'report' => ['required', 'mimetypes:application/pdf'],
        ];

        $parameters = $this->builder->buildFromRules($rules);
        $report = $this->findParameter($parameters, 'report');

        $this->assertNotNull($report, 'report parameter should exist');
        $this->assertEquals('file', $report->type, 'report should have type=file');
    }

    #[Test]
    public function it_detects_file_rules_in_array_format(): void
    {
        // Test with array format rules (as might come from AST extraction)
        $rules = [
            'avatar' => ['nullable', 'image', 'Rule::dimensions()->minWidth(100)'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $avatar = $this->findParameter($parameters, 'avatar');
        $this->assertNotNull($avatar, 'avatar parameter should exist');
        $this->assertEquals('file', $avatar->type, 'avatar should have type=file');

        $resume = $this->findParameter($parameters, 'resume');
        $this->assertNotNull($resume, 'resume parameter should exist');
        $this->assertEquals('file', $resume->type, 'resume should have type=file');
    }

    #[Test]
    public function it_detects_image_with_dimensions_object(): void
    {
        // Simulate rules as they would be extracted from AST with Rule::dimensions()
        $rules = [
            'photo' => ['required', 'image', 'dimensions:min_width=100,min_height=100'],
        ];

        $parameters = $this->builder->buildFromRules($rules);
        $photo = $this->findParameter($parameters, 'photo');

        $this->assertNotNull($photo, 'photo parameter should exist');
        $this->assertEquals('file', $photo->type, 'photo should have type=file');
        $this->assertTrue($photo->isFileUpload(), 'photo should be marked as file upload');
    }

    #[Test]
    public function it_detects_file_uploads_in_conditional_rules(): void
    {
        // This is the actual fix for Issue #312: conditional rules now detect file uploads
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => ['type' => 'photo'],
                    'rules' => [
                        'avatar' => ['required', 'image', 'max:2048'],
                    ],
                ],
                [
                    'conditions' => ['type' => 'document'],
                    'rules' => [
                        'document' => ['required', 'file', 'mimes:pdf,doc'],
                    ],
                ],
            ],
            'merged_rules' => [
                'avatar' => ['required', 'image', 'max:2048'],
                'document' => ['required', 'file', 'mimes:pdf,doc'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $avatar = $this->findParameter($parameters, 'avatar');
        $this->assertNotNull($avatar, 'avatar parameter should exist');
        $this->assertEquals('file', $avatar->type, 'avatar should have type=file in conditional rules');
        $this->assertEquals('binary', $avatar->format, 'avatar should have format=binary');
        $this->assertTrue($avatar->isFileUpload(), 'avatar should be marked as file upload');

        $document = $this->findParameter($parameters, 'document');
        $this->assertNotNull($document, 'document parameter should exist');
        $this->assertEquals('file', $document->type, 'document should have type=file in conditional rules');
    }

    #[Test]
    public function it_detects_mixed_file_and_non_file_fields_in_conditional_rules(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [],
                    'rules' => [
                        'name' => ['required', 'string', 'max:255'],
                        'photo' => ['nullable', 'image'],
                        'resume' => ['nullable', 'mimes:pdf'],
                    ],
                ],
            ],
            'merged_rules' => [
                'name' => ['required', 'string', 'max:255'],
                'photo' => ['nullable', 'image'],
                'resume' => ['nullable', 'mimes:pdf'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        // Non-file field should remain string
        $name = $this->findParameter($parameters, 'name');
        $this->assertNotNull($name, 'name parameter should exist');
        $this->assertEquals('string', $name->type, 'name should remain type=string');
        $this->assertFalse($name->isFileUpload(), 'name should not be marked as file upload');

        // File fields should be detected
        $photo = $this->findParameter($parameters, 'photo');
        $this->assertNotNull($photo, 'photo parameter should exist');
        $this->assertEquals('file', $photo->type, 'photo should have type=file');
        $this->assertTrue($photo->isFileUpload(), 'photo should be marked as file upload');

        $resume = $this->findParameter($parameters, 'resume');
        $this->assertNotNull($resume, 'resume parameter should exist');
        $this->assertEquals('file', $resume->type, 'resume should have type=file');
        $this->assertTrue($resume->isFileUpload(), 'resume should be marked as file upload');
    }

    /**
     * @param  array<ParameterDefinition>  $parameters
     */
    private function findParameter(array $parameters, string $name): ?ParameterDefinition
    {
        foreach ($parameters as $param) {
            if ($param->name === $name) {
                return $param;
            }
        }

        return null;
    }
}
