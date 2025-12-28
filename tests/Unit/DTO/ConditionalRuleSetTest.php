<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ConditionalRuleSet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConditionalRuleSetTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $ruleSet = new ConditionalRuleSet(
            ruleSets: [
                ['condition' => 'http_method:POST', 'rules' => ['name' => 'required']],
            ],
            mergedRules: ['name' => 'required|string'],
            hasConditions: true,
        );

        $this->assertEquals([['condition' => 'http_method:POST', 'rules' => ['name' => 'required']]], $ruleSet->ruleSets);
        $this->assertEquals(['name' => 'required|string'], $ruleSet->mergedRules);
        $this->assertTrue($ruleSet->hasConditions);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'rules_sets' => [
                ['condition' => 'http_method:PUT', 'rules' => ['id' => 'required']],
            ],
            'merged_rules' => ['id' => 'required|integer'],
            'has_conditions' => true,
        ];

        $ruleSet = ConditionalRuleSet::fromArray($array);

        $this->assertCount(1, $ruleSet->ruleSets);
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
            ruleSets: [['condition' => 'test', 'rules' => ['field' => 'required']]],
            mergedRules: ['field' => 'required'],
            hasConditions: true,
        );

        $array = $ruleSet->toArray();

        $this->assertArrayHasKey('rules_sets', $array);
        $this->assertArrayHasKey('merged_rules', $array);
        $this->assertArrayHasKey('has_conditions', $array);
        $this->assertEquals([['condition' => 'test', 'rules' => ['field' => 'required']]], $array['rules_sets']);
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
            ruleSets: [['condition' => 'test', 'rules' => []]],
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
                ['condition' => 'http_method:POST', 'rules' => ['name' => 'required']],
                ['condition' => 'http_method:PUT', 'rules' => ['id' => 'required']],
                ['condition' => 'request_field:type=admin', 'rules' => ['role' => 'required']],
            ],
            mergedRules: [],
            hasConditions: true,
        );

        $conditions = $ruleSet->getAllConditions();

        $this->assertCount(3, $conditions);
        $this->assertContains('http_method:POST', $conditions);
        $this->assertContains('http_method:PUT', $conditions);
        $this->assertContains('request_field:type=admin', $conditions);
    }

    #[Test]
    public function it_gets_rules_for_condition(): void
    {
        $ruleSet = new ConditionalRuleSet(
            ruleSets: [
                ['condition' => 'http_method:POST', 'rules' => ['name' => 'required', 'email' => 'required|email']],
                ['condition' => 'http_method:PUT', 'rules' => ['id' => 'required|integer']],
            ],
            mergedRules: [],
            hasConditions: true,
        );

        $postRules = $ruleSet->getRulesForCondition('http_method:POST');
        $putRules = $ruleSet->getRulesForCondition('http_method:PUT');
        $deleteRules = $ruleSet->getRulesForCondition('http_method:DELETE');

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
                ['condition' => 'a', 'rules' => []],
                ['condition' => 'b', 'rules' => []],
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
                ['condition' => 'http_method:POST', 'rules' => ['name' => 'required']],
                ['condition' => 'http_method:PUT', 'rules' => ['id' => 'required']],
            ],
            mergedRules: ['name' => 'required', 'id' => 'required'],
            hasConditions: true,
        );

        $restored = ConditionalRuleSet::fromArray($original->toArray());

        $this->assertEquals($original->ruleSets, $restored->ruleSets);
        $this->assertEquals($original->mergedRules, $restored->mergedRules);
        $this->assertEquals($original->hasConditions, $restored->hasConditions);
    }
}
