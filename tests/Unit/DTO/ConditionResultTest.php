<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ConditionResult;
use LaravelSpectrum\DTO\ConditionType;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConditionResultTest extends TestCase
{
    #[Test]
    public function it_creates_http_method_condition(): void
    {
        $condition = ConditionResult::httpMethod(
            method: 'POST',
            expression: '$request->isMethod("POST")'
        );

        $this->assertSame(ConditionType::HttpMethod, $condition->type);
        $this->assertSame('POST', $condition->method);
        $this->assertSame('$request->isMethod("POST")', $condition->expression);
        $this->assertNull($condition->check);
        $this->assertNull($condition->field);
    }

    #[Test]
    public function it_creates_http_method_condition_without_method(): void
    {
        $condition = ConditionResult::httpMethod(
            method: null,
            expression: '$request->isMethod($var)'
        );

        $this->assertSame(ConditionType::HttpMethod, $condition->type);
        $this->assertNull($condition->method);
        $this->assertSame('$request->isMethod($var)', $condition->expression);
    }

    #[Test]
    public function it_creates_user_check_condition(): void
    {
        $condition = ConditionResult::userCheck(
            method: 'hasRole',
            expression: '$user->hasRole("admin")'
        );

        $this->assertSame(ConditionType::UserCheck, $condition->type);
        $this->assertSame('hasRole', $condition->method);
        $this->assertSame('$user->hasRole("admin")', $condition->expression);
    }

    #[Test]
    public function it_creates_request_field_condition_with_all_fields(): void
    {
        $condition = ConditionResult::requestField(
            check: 'has',
            field: 'email',
            expression: '$request->has("email")'
        );

        $this->assertSame(ConditionType::RequestField, $condition->type);
        $this->assertSame('has', $condition->check);
        $this->assertSame('email', $condition->field);
        $this->assertSame('$request->has("email")', $condition->expression);
    }

    #[Test]
    public function it_creates_request_field_condition_without_details(): void
    {
        $condition = ConditionResult::requestField(
            check: null,
            field: null,
            expression: '$request->input("field")'
        );

        $this->assertSame(ConditionType::RequestField, $condition->type);
        $this->assertNull($condition->check);
        $this->assertNull($condition->field);
    }

    #[Test]
    public function it_creates_rule_when_condition(): void
    {
        $condition = ConditionResult::ruleWhen(
            expression: 'Rule::when($this->isUpdate(), ["required"])'
        );

        $this->assertSame(ConditionType::RuleWhen, $condition->type);
        $this->assertSame('Rule::when($this->isUpdate(), ["required"])', $condition->expression);
        $this->assertNull($condition->method);
        $this->assertNull($condition->check);
        $this->assertNull($condition->field);
    }

    #[Test]
    public function it_creates_custom_condition(): void
    {
        $condition = ConditionResult::custom(
            expression: '$this->someCustomCheck()'
        );

        $this->assertSame(ConditionType::Custom, $condition->type);
        $this->assertSame('$this->someCustomCheck()', $condition->expression);
    }

    #[Test]
    public function it_creates_else_branch_condition(): void
    {
        $condition = ConditionResult::elseBranch();

        $this->assertSame(ConditionType::ElseBranch, $condition->type);
        $this->assertSame('Default case', $condition->expression);
    }

    #[Test]
    public function it_creates_else_branch_with_custom_description(): void
    {
        $condition = ConditionResult::elseBranch('Custom description');

        $this->assertSame(ConditionType::ElseBranch, $condition->type);
        $this->assertSame('Custom description', $condition->expression);
    }

    #[Test]
    public function it_identifies_http_method_type(): void
    {
        $condition = ConditionResult::httpMethod('GET', 'expr');

        $this->assertTrue($condition->isHttpMethod());
        $this->assertFalse($condition->isUserCheck());
        $this->assertFalse($condition->isRequestField());
        $this->assertFalse($condition->isRuleWhen());
        $this->assertFalse($condition->isCustom());
    }

    #[Test]
    public function it_identifies_user_check_type(): void
    {
        $condition = ConditionResult::userCheck('can', 'expr');

        $this->assertFalse($condition->isHttpMethod());
        $this->assertTrue($condition->isUserCheck());
        $this->assertFalse($condition->isRequestField());
        $this->assertFalse($condition->isRuleWhen());
        $this->assertFalse($condition->isCustom());
    }

    #[Test]
    public function it_identifies_request_field_type(): void
    {
        $condition = ConditionResult::requestField('filled', 'name', 'expr');

        $this->assertFalse($condition->isHttpMethod());
        $this->assertFalse($condition->isUserCheck());
        $this->assertTrue($condition->isRequestField());
        $this->assertFalse($condition->isRuleWhen());
        $this->assertFalse($condition->isCustom());
    }

    #[Test]
    public function it_identifies_rule_when_type(): void
    {
        $condition = ConditionResult::ruleWhen('expr');

        $this->assertFalse($condition->isHttpMethod());
        $this->assertFalse($condition->isUserCheck());
        $this->assertFalse($condition->isRequestField());
        $this->assertTrue($condition->isRuleWhen());
        $this->assertFalse($condition->isCustom());
    }

    #[Test]
    public function it_identifies_custom_type(): void
    {
        $condition = ConditionResult::custom('expr');

        $this->assertFalse($condition->isHttpMethod());
        $this->assertFalse($condition->isUserCheck());
        $this->assertFalse($condition->isRequestField());
        $this->assertFalse($condition->isRuleWhen());
        $this->assertTrue($condition->isCustom());
        $this->assertFalse($condition->isElseBranch());
    }

    #[Test]
    public function it_identifies_else_branch_type(): void
    {
        $condition = ConditionResult::elseBranch();

        $this->assertFalse($condition->isHttpMethod());
        $this->assertFalse($condition->isUserCheck());
        $this->assertFalse($condition->isRequestField());
        $this->assertFalse($condition->isRuleWhen());
        $this->assertFalse($condition->isCustom());
        $this->assertTrue($condition->isElseBranch());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $httpMethod = ConditionResult::httpMethod('POST', '$request->isMethod("POST")');
        $this->assertEquals([
            'type' => 'http_method',
            'method' => 'POST',
            'expression' => '$request->isMethod("POST")',
        ], $httpMethod->toArray());

        $userCheck = ConditionResult::userCheck('hasRole', '$user->hasRole("admin")');
        $this->assertEquals([
            'type' => 'user_check',
            'method' => 'hasRole',
            'expression' => '$user->hasRole("admin")',
        ], $userCheck->toArray());

        $requestField = ConditionResult::requestField('has', 'email', '$request->has("email")');
        $this->assertEquals([
            'type' => 'request_field',
            'check' => 'has',
            'field' => 'email',
            'expression' => '$request->has("email")',
        ], $requestField->toArray());

        $ruleWhen = ConditionResult::ruleWhen('Rule::when(true, [])');
        $this->assertEquals([
            'type' => 'rule_when',
            'expression' => 'Rule::when(true, [])',
        ], $ruleWhen->toArray());

        $custom = ConditionResult::custom('$this->check()');
        $this->assertEquals([
            'type' => 'custom',
            'expression' => '$this->check()',
        ], $custom->toArray());

        $elseBranch = ConditionResult::elseBranch('Default case');
        $this->assertEquals([
            'type' => 'else',
            'description' => 'Default case',
        ], $elseBranch->toArray());
    }

    #[Test]
    public function it_creates_from_array_http_method(): void
    {
        $data = [
            'type' => 'http_method',
            'method' => 'PUT',
            'expression' => '$request->isMethod("PUT")',
        ];

        $condition = ConditionResult::fromArray($data);

        $this->assertSame(ConditionType::HttpMethod, $condition->type);
        $this->assertSame('PUT', $condition->method);
        $this->assertSame('$request->isMethod("PUT")', $condition->expression);
    }

    #[Test]
    public function it_creates_from_array_user_check(): void
    {
        $data = [
            'type' => 'user_check',
            'method' => 'can',
            'expression' => '$user->can("edit")',
        ];

        $condition = ConditionResult::fromArray($data);

        $this->assertSame(ConditionType::UserCheck, $condition->type);
        $this->assertSame('can', $condition->method);
    }

    #[Test]
    public function it_creates_from_array_request_field(): void
    {
        $data = [
            'type' => 'request_field',
            'check' => 'filled',
            'field' => 'name',
            'expression' => '$request->filled("name")',
        ];

        $condition = ConditionResult::fromArray($data);

        $this->assertSame(ConditionType::RequestField, $condition->type);
        $this->assertSame('filled', $condition->check);
        $this->assertSame('name', $condition->field);
    }

    #[Test]
    public function it_creates_from_array_rule_when(): void
    {
        $data = [
            'type' => 'rule_when',
            'expression' => 'Rule::when()',
        ];

        $condition = ConditionResult::fromArray($data);

        $this->assertSame(ConditionType::RuleWhen, $condition->type);
    }

    #[Test]
    public function it_creates_from_array_custom(): void
    {
        $data = [
            'type' => 'custom',
            'expression' => 'custom expression',
        ];

        $condition = ConditionResult::fromArray($data);

        $this->assertSame(ConditionType::Custom, $condition->type);
    }

    #[Test]
    public function it_creates_from_array_else_branch(): void
    {
        $data = [
            'type' => 'else',
            'description' => 'Default case',
        ];

        $condition = ConditionResult::fromArray($data);

        $this->assertSame(ConditionType::ElseBranch, $condition->type);
        $this->assertSame('Default case', $condition->expression);
    }

    #[Test]
    public function it_performs_round_trip_serialization(): void
    {
        $conditions = [
            ConditionResult::httpMethod('DELETE', 'expr1'),
            ConditionResult::userCheck('hasPermission', 'expr2'),
            ConditionResult::requestField('has', 'id', 'expr3'),
            ConditionResult::ruleWhen('expr4'),
            ConditionResult::custom('expr5'),
            ConditionResult::elseBranch('Default case'),
        ];

        foreach ($conditions as $original) {
            $restored = ConditionResult::fromArray($original->toArray());

            $this->assertSame($original->type, $restored->type);
            $this->assertSame($original->expression, $restored->expression);
            $this->assertSame($original->method, $restored->method);
            $this->assertSame($original->check, $restored->check);
            $this->assertSame($original->field, $restored->field);
        }
    }

    #[Test]
    public function it_returns_type_as_string(): void
    {
        $httpMethod = ConditionResult::httpMethod('GET', 'expr');
        $this->assertSame('http_method', $httpMethod->getTypeAsString());

        $userCheck = ConditionResult::userCheck('can', 'expr');
        $this->assertSame('user_check', $userCheck->getTypeAsString());

        $requestField = ConditionResult::requestField('has', 'field', 'expr');
        $this->assertSame('request_field', $requestField->getTypeAsString());

        $ruleWhen = ConditionResult::ruleWhen('expr');
        $this->assertSame('rule_when', $ruleWhen->getTypeAsString());

        $custom = ConditionResult::custom('expr');
        $this->assertSame('custom', $custom->getTypeAsString());

        $elseBranch = ConditionResult::elseBranch();
        $this->assertSame('else', $elseBranch->getTypeAsString());
    }
}
