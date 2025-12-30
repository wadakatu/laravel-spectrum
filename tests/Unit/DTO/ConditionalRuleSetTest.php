<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ConditionalRule;
use LaravelSpectrum\DTO\ConditionalRuleSet;
use LaravelSpectrum\DTO\ConditionResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConditionalRuleSetTest extends TestCase
{
    private function createRule(array $conditions, array $rules = [], float $probability = 1.0): ConditionalRule
    {
        return new ConditionalRule(
            conditions: $conditions,
            rules: $rules,
            probability: $probability,
        );
    }

    #[Test]
    public function it_can_be_constructed(): void
    {
        $ruleSet = new ConditionalRuleSet(
            ruleSets: [
                $this->createRule([ConditionResult::httpMethod('POST', 'isMethod("POST")')], ['name' => 'required']),
            ],
            mergedRules: ['name' => 'required|string'],
            hasConditions: true,
        );

        $this->assertCount(1, $ruleSet->ruleSets);
        $this->assertInstanceOf(ConditionalRule::class, $ruleSet->ruleSets[0]);
        $this->assertTrue($ruleSet->ruleSets[0]->conditions[0]->isHttpMethod());
        $this->assertEquals('POST', $ruleSet->ruleSets[0]->conditions[0]->method);
        $this->assertEquals(['name' => 'required'], $ruleSet->ruleSets[0]->rules);
        $this->assertEquals(['name' => 'required|string'], $ruleSet->mergedRules);
        $this->assertTrue($ruleSet->hasConditions);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'rules_sets' => [
                ['conditions' => [['type' => 'http_method', 'method' => 'PUT', 'expression' => 'isMethod("PUT")']], 'rules' => ['id' => 'required'], 'probability' => 0.5],
            ],
            'merged_rules' => ['id' => 'required|integer'],
            'has_conditions' => true,
        ];

        $ruleSet = ConditionalRuleSet::fromArray($array);

        $this->assertCount(1, $ruleSet->ruleSets);
        $this->assertInstanceOf(ConditionalRule::class, $ruleSet->ruleSets[0]);
        $this->assertInstanceOf(ConditionResult::class, $ruleSet->ruleSets[0]->conditions[0]);
        $this->assertEquals(['id' => 'required|integer'], $ruleSet->mergedRules);
        $this->assertTrue($ruleSet->hasConditions);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [];

        $ruleSet = ConditionalRuleSet::fromArray($array);

        $this->assertEquals([], $ruleSet->ruleSets);
        $this->assertEquals([], $ruleSet->mergedRules);
        $this->assertFalse($ruleSet->hasConditions);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $ruleSet = new ConditionalRuleSet(
            ruleSets: [$this->createRule([ConditionResult::httpMethod('POST', 'isMethod("POST")')], ['field' => 'required'], 0.8)],
            mergedRules: ['field' => 'required'],
            hasConditions: true,
        );

        $array = $ruleSet->toArray();

        $this->assertArrayHasKey('rules_sets', $array);
        $this->assertArrayHasKey('merged_rules', $array);
        $this->assertArrayHasKey('has_conditions', $array);
        $this->assertCount(1, $array['rules_sets']);
        $this->assertArrayHasKey('conditions', $array['rules_sets'][0]);
        $this->assertArrayHasKey('rules', $array['rules_sets'][0]);
        $this->assertArrayHasKey('probability', $array['rules_sets'][0]);
        // ConditionResult objects are preserved for backward compatibility
        $this->assertInstanceOf(ConditionResult::class, $array['rules_sets'][0]['conditions'][0]);
        $this->assertTrue($array['rules_sets'][0]['conditions'][0]->isHttpMethod());
        $this->assertEquals(['field' => 'required'], $array['rules_sets'][0]['rules']);
        $this->assertEquals(0.8, $array['rules_sets'][0]['probability']);
        $this->assertEquals(['field' => 'required'], $array['merged_rules']);
        $this->assertTrue($array['has_conditions']);
    }

    #[Test]
    public function it_creates_empty_instance(): void
    {
        $ruleSet = ConditionalRuleSet::empty();

        $this->assertEquals([], $ruleSet->ruleSets);
        $this->assertEquals([], $ruleSet->mergedRules);
        $this->assertFalse($ruleSet->hasConditions);
    }

    #[Test]
    public function it_checks_if_empty(): void
    {
        $empty = ConditionalRuleSet::empty();
        $notEmpty = new ConditionalRuleSet(
            ruleSets: [$this->createRule([], [])],
            mergedRules: [],
            hasConditions: true,
        );

        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($notEmpty->isEmpty());
    }

    #[Test]
    public function it_gets_all_conditions(): void
    {
        $ruleSet = new ConditionalRuleSet(
            ruleSets: [
                $this->createRule([ConditionResult::httpMethod('POST', 'isMethod("POST")')], ['name' => 'required']),
                $this->createRule([ConditionResult::httpMethod('PUT', 'isMethod("PUT")')], ['id' => 'required']),
                $this->createRule([ConditionResult::requestField('type=admin', 'type', 'admin')], ['role' => 'required']),
            ],
            mergedRules: [],
            hasConditions: true,
        );

        $conditions = $ruleSet->getAllConditions();

        $this->assertCount(3, $conditions);
        $this->assertInstanceOf(ConditionResult::class, $conditions[0]);
        $this->assertInstanceOf(ConditionResult::class, $conditions[1]);
        $this->assertInstanceOf(ConditionResult::class, $conditions[2]);
    }

    #[Test]
    public function it_gets_rules_for_http_method(): void
    {
        $ruleSet = new ConditionalRuleSet(
            ruleSets: [
                $this->createRule([ConditionResult::httpMethod('POST', 'isMethod("POST")')], ['name' => 'required', 'email' => 'required|email']),
                $this->createRule([ConditionResult::httpMethod('PUT', 'isMethod("PUT")')], ['id' => 'required|integer']),
            ],
            mergedRules: [],
            hasConditions: true,
        );

        $postRules = $ruleSet->getRulesForHttpMethod('POST');
        $putRules = $ruleSet->getRulesForHttpMethod('PUT');
        $deleteRules = $ruleSet->getRulesForHttpMethod('DELETE');

        $this->assertEquals(['name' => 'required', 'email' => 'required|email'], $postRules);
        $this->assertEquals(['id' => 'required|integer'], $putRules);
        $this->assertEquals([], $deleteRules);
    }

    #[Test]
    public function it_counts_rule_sets(): void
    {
        $empty = ConditionalRuleSet::empty();
        $withRules = new ConditionalRuleSet(
            ruleSets: [
                $this->createRule([], []),
                $this->createRule([], []),
            ],
            mergedRules: [],
            hasConditions: true,
        );

        $this->assertEquals(0, $empty->count());
        $this->assertEquals(2, $withRules->count());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new ConditionalRuleSet(
            ruleSets: [
                $this->createRule([ConditionResult::httpMethod('POST', 'isMethod("POST")')], ['name' => 'required'], 0.8),
                $this->createRule([ConditionResult::httpMethod('PUT', 'isMethod("PUT")')], ['id' => 'required'], 0.6),
            ],
            mergedRules: ['name' => 'required', 'id' => 'required'],
            hasConditions: true,
        );

        $restored = ConditionalRuleSet::fromArray($original->toArray());

        $this->assertCount(2, $restored->ruleSets);
        $this->assertInstanceOf(ConditionalRule::class, $restored->ruleSets[0]);
        $this->assertInstanceOf(ConditionResult::class, $restored->ruleSets[0]->conditions[0]);
        $this->assertEquals('POST', $restored->ruleSets[0]->conditions[0]->method);
        $this->assertEquals($original->ruleSets[0]->rules, $restored->ruleSets[0]->rules);
        $this->assertEquals($original->ruleSets[0]->probability, $restored->ruleSets[0]->probability);
        $this->assertEquals($original->mergedRules, $restored->mergedRules);
        $this->assertEquals($original->hasConditions, $restored->hasConditions);
    }
}
