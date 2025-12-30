<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ConditionalRule;
use LaravelSpectrum\DTO\ConditionResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConditionalRuleTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $condition = ConditionResult::httpMethod('POST', 'isMethod("POST")');
        $rule = new ConditionalRule(
            conditions: [$condition],
            rules: ['name' => 'required', 'email' => 'required|email'],
            probability: 0.8,
        );

        $this->assertCount(1, $rule->conditions);
        $this->assertInstanceOf(ConditionResult::class, $rule->conditions[0]);
        $this->assertEquals(['name' => 'required', 'email' => 'required|email'], $rule->rules);
        $this->assertEquals(0.8, $rule->probability);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'conditions' => [
                ['type' => 'http_method', 'expression' => 'isMethod("PUT")', 'method' => 'PUT'],
            ],
            'rules' => ['id' => 'required|integer'],
            'probability' => 0.5,
        ];

        $rule = ConditionalRule::fromArray($array);

        $this->assertCount(1, $rule->conditions);
        $this->assertInstanceOf(ConditionResult::class, $rule->conditions[0]);
        $this->assertTrue($rule->conditions[0]->isHttpMethod());
        $this->assertEquals('PUT', $rule->conditions[0]->method);
        $this->assertEquals(['id' => 'required|integer'], $rule->rules);
        $this->assertEquals(0.5, $rule->probability);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [];

        $rule = ConditionalRule::fromArray($array);

        $this->assertEquals([], $rule->conditions);
        $this->assertEquals([], $rule->rules);
        $this->assertEquals(1.0, $rule->probability);
    }

    #[Test]
    public function it_creates_from_array_with_condition_result_objects(): void
    {
        $condition = ConditionResult::httpMethod('POST', 'isMethod("POST")');
        $array = [
            'conditions' => [$condition],
            'rules' => ['name' => 'required'],
        ];

        $rule = ConditionalRule::fromArray($array);

        $this->assertSame($condition, $rule->conditions[0]);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $condition = ConditionResult::httpMethod('POST', 'isMethod("POST")');
        $rule = new ConditionalRule(
            conditions: [$condition],
            rules: ['role' => 'required', 'permissions' => 'array'],
            probability: 0.75,
        );

        $array = $rule->toArray();

        $this->assertArrayHasKey('conditions', $array);
        $this->assertArrayHasKey('rules', $array);
        $this->assertArrayHasKey('probability', $array);
        // ConditionResult objects are preserved for backward compatibility
        $this->assertInstanceOf(ConditionResult::class, $array['conditions'][0]);
        $this->assertTrue($array['conditions'][0]->isHttpMethod());
        $this->assertEquals('POST', $array['conditions'][0]->method);
        $this->assertEquals(['role' => 'required', 'permissions' => 'array'], $array['rules']);
        $this->assertEquals(0.75, $array['probability']);
    }

    #[Test]
    public function it_checks_if_has_rules(): void
    {
        $withRules = new ConditionalRule(
            conditions: [],
            rules: ['field' => 'required'],
        );
        $withoutRules = new ConditionalRule(
            conditions: [],
            rules: [],
        );

        $this->assertTrue($withRules->hasRules());
        $this->assertFalse($withoutRules->hasRules());
    }

    #[Test]
    public function it_checks_if_has_conditions(): void
    {
        $withConditions = new ConditionalRule(
            conditions: [ConditionResult::httpMethod('POST', 'isMethod("POST")')],
            rules: [],
        );
        $withoutConditions = new ConditionalRule(
            conditions: [],
            rules: [],
        );

        $this->assertTrue($withConditions->hasConditions());
        $this->assertFalse($withoutConditions->hasConditions());
    }

    #[Test]
    public function it_checks_if_is_http_method_condition(): void
    {
        $httpMethodCondition = new ConditionalRule(
            conditions: [ConditionResult::httpMethod('POST', 'isMethod("POST")')],
            rules: ['name' => 'required'],
        );
        $userCheckCondition = new ConditionalRule(
            conditions: [ConditionResult::userCheck('isAdmin()', 'isAdmin')],
            rules: ['role' => 'required'],
        );
        $noCondition = new ConditionalRule(
            conditions: [],
            rules: ['field' => 'required'],
        );

        $this->assertTrue($httpMethodCondition->isHttpMethodCondition());
        $this->assertFalse($userCheckCondition->isHttpMethodCondition());
        $this->assertFalse($noCondition->isHttpMethodCondition());
    }

    #[Test]
    public function it_gets_http_method(): void
    {
        $postCondition = new ConditionalRule(
            conditions: [ConditionResult::httpMethod('POST', 'isMethod("POST")')],
            rules: [],
        );
        $userCheckCondition = new ConditionalRule(
            conditions: [ConditionResult::userCheck('isAdmin()', 'isAdmin')],
            rules: [],
        );

        $this->assertEquals('POST', $postCondition->getHttpMethod());
        $this->assertNull($userCheckCondition->getHttpMethod());
    }

    #[Test]
    public function it_counts_rules(): void
    {
        $empty = new ConditionalRule(conditions: [], rules: []);
        $withRules = new ConditionalRule(
            conditions: [],
            rules: ['a' => 'required', 'b' => 'string', 'c' => 'integer'],
        );

        $this->assertEquals(0, $empty->getRuleCount());
        $this->assertEquals(3, $withRules->getRuleCount());
    }

    #[Test]
    public function it_gets_rule_field_names(): void
    {
        $rule = new ConditionalRule(
            conditions: [],
            rules: ['name' => 'required', 'email' => 'required|email', 'age' => 'integer'],
        );

        $fieldNames = $rule->getFieldNames();

        $this->assertCount(3, $fieldNames);
        $this->assertContains('name', $fieldNames);
        $this->assertContains('email', $fieldNames);
        $this->assertContains('age', $fieldNames);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new ConditionalRule(
            conditions: [ConditionResult::httpMethod('DELETE', 'isMethod("DELETE")')],
            rules: ['id' => 'required|integer', 'confirm' => 'boolean'],
            probability: 0.9,
        );

        $restored = ConditionalRule::fromArray($original->toArray());

        $this->assertCount(1, $restored->conditions);
        $this->assertInstanceOf(ConditionResult::class, $restored->conditions[0]);
        $this->assertTrue($restored->conditions[0]->isHttpMethod());
        $this->assertEquals('DELETE', $restored->conditions[0]->method);
        $this->assertEquals($original->rules, $restored->rules);
        $this->assertEquals($original->probability, $restored->probability);
    }
}
