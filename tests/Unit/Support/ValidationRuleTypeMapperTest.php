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
}
