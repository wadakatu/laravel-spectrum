<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ConditionalRuleDetail;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConditionalRuleDetailTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $detail = new ConditionalRuleDetail(
            type: 'required_if',
            parameters: 'status,active',
            fullRule: 'required_if:status,active',
        );

        $this->assertEquals('required_if', $detail->type);
        $this->assertEquals('status,active', $detail->parameters);
        $this->assertEquals('required_if:status,active', $detail->fullRule);
    }

    #[Test]
    public function it_can_be_constructed_with_empty_parameters(): void
    {
        $detail = new ConditionalRuleDetail(
            type: 'required_with',
            parameters: '',
            fullRule: 'required_with',
        );

        $this->assertEquals('required_with', $detail->type);
        $this->assertEquals('', $detail->parameters);
        $this->assertEquals('required_with', $detail->fullRule);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'type' => 'prohibited_if',
            'parameters' => 'role,guest',
            'full_rule' => 'prohibited_if:role,guest',
        ];

        $detail = ConditionalRuleDetail::fromArray($array);

        $this->assertEquals('prohibited_if', $detail->type);
        $this->assertEquals('role,guest', $detail->parameters);
        $this->assertEquals('prohibited_if:role,guest', $detail->fullRule);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [
            'type' => 'exclude_if',
        ];

        $detail = ConditionalRuleDetail::fromArray($array);

        $this->assertEquals('exclude_if', $detail->type);
        $this->assertEquals('', $detail->parameters);
        $this->assertEquals('', $detail->fullRule);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $detail = new ConditionalRuleDetail(
            type: 'required_unless',
            parameters: 'status,inactive',
            fullRule: 'required_unless:status,inactive',
        );

        $array = $detail->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('parameters', $array);
        $this->assertArrayHasKey('full_rule', $array);
        $this->assertEquals('required_unless', $array['type']);
        $this->assertEquals('status,inactive', $array['parameters']);
        $this->assertEquals('required_unless:status,inactive', $array['full_rule']);
    }

    #[Test]
    public function it_checks_if_is_required_rule(): void
    {
        $requiredIf = new ConditionalRuleDetail('required_if', 'status,active', 'required_if:status,active');
        $requiredWith = new ConditionalRuleDetail('required_with', 'email', 'required_with:email');
        $requiredWithAll = new ConditionalRuleDetail('required_with_all', 'a,b,c', 'required_with_all:a,b,c');
        $prohibitedIf = new ConditionalRuleDetail('prohibited_if', 'role,guest', 'prohibited_if:role,guest');
        $excludeIf = new ConditionalRuleDetail('exclude_if', 'type,free', 'exclude_if:type,free');

        $this->assertTrue($requiredIf->isRequiredRule());
        $this->assertTrue($requiredWith->isRequiredRule());
        $this->assertTrue($requiredWithAll->isRequiredRule());
        $this->assertFalse($prohibitedIf->isRequiredRule());
        $this->assertFalse($excludeIf->isRequiredRule());
    }

    #[Test]
    public function it_checks_if_is_prohibited_rule(): void
    {
        $prohibitedIf = new ConditionalRuleDetail('prohibited_if', 'role,guest', 'prohibited_if:role,guest');
        $prohibitedUnless = new ConditionalRuleDetail('prohibited_unless', 'role,admin', 'prohibited_unless:role,admin');
        $requiredIf = new ConditionalRuleDetail('required_if', 'status,active', 'required_if:status,active');

        $this->assertTrue($prohibitedIf->isProhibitedRule());
        $this->assertTrue($prohibitedUnless->isProhibitedRule());
        $this->assertFalse($requiredIf->isProhibitedRule());
    }

    #[Test]
    public function it_checks_if_is_exclude_rule(): void
    {
        $excludeIf = new ConditionalRuleDetail('exclude_if', 'type,free', 'exclude_if:type,free');
        $excludeUnless = new ConditionalRuleDetail('exclude_unless', 'type,premium', 'exclude_unless:type,premium');
        $requiredIf = new ConditionalRuleDetail('required_if', 'status,active', 'required_if:status,active');

        $this->assertTrue($excludeIf->isExcludeRule());
        $this->assertTrue($excludeUnless->isExcludeRule());
        $this->assertFalse($requiredIf->isExcludeRule());
    }

    #[Test]
    public function it_parses_parameters_to_array(): void
    {
        $detail = new ConditionalRuleDetail(
            type: 'required_if',
            parameters: 'status,active,1',
            fullRule: 'required_if:status,active,1',
        );

        $params = $detail->getParametersArray();

        $this->assertEquals(['status', 'active', '1'], $params);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_parameters(): void
    {
        $detail = new ConditionalRuleDetail(
            type: 'required_with',
            parameters: '',
            fullRule: 'required_with',
        );

        $params = $detail->getParametersArray();

        $this->assertEquals([], $params);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new ConditionalRuleDetail(
            type: 'required_without_all',
            parameters: 'field1,field2,field3',
            fullRule: 'required_without_all:field1,field2,field3',
        );

        $restored = ConditionalRuleDetail::fromArray($original->toArray());

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->parameters, $restored->parameters);
        $this->assertEquals($original->fullRule, $restored->fullRule);
    }
}
