<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\CustomRuleAnalyzer;
use LaravelSpectrum\DTO\OpenApiSchema;
use LaravelSpectrum\Tests\Fixtures\Rules\AttributeRule;
use LaravelSpectrum\Tests\Fixtures\Rules\DescribableRule;
use LaravelSpectrum\Tests\Fixtures\Rules\MinLengthRule;
use LaravelSpectrum\Tests\Fixtures\Rules\NoConstraintRule;
use LaravelSpectrum\Tests\Fixtures\Rules\NumericRangeRule;
use LaravelSpectrum\Tests\Fixtures\Rules\PatternRule;
use LaravelSpectrum\Tests\TestCase;

class CustomRuleAnalyzerTest extends TestCase
{
    private CustomRuleAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new CustomRuleAnalyzer;
    }

    // ===== Interface-based detection tests =====

    public function test_analyzes_rule_implementing_spectrum_describable_rule(): void
    {
        $rule = new DescribableRule;
        $result = $this->analyzer->analyze($rule);

        $this->assertNotNull($result);
        $this->assertInstanceOf(OpenApiSchema::class, $result);
        $this->assertEquals('string', $result->type);
        $this->assertEquals('^\d{3}-\d{4}$', $result->pattern);
        $this->assertEquals(8, $result->minLength);
        $this->assertEquals(8, $result->maxLength);
    }

    // ===== Attribute-based detection tests =====

    public function test_analyzes_rule_with_openapi_schema_attribute(): void
    {
        $rule = new AttributeRule;
        $result = $this->analyzer->analyze($rule);

        $this->assertNotNull($result);
        $this->assertInstanceOf(OpenApiSchema::class, $result);
        $this->assertEquals('string', $result->type);
        $this->assertEquals('uuid', $result->format);
    }

    // ===== Reflection-based detection tests =====

    public function test_analyzes_rule_with_min_length_property(): void
    {
        $rule = new MinLengthRule(minLength: 12);
        $result = $this->analyzer->analyze($rule);

        $this->assertNotNull($result);
        $this->assertInstanceOf(OpenApiSchema::class, $result);
        $this->assertEquals('string', $result->type);
        $this->assertEquals(12, $result->minLength);
    }

    public function test_analyzes_rule_with_default_min_length(): void
    {
        $rule = new MinLengthRule;
        $result = $this->analyzer->analyze($rule);

        $this->assertNotNull($result);
        $this->assertEquals(8, $result->minLength);
    }

    public function test_analyzes_rule_with_min_max_properties(): void
    {
        $rule = new NumericRangeRule(min: 18, max: 120);
        $result = $this->analyzer->analyze($rule);

        $this->assertNotNull($result);
        $this->assertInstanceOf(OpenApiSchema::class, $result);
        $this->assertEquals('integer', $result->type);
        $this->assertEquals(18, $result->minimum);
        $this->assertEquals(120, $result->maximum);
    }

    public function test_analyzes_rule_with_default_min_max(): void
    {
        $rule = new NumericRangeRule;
        $result = $this->analyzer->analyze($rule);

        $this->assertNotNull($result);
        $this->assertEquals(0, $result->minimum);
        $this->assertEquals(100, $result->maximum);
    }

    public function test_analyzes_rule_with_pattern_property(): void
    {
        $rule = new PatternRule(pattern: '/^[A-Z]{2}\d{6}$/');
        $result = $this->analyzer->analyze($rule);

        $this->assertNotNull($result);
        $this->assertInstanceOf(OpenApiSchema::class, $result);
        $this->assertEquals('string', $result->type);
        $this->assertEquals('/^[A-Z]{2}\d{6}$/', $result->pattern);
    }

    // ===== Edge case tests =====

    public function test_returns_null_for_rule_with_no_detectable_constraints(): void
    {
        $rule = new NoConstraintRule;
        $result = $this->analyzer->analyze($rule);

        $this->assertNull($result);
    }

    // ===== Priority order tests =====

    public function test_interface_takes_priority_over_attribute(): void
    {
        // DescribableRule implements the interface
        // Even if it had an attribute, the interface should be used
        $rule = new DescribableRule;
        $result = $this->analyzer->analyze($rule);

        // Verify it used the interface (specific pattern from interface)
        $this->assertEquals('^\d{3}-\d{4}$', $result->pattern);
    }

    public function test_attribute_takes_priority_over_reflection(): void
    {
        // AttributeRule has the attribute specifying format: uuid
        // Even if properties suggested something else, attribute should be used
        $rule = new AttributeRule;
        $result = $this->analyzer->analyze($rule);

        // Verify it used the attribute (format from attribute)
        $this->assertEquals('uuid', $result->format);
    }
}
