<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\DTO\OpenApiRequestBody;
use LaravelSpectrum\Generators\RequestBodyGenerator;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class RequestBodyGeneratorTest extends TestCase
{
    protected RequestBodyGenerator $generator;

    protected $mockRequestAnalyzer;

    protected $mockInlineValidationAnalyzer;

    protected $mockSchemaGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRequestAnalyzer = Mockery::mock(FormRequestAnalyzer::class);
        $this->mockInlineValidationAnalyzer = Mockery::mock(InlineValidationAnalyzer::class);
        $this->mockSchemaGenerator = Mockery::mock(SchemaGenerator::class);

        $this->generator = new RequestBodyGenerator(
            $this->mockRequestAnalyzer,
            $this->mockInlineValidationAnalyzer,
            $this->mockSchemaGenerator
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_null_when_no_validation(): void
    {
        $controllerInfo = [];
        $route = ['uri' => 'api/users'];

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertNull($result);
    }

    #[Test]
    public function it_generates_request_body_from_form_request(): void
    {
        $controllerInfo = ['formRequest' => 'App\Http\Requests\StoreUserRequest'];
        $route = ['uri' => 'api/users'];

        $this->mockRequestAnalyzer->shouldReceive('analyzeWithConditionalRules')
            ->once()
            ->with('App\Http\Requests\StoreUserRequest')
            ->andReturn(['parameters' => [], 'conditional_rules' => []]);

        $this->mockRequestAnalyzer->shouldReceive('analyze')
            ->once()
            ->with('App\Http\Requests\StoreUserRequest')
            ->andReturn([
                ['name' => 'email', 'type' => 'string', 'required' => true],
                ['name' => 'password', 'type' => 'string', 'required' => true],
            ]);

        $this->mockSchemaGenerator->shouldReceive('generateFromParameters')
            ->once()
            ->andReturn([
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string'],
                    'password' => ['type' => 'string'],
                ],
                'required' => ['email', 'password'],
            ]);

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertInstanceOf(OpenApiRequestBody::class, $result);
        $this->assertTrue($result->required);
        $this->assertTrue($result->isJson());
        $this->assertEquals('object', $result->getSchemaFor('application/json')['type']);
    }

    #[Test]
    public function it_generates_request_body_from_inline_validation(): void
    {
        $controllerInfo = [
            'inlineValidation' => [
                'rules' => [
                    'name' => ['required', 'string'],
                ],
            ],
        ];
        $route = ['uri' => 'api/users'];

        $this->mockInlineValidationAnalyzer->shouldReceive('generateParameters')
            ->once()
            ->andReturn([
                ['name' => 'name', 'type' => 'string', 'required' => true],
            ]);

        $this->mockSchemaGenerator->shouldReceive('generateFromParameters')
            ->once()
            ->andReturn([
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
                'required' => ['name'],
            ]);

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertInstanceOf(OpenApiRequestBody::class, $result);
        $this->assertTrue($result->isJson());
    }

    #[Test]
    public function it_generates_request_body_with_conditional_rules(): void
    {
        $controllerInfo = ['formRequest' => 'App\Http\Requests\ConditionalRequest'];
        $route = ['uri' => 'api/users'];

        $conditionalRules = [
            'rules_sets' => [
                ['type' => 'individual'],
                ['type' => 'company'],
            ],
        ];

        $this->mockRequestAnalyzer->shouldReceive('analyzeWithConditionalRules')
            ->once()
            ->andReturn([
                'parameters' => [['name' => 'type', 'type' => 'string']],
                'conditional_rules' => $conditionalRules,
            ]);

        $this->mockSchemaGenerator->shouldReceive('generateConditionalSchema')
            ->once()
            ->with($conditionalRules, [['name' => 'type', 'type' => 'string']])
            ->andReturn([
                'oneOf' => [
                    ['type' => 'object', 'properties' => ['individual_field' => ['type' => 'string']]],
                    ['type' => 'object', 'properties' => ['company_field' => ['type' => 'string']]],
                ],
            ]);

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertInstanceOf(OpenApiRequestBody::class, $result);
        $this->assertArrayHasKey('oneOf', $result->getSchemaFor('application/json'));
    }

    #[Test]
    public function it_generates_multipart_request_body_for_file_uploads(): void
    {
        $controllerInfo = ['formRequest' => 'App\Http\Requests\UploadRequest'];
        $route = ['uri' => 'api/upload'];

        $this->mockRequestAnalyzer->shouldReceive('analyzeWithConditionalRules')
            ->once()
            ->andReturn(['parameters' => [], 'conditional_rules' => []]);

        $this->mockRequestAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                ['name' => 'file', 'type' => 'file', 'required' => true],
                ['name' => 'description', 'type' => 'string', 'required' => false],
            ]);

        $this->mockSchemaGenerator->shouldReceive('generateFromParameters')
            ->once()
            ->andReturn([
                'content' => [
                    'multipart/form-data' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'file' => ['type' => 'string', 'format' => 'binary'],
                                'description' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ]);

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertInstanceOf(OpenApiRequestBody::class, $result);
        $this->assertTrue($result->required);
        $this->assertTrue($result->isMultipart());
        $this->assertStringContainsString('file uploads', $result->description);
    }

    #[Test]
    public function it_includes_multiple_files_info_in_description(): void
    {
        $controllerInfo = ['formRequest' => 'App\Http\Requests\MultiUploadRequest'];
        $route = ['uri' => 'api/upload'];

        $this->mockRequestAnalyzer->shouldReceive('analyzeWithConditionalRules')
            ->once()
            ->andReturn(['parameters' => [], 'conditional_rules' => []]);

        $this->mockRequestAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                [
                    'name' => 'files',
                    'type' => 'file',
                    'required' => true,
                    'file_info' => ['multiple' => true],
                ],
            ]);

        $this->mockSchemaGenerator->shouldReceive('generateFromParameters')
            ->once()
            ->andReturn([
                'content' => [
                    'multipart/form-data' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'files' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'binary']],
                            ],
                        ],
                    ],
                ],
            ]);

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertInstanceOf(OpenApiRequestBody::class, $result);
        $this->assertStringContainsString('Multiple files allowed', $result->description);
    }

    #[Test]
    public function it_handles_schema_with_existing_content_structure(): void
    {
        $controllerInfo = ['formRequest' => 'App\Http\Requests\SpecialRequest'];
        $route = ['uri' => 'api/special'];

        $this->mockRequestAnalyzer->shouldReceive('analyzeWithConditionalRules')
            ->once()
            ->andReturn(['parameters' => [], 'conditional_rules' => []]);

        $this->mockRequestAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                ['name' => 'data', 'type' => 'string', 'required' => true],
            ]);

        $this->mockSchemaGenerator->shouldReceive('generateFromParameters')
            ->once()
            ->andReturn([
                'content' => [
                    'application/xml' => [
                        'schema' => ['type' => 'object'],
                    ],
                ],
            ]);

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertInstanceOf(OpenApiRequestBody::class, $result);
        $this->assertContains('application/xml', $result->getContentTypes());
        $this->assertNotContains('application/json', $result->getContentTypes());
    }

    #[Test]
    public function it_prefers_form_request_over_inline_validation(): void
    {
        $controllerInfo = [
            'formRequest' => 'App\Http\Requests\StoreUserRequest',
            'inlineValidation' => [
                'rules' => ['ignored' => ['required']],
            ],
        ];
        $route = ['uri' => 'api/users'];

        $this->mockRequestAnalyzer->shouldReceive('analyzeWithConditionalRules')
            ->once()
            ->andReturn(['parameters' => [], 'conditional_rules' => []]);

        $this->mockRequestAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                ['name' => 'from_form_request', 'type' => 'string', 'required' => true],
            ]);

        $this->mockSchemaGenerator->shouldReceive('generateFromParameters')
            ->once()
            ->andReturn([
                'type' => 'object',
                'properties' => [
                    'from_form_request' => ['type' => 'string'],
                ],
            ]);

        // InlineValidationAnalyzer should NOT be called
        $this->mockInlineValidationAnalyzer->shouldNotReceive('generateParameters');

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertInstanceOf(OpenApiRequestBody::class, $result);
    }

    #[Test]
    public function it_returns_null_when_form_request_has_no_parameters(): void
    {
        $controllerInfo = ['formRequest' => 'App\Http\Requests\EmptyRequest'];
        $route = ['uri' => 'api/users'];

        $this->mockRequestAnalyzer->shouldReceive('analyzeWithConditionalRules')
            ->once()
            ->andReturn(['parameters' => [], 'conditional_rules' => []]);

        $this->mockRequestAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([]);

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_when_file_upload_schema_has_no_content(): void
    {
        $controllerInfo = ['formRequest' => 'App\Http\Requests\UploadRequest'];
        $route = ['uri' => 'api/upload'];

        $this->mockRequestAnalyzer->shouldReceive('analyzeWithConditionalRules')
            ->once()
            ->andReturn(['parameters' => [], 'conditional_rules' => []]);

        $this->mockRequestAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                ['name' => 'file', 'type' => 'file', 'required' => true],
            ]);

        $this->mockSchemaGenerator->shouldReceive('generateFromParameters')
            ->once()
            ->andReturn(['type' => 'object']); // No 'content' key

        $result = $this->generator->generate($controllerInfo, $route);

        $this->assertNull($result);
    }
}
