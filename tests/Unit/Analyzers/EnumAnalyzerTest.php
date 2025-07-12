<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Tests\Fixtures\Enums\PriorityEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\SimpleEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\TagEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\UserTypeEnum;
use LaravelSpectrum\Tests\TestCase;
use ReflectionMethod;

class EnumAnalyzerTest extends TestCase
{
    private EnumAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new EnumAnalyzer;
    }

    public function test_analyzes_rule_enum_static_method(): void
    {
        $rule = Rule::enum(StatusEnum::class);
        $result = $this->analyzer->analyzeValidationRule($rule);

        $this->assertNotNull($result);
        $this->assertEquals(StatusEnum::class, $result['class']);
        $this->assertEquals(['active', 'inactive', 'pending'], $result['values']);
        $this->assertEquals('string', $result['type']);
    }

    public function test_analyzes_new_enum_instance(): void
    {
        $rule = new Enum(PriorityEnum::class);
        $result = $this->analyzer->analyzeValidationRule($rule);

        $this->assertNotNull($result);
        $this->assertEquals(PriorityEnum::class, $result['class']);
        $this->assertEquals([1, 2, 3], $result['values']);
        $this->assertEquals('integer', $result['type']);
    }

    public function test_analyzes_string_enum_rule(): void
    {
        $rule = 'enum:'.UserTypeEnum::class;
        $result = $this->analyzer->analyzeValidationRule($rule);

        $this->assertNotNull($result);
        $this->assertEquals(UserTypeEnum::class, $result['class']);
        $this->assertEquals(['admin', 'user', 'guest'], $result['values']);
        $this->assertEquals('string', $result['type']);
    }

    public function test_analyzes_unit_enum(): void
    {
        $rule = Rule::enum(SimpleEnum::class);
        $result = $this->analyzer->analyzeValidationRule($rule);

        $this->assertNotNull($result);
        $this->assertEquals(SimpleEnum::class, $result['class']);
        $this->assertEquals(['OPTION_A', 'OPTION_B', 'OPTION_C'], $result['values']);
        $this->assertEquals('string', $result['type']);
    }

    public function test_returns_null_for_non_enum_rules(): void
    {
        $this->assertNull($this->analyzer->analyzeValidationRule('required'));
        $this->assertNull($this->analyzer->analyzeValidationRule(['required', 'string']));
        $this->assertNull($this->analyzer->analyzeValidationRule(null));
    }

    public function test_handles_invalid_enum_class(): void
    {
        $rule = 'enum:NonExistentClass';
        $result = $this->analyzer->analyzeValidationRule($rule);

        $this->assertNull($result);
    }

    public function test_analyzes_eloquent_casts(): void
    {
        $modelClass = \LaravelSpectrum\Tests\Fixtures\Models\EnumCastModel::class;
        $result = $this->analyzer->analyzeEloquentCasts($modelClass);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(StatusEnum::class, $result['status']['class']);
        $this->assertEquals(['active', 'inactive', 'pending'], $result['status']['values']);
        $this->assertEquals('string', $result['status']['type']);

        $this->assertArrayHasKey('priority', $result);
        $this->assertEquals(PriorityEnum::class, $result['priority']['class']);
    }

    public function test_analyzes_method_signature_parameters(): void
    {
        $method = new ReflectionMethod(
            \LaravelSpectrum\Tests\Fixtures\Controllers\EnumTestController::class,
            'store'
        );
        $result = $this->analyzer->analyzeMethodSignature($method);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('status', $result['parameters']);
        $this->assertEquals(StatusEnum::class, $result['parameters']['status']['class']);
    }

    public function test_analyzes_method_signature_return_type(): void
    {
        $method = new ReflectionMethod(
            \LaravelSpectrum\Tests\Fixtures\Controllers\EnumTestController::class,
            'getStatus'
        );
        $result = $this->analyzer->analyzeMethodSignature($method);

        $this->assertArrayHasKey('return', $result);
        $this->assertEquals(StatusEnum::class, $result['return']['class']);
        $this->assertEquals(['active', 'inactive', 'pending'], $result['return']['values']);
    }

    public function test_analyzes_array_validation_with_enum(): void
    {
        $rule = Rule::enum(TagEnum::class);
        $result = $this->analyzer->analyzeValidationRule($rule);

        $this->assertNotNull($result);
        $this->assertEquals(TagEnum::class, $result['class']);
        $this->assertEquals(['featured', 'popular', 'new', 'trending'], $result['values']);
    }

    public function test_handles_enum_with_namespace_alias(): void
    {
        $rule = 'enum:StatusEnum';
        $namespace = 'LaravelSpectrum\\Tests\\Fixtures\\Enums';
        $result = $this->analyzer->analyzeValidationRule($rule, $namespace);

        $this->assertNotNull($result);
        $this->assertEquals('LaravelSpectrum\\Tests\\Fixtures\\Enums\\StatusEnum', $result['class']);
    }
}
