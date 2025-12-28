<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ConditionalRuleSet;
use LaravelSpectrum\DTO\ValidationAnalysisResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValidationAnalysisResultTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $conditionalRules = new ConditionalRuleSet(
            ruleSets: [],
            mergedRules: [],
            hasConditions: false,
        );

        $result = new ValidationAnalysisResult(
            parameters: [
                ['name' => 'email', 'in' => 'body', 'required' => true],
            ],
            conditionalRules: $conditionalRules,
            attributes: ['email' => 'Email Address'],
            messages: ['email.required' => 'Email is required'],
        );

        $this->assertCount(1, $result->parameters);
        $this->assertInstanceOf(ConditionalRuleSet::class, $result->conditionalRules);
        $this->assertEquals(['email' => 'Email Address'], $result->attributes);
        $this->assertEquals(['email.required' => 'Email is required'], $result->messages);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'parameters' => [
                ['name' => 'name', 'in' => 'body', 'required' => true],
            ],
            'conditional_rules' => [
                'rules_sets' => [['condition' => 'test', 'rules' => []]],
                'merged_rules' => ['name' => 'required'],
                'has_conditions' => true,
            ],
            'attributes' => ['name' => 'Name'],
            'messages' => [],
        ];

        $result = ValidationAnalysisResult::fromArray($array);

        $this->assertCount(1, $result->parameters);
        $this->assertInstanceOf(ConditionalRuleSet::class, $result->conditionalRules);
        $this->assertTrue($result->conditionalRules->hasConditions);
        $this->assertEquals(['name' => 'Name'], $result->attributes);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [
            'parameters' => [],
        ];

        $result = ValidationAnalysisResult::fromArray($array);

        $this->assertEquals([], $result->parameters);
        $this->assertInstanceOf(ConditionalRuleSet::class, $result->conditionalRules);
        $this->assertFalse($result->conditionalRules->hasConditions);
        $this->assertEquals([], $result->attributes);
        $this->assertEquals([], $result->messages);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $result = new ValidationAnalysisResult(
            parameters: [['name' => 'id', 'in' => 'body', 'required' => true]],
            conditionalRules: new ConditionalRuleSet(
                ruleSets: [['condition' => 'test', 'rules' => ['id' => 'required']]],
                mergedRules: ['id' => 'required'],
                hasConditions: true,
            ),
            attributes: ['id' => 'ID'],
            messages: ['id.required' => 'ID is required'],
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('parameters', $array);
        $this->assertArrayHasKey('conditional_rules', $array);
        $this->assertArrayHasKey('attributes', $array);
        $this->assertArrayHasKey('messages', $array);
        $this->assertIsArray($array['conditional_rules']);
        $this->assertArrayHasKey('rules_sets', $array['conditional_rules']);
    }

    #[Test]
    public function it_creates_empty_instance(): void
    {
        $result = ValidationAnalysisResult::empty();

        $this->assertEquals([], $result->parameters);
        $this->assertTrue($result->conditionalRules->isEmpty());
        $this->assertEquals([], $result->attributes);
        $this->assertEquals([], $result->messages);
    }

    #[Test]
    public function it_checks_if_has_conditional_rules(): void
    {
        $withConditions = new ValidationAnalysisResult(
            parameters: [],
            conditionalRules: new ConditionalRuleSet(
                ruleSets: [['condition' => 'test', 'rules' => []]],
                mergedRules: [],
                hasConditions: true,
            ),
        );
        $withoutConditions = ValidationAnalysisResult::empty();

        $this->assertTrue($withConditions->hasConditionalRules());
        $this->assertFalse($withoutConditions->hasConditionalRules());
    }

    #[Test]
    public function it_checks_if_has_parameters(): void
    {
        $withParams = new ValidationAnalysisResult(
            parameters: [['name' => 'test']],
            conditionalRules: ConditionalRuleSet::empty(),
        );
        $withoutParams = ValidationAnalysisResult::empty();

        $this->assertTrue($withParams->hasParameters());
        $this->assertFalse($withoutParams->hasParameters());
    }

    #[Test]
    public function it_gets_parameter_by_name(): void
    {
        $result = new ValidationAnalysisResult(
            parameters: [
                ['name' => 'email', 'type' => 'string'],
                ['name' => 'age', 'type' => 'integer'],
            ],
            conditionalRules: ConditionalRuleSet::empty(),
        );

        $email = $result->getParameterByName('email');
        $age = $result->getParameterByName('age');
        $missing = $result->getParameterByName('nonexistent');

        $this->assertEquals(['name' => 'email', 'type' => 'string'], $email);
        $this->assertEquals(['name' => 'age', 'type' => 'integer'], $age);
        $this->assertNull($missing);
    }

    #[Test]
    public function it_gets_required_parameters(): void
    {
        $result = new ValidationAnalysisResult(
            parameters: [
                ['name' => 'email', 'required' => true],
                ['name' => 'name', 'required' => true],
                ['name' => 'nickname', 'required' => false],
            ],
            conditionalRules: ConditionalRuleSet::empty(),
        );

        $required = $result->getRequiredParameters();

        $this->assertCount(2, $required);
        $this->assertEquals('email', $required[0]['name']);
        $this->assertEquals('name', $required[1]['name']);
    }

    #[Test]
    public function it_gets_parameter_names(): void
    {
        $result = new ValidationAnalysisResult(
            parameters: [
                ['name' => 'email'],
                ['name' => 'password'],
                ['name' => 'confirm_password'],
            ],
            conditionalRules: ConditionalRuleSet::empty(),
        );

        $names = $result->getParameterNames();

        $this->assertEquals(['email', 'password', 'confirm_password'], $names);
    }

    #[Test]
    public function it_checks_if_empty(): void
    {
        $empty = ValidationAnalysisResult::empty();
        $withParams = new ValidationAnalysisResult(
            parameters: [['name' => 'test']],
            conditionalRules: ConditionalRuleSet::empty(),
        );
        $withConditions = new ValidationAnalysisResult(
            parameters: [],
            conditionalRules: new ConditionalRuleSet(
                ruleSets: [['condition' => 'test', 'rules' => []]],
                mergedRules: [],
                hasConditions: true,
            ),
        );

        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($withParams->isEmpty());
        $this->assertFalse($withConditions->isEmpty());
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new ValidationAnalysisResult(
            parameters: [
                ['name' => 'email', 'type' => 'string', 'required' => true],
                ['name' => 'age', 'type' => 'integer', 'required' => false],
            ],
            conditionalRules: new ConditionalRuleSet(
                ruleSets: [['condition' => 'http_method:POST', 'rules' => ['name' => 'required']]],
                mergedRules: ['name' => 'required', 'email' => 'required|email'],
                hasConditions: true,
            ),
            attributes: ['email' => 'Email', 'age' => 'Age'],
            messages: ['email.required' => 'Email is required'],
        );

        $restored = ValidationAnalysisResult::fromArray($original->toArray());

        $this->assertEquals($original->parameters, $restored->parameters);
        $this->assertEquals($original->conditionalRules->ruleSets, $restored->conditionalRules->ruleSets);
        $this->assertEquals($original->conditionalRules->mergedRules, $restored->conditionalRules->mergedRules);
        $this->assertEquals($original->attributes, $restored->attributes);
        $this->assertEquals($original->messages, $restored->messages);
    }

    #[Test]
    public function it_counts_parameters(): void
    {
        $empty = ValidationAnalysisResult::empty();
        $withParams = new ValidationAnalysisResult(
            parameters: [
                ['name' => 'a'],
                ['name' => 'b'],
                ['name' => 'c'],
            ],
            conditionalRules: ConditionalRuleSet::empty(),
        );

        $this->assertEquals(0, $empty->count());
        $this->assertEquals(3, $withParams->count());
    }

    #[Test]
    public function it_gets_attribute_for_parameter(): void
    {
        $result = new ValidationAnalysisResult(
            parameters: [['name' => 'email']],
            conditionalRules: ConditionalRuleSet::empty(),
            attributes: ['email' => 'Email Address', 'name' => 'Full Name'],
        );

        $this->assertEquals('Email Address', $result->getAttributeFor('email'));
        $this->assertEquals('Full Name', $result->getAttributeFor('name'));
        $this->assertNull($result->getAttributeFor('nonexistent'));
    }

    #[Test]
    public function it_gets_message_for_rule(): void
    {
        $result = new ValidationAnalysisResult(
            parameters: [],
            conditionalRules: ConditionalRuleSet::empty(),
            messages: [
                'email.required' => 'Email is required',
                'email.email' => 'Must be valid email',
            ],
        );

        $this->assertEquals('Email is required', $result->getMessageFor('email.required'));
        $this->assertEquals('Must be valid email', $result->getMessageFor('email.email'));
        $this->assertNull($result->getMessageFor('nonexistent'));
    }
}
