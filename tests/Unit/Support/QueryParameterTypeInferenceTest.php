<?php

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\QueryParameterTypeInference;
use PHPUnit\Framework\TestCase;

class QueryParameterTypeInferenceTest extends TestCase
{
    private QueryParameterTypeInference $inference;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inference = new QueryParameterTypeInference;
    }

    public function test_infer_from_method(): void
    {
        $this->assertEquals('boolean', $this->inference->inferFromMethod('boolean'));
        $this->assertEquals('boolean', $this->inference->inferFromMethod('bool'));
        $this->assertEquals('integer', $this->inference->inferFromMethod('integer'));
        $this->assertEquals('integer', $this->inference->inferFromMethod('int'));
        $this->assertEquals('number', $this->inference->inferFromMethod('float'));
        $this->assertEquals('number', $this->inference->inferFromMethod('double'));
        $this->assertEquals('string', $this->inference->inferFromMethod('string'));
        $this->assertEquals('array', $this->inference->inferFromMethod('array'));
        $this->assertEquals('string', $this->inference->inferFromMethod('date'));
        $this->assertEquals('object', $this->inference->inferFromMethod('json'));
        $this->assertNull($this->inference->inferFromMethod('input'));
        $this->assertNull($this->inference->inferFromMethod('get'));
    }

    public function test_infer_from_default_value(): void
    {
        $this->assertEquals('boolean', $this->inference->inferFromDefaultValue(true));
        $this->assertEquals('boolean', $this->inference->inferFromDefaultValue(false));
        $this->assertEquals('integer', $this->inference->inferFromDefaultValue(42));
        $this->assertEquals('integer', $this->inference->inferFromDefaultValue(0));
        $this->assertEquals('number', $this->inference->inferFromDefaultValue(3.14));
        $this->assertEquals('number', $this->inference->inferFromDefaultValue(0.0));
        $this->assertEquals('array', $this->inference->inferFromDefaultValue([]));
        $this->assertEquals('array', $this->inference->inferFromDefaultValue(['a', 'b']));
        $this->assertEquals('string', $this->inference->inferFromDefaultValue('hello'));
        $this->assertEquals('string', $this->inference->inferFromDefaultValue(''));
    }

    public function test_infer_from_context(): void
    {
        // Numeric operations
        $context = ['numeric_operation' => 'integer'];
        $this->assertEquals('integer', $this->inference->inferFromContext($context));

        $context = ['numeric_operation' => 'float'];
        $this->assertEquals('number', $this->inference->inferFromContext($context));

        // Array operations
        $context = ['array_operation' => true];
        $this->assertEquals('array', $this->inference->inferFromContext($context));

        // Boolean context
        $context = ['boolean_context' => true];
        $this->assertEquals('boolean', $this->inference->inferFromContext($context));

        // WHERE clause context - ID fields
        $context = ['where_clause' => ['column' => 'user_id']];
        $this->assertEquals('integer', $this->inference->inferFromContext($context));

        $context = ['where_clause' => ['column' => 'product_count']];
        $this->assertEquals('integer', $this->inference->inferFromContext($context));

        // WHERE clause context - Date fields
        $context = ['where_clause' => ['column' => 'created_at']];
        $this->assertEquals('string', $this->inference->inferFromContext($context));

        // WHERE clause context - Price fields
        $context = ['where_clause' => ['column' => 'price']];
        $this->assertEquals('number', $this->inference->inferFromContext($context));

        // WHERE clause context - Boolean fields
        $context = ['where_clause' => ['column' => 'active']];
        $this->assertEquals('boolean', $this->inference->inferFromContext($context));

        $context = ['where_clause' => ['column' => 'published']];
        $this->assertEquals('boolean', $this->inference->inferFromContext($context));
    }

    public function test_infer_from_validation_rules(): void
    {
        $this->assertEquals('integer', $this->inference->inferFromValidationRules(['integer']));
        $this->assertEquals('integer', $this->inference->inferFromValidationRules(['required', 'integer', 'min:1']));
        $this->assertEquals('number', $this->inference->inferFromValidationRules(['numeric']));
        $this->assertEquals('number', $this->inference->inferFromValidationRules(['decimal:2']));
        $this->assertEquals('boolean', $this->inference->inferFromValidationRules(['boolean']));
        $this->assertEquals('boolean', $this->inference->inferFromValidationRules(['accepted']));
        $this->assertEquals('array', $this->inference->inferFromValidationRules(['array']));
        $this->assertEquals('object', $this->inference->inferFromValidationRules(['json']));
        $this->assertEquals('string', $this->inference->inferFromValidationRules(['string', 'max:255']));
        $this->assertEquals('string', $this->inference->inferFromValidationRules(['email']));
        $this->assertEquals('string', $this->inference->inferFromValidationRules(['url']));
        $this->assertEquals('string', $this->inference->inferFromValidationRules(['date']));
        $this->assertEquals('string', $this->inference->inferFromValidationRules(['in:active,inactive']));
    }

    public function test_detect_enum_values(): void
    {
        $context = ['enum_values' => ['active', 'inactive', 'pending']];
        $this->assertEquals(['active', 'inactive', 'pending'], $this->inference->detectEnumValues($context));

        $context = ['in_array' => ['small', 'medium', 'large']];
        $this->assertEquals(['small', 'medium', 'large'], $this->inference->detectEnumValues($context));

        $context = ['switch_cases' => ['asc', 'desc']];
        $this->assertEquals(['asc', 'desc'], $this->inference->detectEnumValues($context));

        $context = [];
        $this->assertNull($this->inference->detectEnumValues($context));
    }

    public function test_get_format_for_type(): void
    {
        // Date operations
        $this->assertEquals('date-time', $this->inference->getFormatForType('string', ['date_operation' => true]));

        // Validation rules
        $this->assertEquals('email', $this->inference->getFormatForType('string', ['validation_rules' => ['email']]));
        $this->assertEquals('uri', $this->inference->getFormatForType('string', ['validation_rules' => ['url']]));
        $this->assertEquals('uuid', $this->inference->getFormatForType('string', ['validation_rules' => ['uuid']]));
        $this->assertEquals('date-time', $this->inference->getFormatForType('string', ['validation_rules' => ['date_format:Y-m-d H:i:s']]));
        $this->assertEquals('date', $this->inference->getFormatForType('string', ['validation_rules' => ['date']]));

        // No format
        $this->assertNull($this->inference->getFormatForType('integer', []));
        $this->assertNull($this->inference->getFormatForType('string', []));
    }

    public function test_get_constraints_from_rules(): void
    {
        // For string type (default), min/max → minLength/maxLength
        $rules = ['min:5'];
        $constraints = $this->inference->getConstraintsFromRules($rules);
        $this->assertEquals(['minLength' => 5], $constraints);

        $rules = ['max:100'];
        $constraints = $this->inference->getConstraintsFromRules($rules);
        $this->assertEquals(['maxLength' => 100], $constraints);

        $rules = ['between:10,50'];
        $constraints = $this->inference->getConstraintsFromRules($rules);
        $this->assertEquals(['minLength' => 10, 'maxLength' => 50], $constraints);

        $rules = ['size:10'];
        $constraints = $this->inference->getConstraintsFromRules($rules);
        $this->assertEquals(['minLength' => 10, 'maxLength' => 10], $constraints);

        $rules = ['in:active,inactive,pending'];
        $constraints = $this->inference->getConstraintsFromRules($rules);
        $this->assertEquals(['enum' => ['active', 'inactive', 'pending']], $constraints);

        $rules = ['regex:/^[A-Z]+$/'];
        $constraints = $this->inference->getConstraintsFromRules($rules);
        // PCRE delimiters should be stripped for OpenAPI compatibility
        $this->assertEquals(['pattern' => '^[A-Z]+$'], $constraints);

        // For integer type, min/max → minimum/maximum
        $rules = ['integer', 'min:1', 'max:100'];
        $constraints = $this->inference->getConstraintsFromRules($rules);
        $this->assertEquals(['minimum' => 1, 'maximum' => 100], $constraints);
    }

    public function test_complex_type_inference(): void
    {
        // Validation rules should take precedence
        $rules = ['integer', 'min:1'];
        $this->assertEquals('integer', $this->inference->inferFromValidationRules($rules));

        // Enum detection
        $rules = ['string', 'in:small,medium,large'];
        $this->assertEquals('string', $this->inference->inferFromValidationRules($rules));

        // Mixed rules
        $rules = ['required', 'numeric', 'between:0,100'];
        $this->assertEquals('number', $this->inference->inferFromValidationRules($rules));
    }
}
