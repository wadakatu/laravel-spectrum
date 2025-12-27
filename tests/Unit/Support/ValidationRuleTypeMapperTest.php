<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\ValidationRuleTypeMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValidationRuleTypeMapperTest extends TestCase
{
    private ValidationRuleTypeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ValidationRuleTypeMapper;
    }

    #[Test]
    public function it_infers_integer_type_from_rules(): void
    {
        $this->assertEquals('integer', $this->mapper->inferType(['integer']));
        $this->assertEquals('integer', $this->mapper->inferType(['int']));
        $this->assertEquals('integer', $this->mapper->inferType(['digits:4']));
        $this->assertEquals('integer', $this->mapper->inferType(['digits_between:4,8']));
    }

    #[Test]
    public function it_infers_number_type_from_rules(): void
    {
        $this->assertEquals('number', $this->mapper->inferType(['numeric']));
        $this->assertEquals('number', $this->mapper->inferType(['decimal:2']));
    }

    #[Test]
    public function it_infers_boolean_type_from_rules(): void
    {
        $this->assertEquals('boolean', $this->mapper->inferType(['boolean']));
        $this->assertEquals('boolean', $this->mapper->inferType(['bool']));
        $this->assertEquals('boolean', $this->mapper->inferType(['accepted']));
        $this->assertEquals('boolean', $this->mapper->inferType(['declined']));
    }

    #[Test]
    public function it_infers_array_type_from_rules(): void
    {
        $this->assertEquals('array', $this->mapper->inferType(['array']));
    }

    #[Test]
    public function it_infers_object_type_from_json_rule(): void
    {
        $this->assertEquals('object', $this->mapper->inferType(['json']));
    }

    #[Test]
    public function it_infers_string_type_for_date_rules(): void
    {
        $this->assertEquals('string', $this->mapper->inferType(['date']));
        $this->assertEquals('string', $this->mapper->inferType(['datetime']));
        $this->assertEquals('string', $this->mapper->inferType(['date_format:Y-m-d']));
        $this->assertEquals('string', $this->mapper->inferType(['after:2024-01-01']));
        $this->assertEquals('string', $this->mapper->inferType(['before:2024-12-31']));
    }

    #[Test]
    public function it_infers_string_type_for_format_rules(): void
    {
        $this->assertEquals('string', $this->mapper->inferType(['email']));
        $this->assertEquals('string', $this->mapper->inferType(['url']));
        $this->assertEquals('string', $this->mapper->inferType(['uuid']));
        $this->assertEquals('string', $this->mapper->inferType(['ip']));
        $this->assertEquals('string', $this->mapper->inferType(['ipv4']));
        $this->assertEquals('string', $this->mapper->inferType(['ipv6']));
        $this->assertEquals('string', $this->mapper->inferType(['mac_address']));
    }

    #[Test]
    public function it_returns_string_as_default_type(): void
    {
        $this->assertEquals('string', $this->mapper->inferType([]));
        $this->assertEquals('string', $this->mapper->inferType(['required']));
        $this->assertEquals('string', $this->mapper->inferType(['max:255']));
    }

    #[Test]
    public function it_skips_non_string_rules(): void
    {
        $this->assertEquals('string', $this->mapper->inferType([new \stdClass]));
        $this->assertEquals('integer', $this->mapper->inferType([new \stdClass, 'integer']));
    }

    #[Test]
    public function it_infers_email_format(): void
    {
        $this->assertEquals('email', $this->mapper->inferFormat(['email']));
    }

    #[Test]
    public function it_infers_uri_format(): void
    {
        $this->assertEquals('uri', $this->mapper->inferFormat(['url']));
    }

    #[Test]
    public function it_infers_uuid_format(): void
    {
        $this->assertEquals('uuid', $this->mapper->inferFormat(['uuid']));
    }

    #[Test]
    public function it_infers_date_format(): void
    {
        $this->assertEquals('date', $this->mapper->inferFormat(['date']));
        $this->assertEquals('date', $this->mapper->inferFormat(['date_format:Y-m-d']));
        $this->assertEquals('date-time', $this->mapper->inferFormat(['date_format:Y-m-d H:i:s']));
        $this->assertEquals('date-time', $this->mapper->inferFormat(['after:2024-01-01']));
    }

    #[Test]
    public function it_infers_ip_format(): void
    {
        $this->assertEquals('ipv4', $this->mapper->inferFormat(['ip']));
        $this->assertEquals('ipv4', $this->mapper->inferFormat(['ipv4']));
        $this->assertEquals('ipv6', $this->mapper->inferFormat(['ipv6']));
    }

    #[Test]
    public function it_returns_null_format_for_unknown_rules(): void
    {
        $this->assertNull($this->mapper->inferFormat([]));
        $this->assertNull($this->mapper->inferFormat(['required']));
        $this->assertNull($this->mapper->inferFormat(['integer']));
    }

    #[Test]
    public function it_detects_enum_rule_with_in_prefix(): void
    {
        $this->assertTrue($this->mapper->hasEnumRule(['in:active,inactive']));
        $this->assertFalse($this->mapper->hasEnumRule(['required']));
        $this->assertFalse($this->mapper->hasEnumRule([]));
    }

    #[Test]
    public function it_extracts_enum_values(): void
    {
        $values = $this->mapper->extractEnumValues(['in:active,inactive,pending']);

        $this->assertEquals(['active', 'inactive', 'pending'], $values);
    }

    #[Test]
    public function it_returns_null_for_no_enum_values(): void
    {
        $this->assertNull($this->mapper->extractEnumValues(['required']));
        $this->assertNull($this->mapper->extractEnumValues([]));
    }

    #[Test]
    public function it_extracts_minimum_constraint(): void
    {
        // For string type (default), min → minLength
        $constraints = $this->mapper->extractConstraints(['min:5']);
        $this->assertEquals(5, $constraints['minLength']);

        // For integer type, min → minimum
        $constraints = $this->mapper->extractConstraints(['integer', 'min:5']);
        $this->assertEquals(5, $constraints['minimum']);
    }

    #[Test]
    public function it_extracts_maximum_constraint(): void
    {
        // For string type (default), max → maxLength
        $constraints = $this->mapper->extractConstraints(['max:100']);
        $this->assertEquals(100, $constraints['maxLength']);

        // For number type, max → maximum
        $constraints = $this->mapper->extractConstraints(['numeric', 'max:100']);
        $this->assertEquals(100, $constraints['maximum']);
    }

    #[Test]
    public function it_extracts_between_constraints(): void
    {
        // For string type (default), between → minLength/maxLength
        $constraints = $this->mapper->extractConstraints(['between:1,10']);
        $this->assertEquals(1, $constraints['minLength']);
        $this->assertEquals(10, $constraints['maxLength']);

        // For integer type, between → minimum/maximum
        $constraints = $this->mapper->extractConstraints(['integer', 'between:1,10']);
        $this->assertEquals(1, $constraints['minimum']);
        $this->assertEquals(10, $constraints['maximum']);
    }

    #[Test]
    public function it_extracts_size_constraints(): void
    {
        $constraints = $this->mapper->extractConstraints(['size:5']);

        $this->assertEquals(5, $constraints['minLength']);
        $this->assertEquals(5, $constraints['maxLength']);
    }

    #[Test]
    public function it_extracts_digits_constraints(): void
    {
        $constraints = $this->mapper->extractConstraints(['digits:4']);

        $this->assertEquals(4, $constraints['minLength']);
        $this->assertEquals(4, $constraints['maxLength']);
    }

    #[Test]
    public function it_extracts_digits_between_constraints(): void
    {
        $constraints = $this->mapper->extractConstraints(['digits_between:4,8']);

        $this->assertEquals(4, $constraints['minLength']);
        $this->assertEquals(8, $constraints['maxLength']);
    }

    #[Test]
    public function it_extracts_enum_constraint(): void
    {
        $constraints = $this->mapper->extractConstraints(['in:a,b,c']);

        $this->assertEquals(['a', 'b', 'c'], $constraints['enum']);
    }

    #[Test]
    public function it_extracts_pattern_constraint(): void
    {
        $constraints = $this->mapper->extractConstraints(['regex:/^[a-z]+$/']);

        $this->assertEquals('/^[a-z]+$/', $constraints['pattern']);
    }

    #[Test]
    public function it_returns_empty_constraints_for_unknown_rules(): void
    {
        $constraints = $this->mapper->extractConstraints(['required', 'string']);

        $this->assertEmpty($constraints);
    }

    #[Test]
    public function it_handles_mixed_rules(): void
    {
        $rules = ['required', 'integer', 'min:1', 'max:100'];

        $this->assertEquals('integer', $this->mapper->inferType($rules));

        $constraints = $this->mapper->extractConstraints($rules);
        $this->assertEquals(1, $constraints['minimum']);
        $this->assertEquals(100, $constraints['maximum']);
    }

    #[Test]
    public function it_normalizes_string_rules(): void
    {
        $result = $this->mapper->normalizeRules('required|string|max:255');

        $this->assertEquals(['required', 'string', 'max:255'], $result);
    }

    #[Test]
    public function it_normalizes_empty_string_rules(): void
    {
        $result = $this->mapper->normalizeRules('');

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_normalizes_null_rules(): void
    {
        $result = $this->mapper->normalizeRules(null);

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_normalizes_array_rules_unchanged(): void
    {
        $rules = ['required', 'string'];
        $result = $this->mapper->normalizeRules($rules);

        $this->assertEquals(['required', 'string'], $result);
    }

    #[Test]
    public function it_infers_string_type_from_file_rules(): void
    {
        $this->assertEquals('string', $this->mapper->inferType(['file']));
        $this->assertEquals('string', $this->mapper->inferType(['image']));
    }

    #[Test]
    public function it_infers_string_type_from_timezone_rule(): void
    {
        $this->assertEquals('string', $this->mapper->inferType(['timezone']));
    }

    #[Test]
    public function it_infers_string_type_from_explicit_string_rule(): void
    {
        $this->assertEquals('string', $this->mapper->inferType(['string']));
    }

    #[Test]
    public function it_detects_enum_rule_with_in_object(): void
    {
        // Test with Illuminate's In rule class
        $inRule = \Illuminate\Validation\Rule::in(['active', 'inactive']);
        $this->assertTrue($this->mapper->hasEnumRule([$inRule]));

        // Test with anonymous class (should not match)
        $anonymousRule = new class {};
        $this->assertFalse($this->mapper->hasEnumRule([$anonymousRule]));
    }

    #[Test]
    public function it_detects_enum_rule_with_enum_object(): void
    {
        // Test with Illuminate's Enum rule class (requires Laravel 9+)
        if (class_exists(\Illuminate\Validation\Rules\Enum::class)) {
            // Create an enum mock or use a real enum if available
            $enumRule = new \Illuminate\Validation\Rules\Enum(\LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum::class);
            $this->assertTrue($this->mapper->hasEnumRule([$enumRule]));
        } else {
            $this->markTestSkipped('Enum rule class not available');
        }
    }

    #[Test]
    public function it_infers_boolean_type_from_accepted_if(): void
    {
        $this->assertEquals('boolean', $this->mapper->inferType(['accepted_if:field,value']));
        $this->assertEquals('boolean', $this->mapper->inferType(['declined_if:field,value']));
    }

    #[Test]
    public function it_infers_format_from_before_or_equal_rule(): void
    {
        $this->assertEquals('date-time', $this->mapper->inferFormat(['before_or_equal:2024-12-31']));
        $this->assertEquals('date-time', $this->mapper->inferFormat(['after_or_equal:2024-01-01']));
        $this->assertEquals('date-time', $this->mapper->inferFormat(['date_equals:2024-06-15']));
    }

    #[Test]
    public function it_extracts_constraints_with_explicit_type(): void
    {
        // When type is explicitly provided, use it
        $constraints = $this->mapper->extractConstraints(['min:5'], 'integer');
        $this->assertEquals(5, $constraints['minimum']);

        // Without explicit type, infer from rules
        $constraints = $this->mapper->extractConstraints(['min:5'], 'string');
        $this->assertEquals(5, $constraints['minLength']);
    }

    #[Test]
    public function it_infers_mac_format(): void
    {
        $this->assertEquals('mac', $this->mapper->inferFormat(['mac_address']));
    }

    #[Test]
    public function it_infers_datetime_format(): void
    {
        $this->assertEquals('date-time', $this->mapper->inferFormat(['datetime']));
    }

    #[Test]
    public function it_skips_non_string_rules_in_infer_format(): void
    {
        $this->assertNull($this->mapper->inferFormat([new \stdClass, 'required']));
        $this->assertEquals('email', $this->mapper->inferFormat([new \stdClass, 'email']));
    }

    #[Test]
    public function it_skips_non_string_rules_in_has_enum_rule(): void
    {
        // Should check string 'in:' rules
        $this->assertTrue($this->mapper->hasEnumRule([new \stdClass, 'in:a,b']));
        $this->assertFalse($this->mapper->hasEnumRule([new \stdClass, 'required']));
    }

    #[Test]
    public function it_skips_non_string_rules_in_extract_enum_values(): void
    {
        $this->assertEquals(['a', 'b'], $this->mapper->extractEnumValues([new \stdClass, 'in:a,b']));
        $this->assertNull($this->mapper->extractEnumValues([new \stdClass, 'required']));
    }

    #[Test]
    public function it_skips_non_string_rules_in_extract_constraints(): void
    {
        // Non-string rules should be skipped
        $constraints = $this->mapper->extractConstraints([new \stdClass, 'min:5']);
        $this->assertEquals(5, $constraints['minLength']);
    }
}
