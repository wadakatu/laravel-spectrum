<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\Support\RuleRequirementAnalyzer;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RuleRequirementAnalyzerTest extends TestCase
{
    private RuleRequirementAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new RuleRequirementAnalyzer;
    }

    #[Test]
    public function it_detects_required_rule(): void
    {
        $this->assertTrue($this->analyzer->isRequired(['required', 'string']));
        $this->assertTrue($this->analyzer->isRequired('required|string'));
    }

    #[Test]
    public function it_returns_false_for_non_required_field(): void
    {
        $this->assertFalse($this->analyzer->isRequired(['string', 'max:255']));
        $this->assertFalse($this->analyzer->isRequired('string|max:255'));
    }

    #[Test]
    public function it_treats_conditional_required_as_not_required(): void
    {
        $this->assertFalse($this->analyzer->isRequired(['required_if:status,active']));
        $this->assertFalse($this->analyzer->isRequired(['required_unless:role,admin']));
        $this->assertFalse($this->analyzer->isRequired(['required_with:email']));
        $this->assertFalse($this->analyzer->isRequired(['required_without:phone']));
    }

    #[Test]
    public function it_detects_conditional_required_rules(): void
    {
        $this->assertTrue($this->analyzer->hasConditionalRequired(['required_if:status,active']));
        $this->assertTrue($this->analyzer->hasConditionalRequired(['required_unless:role,admin']));
        $this->assertTrue($this->analyzer->hasConditionalRequired(['required_with:email']));
        $this->assertTrue($this->analyzer->hasConditionalRequired(['required_without:phone']));
        $this->assertTrue($this->analyzer->hasConditionalRequired(['required_with_all:email,phone']));
        $this->assertTrue($this->analyzer->hasConditionalRequired(['required_without_all:email,phone']));
    }

    #[Test]
    public function it_returns_false_for_non_conditional_required(): void
    {
        $this->assertFalse($this->analyzer->hasConditionalRequired(['required', 'string']));
        $this->assertFalse($this->analyzer->hasConditionalRequired(['string', 'max:255']));
    }

    #[Test]
    public function it_extracts_conditional_required_details(): void
    {
        $rules = ['required_if:status,active', 'string'];
        $details = $this->analyzer->extractConditionalRuleDetails($rules);

        $this->assertCount(1, $details);
        $this->assertEquals('required_if', $details[0]['type']);
        $this->assertEquals('status,active', $details[0]['parameters']);
        $this->assertEquals('required_if:status,active', $details[0]['full_rule']);
    }

    #[Test]
    public function it_extracts_prohibited_rule_details(): void
    {
        $rules = ['prohibited_if:status,inactive', 'string'];
        $details = $this->analyzer->extractConditionalRuleDetails($rules);

        $this->assertCount(1, $details);
        $this->assertEquals('prohibited_if', $details[0]['type']);
        $this->assertEquals('status,inactive', $details[0]['parameters']);
    }

    #[Test]
    public function it_extracts_exclude_rule_details(): void
    {
        $rules = ['exclude_if:role,guest', 'string'];
        $details = $this->analyzer->extractConditionalRuleDetails($rules);

        $this->assertCount(1, $details);
        $this->assertEquals('exclude_if', $details[0]['type']);
        $this->assertEquals('role,guest', $details[0]['parameters']);
    }

    #[Test]
    public function it_extracts_multiple_conditional_rules(): void
    {
        $rules = ['required_if:status,active', 'prohibited_unless:role,admin', 'string'];
        $details = $this->analyzer->extractConditionalRuleDetails($rules);

        $this->assertCount(2, $details);
        $this->assertEquals('required_if', $details[0]['type']);
        $this->assertEquals('prohibited_unless', $details[1]['type']);
    }

    #[Test]
    public function it_returns_empty_array_for_no_conditional_rules(): void
    {
        $rules = ['required', 'string', 'max:255'];
        $details = $this->analyzer->extractConditionalRuleDetails($rules);

        $this->assertEmpty($details);
    }

    #[Test]
    public function it_checks_required_in_any_condition(): void
    {
        $rulesByCondition = [
            ['condition' => 'status=active', 'rules' => ['string', 'max:255']],
            ['condition' => 'status=pending', 'rules' => ['required', 'string']],
        ];

        $this->assertTrue($this->analyzer->isRequiredInAnyCondition($rulesByCondition));
    }

    #[Test]
    public function it_returns_false_when_not_required_in_any_condition(): void
    {
        $rulesByCondition = [
            ['condition' => 'status=active', 'rules' => ['string', 'max:255']],
            ['condition' => 'status=pending', 'rules' => ['nullable', 'string']],
        ];

        $this->assertFalse($this->analyzer->isRequiredInAnyCondition($rulesByCondition));
    }

    #[Test]
    public function it_handles_string_rules_format(): void
    {
        $this->assertTrue($this->analyzer->isRequired('required|string|max:255'));
        $this->assertTrue($this->analyzer->hasConditionalRequired('required_if:active,1|string'));

        $details = $this->analyzer->extractConditionalRuleDetails('required_with:email|string');
        $this->assertCount(1, $details);
        $this->assertEquals('required_with', $details[0]['type']);
    }

    #[Test]
    public function it_handles_non_string_rules_in_array(): void
    {
        // Rule objects or closures should be skipped gracefully
        $rules = ['required', fn () => true, 'string'];
        $this->assertTrue($this->analyzer->isRequired($rules));

        $details = $this->analyzer->extractConditionalRuleDetails($rules);
        $this->assertEmpty($details);
    }
}
