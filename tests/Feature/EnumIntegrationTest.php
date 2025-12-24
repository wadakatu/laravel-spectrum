<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Validation\Rule;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Tests\Fixtures\Enums\PriorityEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;
use LaravelSpectrum\Tests\TestCase;

class EnumIntegrationTest extends TestCase
{
    private FormRequestAnalyzer $formRequestAnalyzer;

    private InlineValidationAnalyzer $inlineValidationAnalyzer;

    private SchemaGenerator $schemaGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock cache that always calls the callback
        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberFormRequest')
            ->willReturnCallback(function ($class, $callback) {
                return $callback();
            });

        // Register mock cache in container and get analyzers via DI
        $this->app->instance(DocumentationCache::class, $cache);
        $this->formRequestAnalyzer = $this->app->make(FormRequestAnalyzer::class);
        $this->inlineValidationAnalyzer = $this->app->make(InlineValidationAnalyzer::class);
        $this->schemaGenerator = new SchemaGenerator;
    }

    public function test_form_request_with_enum_generates_correct_schema(): void
    {
        // Use a real FormRequest class instead of anonymous class
        $requestClass = \LaravelSpectrum\Tests\Fixtures\FormRequests\EnumTestRequest::class;

        $parameters = $this->formRequestAnalyzer->analyze($requestClass);

        // Debug: Let's check what parameters are generated
        foreach ($parameters as $param) {
            if ($param['name'] === 'status' || $param['name'] === 'priority') {
                $this->assertArrayHasKey('enum', $param, 'Enum not found for field: '.$param['name']);
            }
        }

        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        // Check status field has string enum values
        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['status']['type']);
        $this->assertArrayHasKey('enum', $schema['properties']['status']);
        $this->assertEquals(['active', 'inactive', 'pending'], $schema['properties']['status']['enum']);
        $this->assertStringContainsString('StatusEnum', $schema['properties']['status']['description']);

        // Check priority field has integer enum values
        $this->assertArrayHasKey('priority', $schema['properties']);
        $this->assertEquals('integer', $schema['properties']['priority']['type']);
        $this->assertEquals([1, 2, 3], $schema['properties']['priority']['enum']);
        $this->assertStringContainsString('PriorityEnum', $schema['properties']['priority']['description']);

        // Check regular field is unaffected
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertArrayNotHasKey('enum', $schema['properties']['name']);
    }

    public function test_inline_validation_with_enum_generates_correct_schema(): void
    {
        $validation = [
            'rules' => [
                'status' => ['required', Rule::enum(StatusEnum::class)],
                'type' => 'required|enum:'.\LaravelSpectrum\Tests\Fixtures\Enums\UserTypeEnum::class,
            ],
            'attributes' => [],
            'messages' => [],
        ];

        $parameters = $this->inlineValidationAnalyzer->generateParameters($validation);
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        // Check status field
        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['status']['type']);
        $this->assertEquals(['active', 'inactive', 'pending'], $schema['properties']['status']['enum']);

        // Check type field with string-based enum rule
        $this->assertArrayHasKey('type', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['type']['type']);
        $this->assertEquals(['admin', 'user', 'guest'], $schema['properties']['type']['enum']);
    }

    public function test_mixed_enum_and_in_rule_handling(): void
    {
        $validation = [
            'rules' => [
                'status' => ['required', Rule::enum(StatusEnum::class)],
                'role' => 'required|in:admin,moderator,user',
            ],
            'attributes' => [],
            'messages' => [],
        ];

        $parameters = $this->inlineValidationAnalyzer->generateParameters($validation);
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        // Check enum-based field
        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertEquals(['active', 'inactive', 'pending'], $schema['properties']['status']['enum']);

        // Check in-rule based field (should still work)
        $this->assertArrayHasKey('role', $schema['properties']);
        $this->assertEquals(['admin', 'moderator', 'user'], $schema['properties']['role']['enum']);
    }

    public function test_enum_with_other_validation_rules(): void
    {
        $validation = [
            'rules' => [
                'status' => ['sometimes', 'nullable', Rule::enum(StatusEnum::class)],
                'priority' => ['required_if:status,active', new \Illuminate\Validation\Rules\Enum(PriorityEnum::class)],
            ],
            'attributes' => [],
            'messages' => [],
        ];

        $parameters = $this->inlineValidationAnalyzer->generateParameters($validation);

        // Check status is not required but has enum
        $statusParam = array_filter($parameters, fn ($p) => $p['name'] === 'status')[0];
        $this->assertFalse($statusParam['required']);
        $this->assertEquals(['active', 'inactive', 'pending'], $statusParam['enum']['values']);

        // Check priority has conditional requirement and enum
        $priorityParam = array_filter($parameters, fn ($p) => $p['name'] === 'priority')[1];
        $this->assertTrue($priorityParam['required']); // required_if is treated as required
        $this->assertEquals([1, 2, 3], $priorityParam['enum']['values']);
        $this->assertEquals('integer', $priorityParam['enum']['type']);
    }
}
