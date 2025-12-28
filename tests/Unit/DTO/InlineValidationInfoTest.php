<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\InlineValidationInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InlineValidationInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_all_properties(): void
    {
        $info = new InlineValidationInfo(
            rules: [
                'name' => 'required|string|max:255',
                'email' => ['required', 'email', 'unique:users'],
            ],
            messages: [
                'name.required' => 'The name field is required.',
                'email.unique' => 'This email is already taken.',
            ],
            attributes: [
                'name' => 'Full Name',
                'email' => 'Email Address',
            ],
        );

        $this->assertEquals([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'unique:users'],
        ], $info->rules);
        $this->assertEquals([
            'name.required' => 'The name field is required.',
            'email.unique' => 'This email is already taken.',
        ], $info->messages);
        $this->assertEquals([
            'name' => 'Full Name',
            'email' => 'Email Address',
        ], $info->attributes);
    }

    #[Test]
    public function it_creates_empty_instance(): void
    {
        $info = InlineValidationInfo::empty();

        $this->assertEquals([], $info->rules);
        $this->assertEquals([], $info->messages);
        $this->assertEquals([], $info->attributes);
        $this->assertTrue($info->isEmpty());
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'rules' => ['name' => 'required'],
            'messages' => ['name.required' => 'Name is required'],
            'attributes' => ['name' => 'Full Name'],
        ];

        $info = InlineValidationInfo::fromArray($data);

        $this->assertEquals(['name' => 'required'], $info->rules);
        $this->assertEquals(['name.required' => 'Name is required'], $info->messages);
        $this->assertEquals(['name' => 'Full Name'], $info->attributes);
    }

    #[Test]
    public function it_creates_from_array_with_missing_keys(): void
    {
        $data = [
            'rules' => ['email' => 'required|email'],
        ];

        $info = InlineValidationInfo::fromArray($data);

        $this->assertEquals(['email' => 'required|email'], $info->rules);
        $this->assertEquals([], $info->messages);
        $this->assertEquals([], $info->attributes);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new InlineValidationInfo(
            rules: ['name' => 'required'],
            messages: ['name.required' => 'Required'],
            attributes: ['name' => 'Name'],
        );

        $array = $info->toArray();

        $this->assertEquals([
            'rules' => ['name' => 'required'],
            'messages' => ['name.required' => 'Required'],
            'attributes' => ['name' => 'Name'],
        ], $array);
    }

    #[Test]
    public function it_detects_rules(): void
    {
        $withRules = new InlineValidationInfo(rules: ['name' => 'required']);
        $withoutRules = InlineValidationInfo::empty();

        $this->assertTrue($withRules->hasRules());
        $this->assertFalse($withoutRules->hasRules());
    }

    #[Test]
    public function it_detects_messages(): void
    {
        $withMessages = new InlineValidationInfo(messages: ['name.required' => 'Required']);
        $withoutMessages = InlineValidationInfo::empty();

        $this->assertTrue($withMessages->hasMessages());
        $this->assertFalse($withoutMessages->hasMessages());
    }

    #[Test]
    public function it_detects_attributes(): void
    {
        $withAttributes = new InlineValidationInfo(attributes: ['name' => 'Full Name']);
        $withoutAttributes = InlineValidationInfo::empty();

        $this->assertTrue($withAttributes->hasAttributes());
        $this->assertFalse($withoutAttributes->hasAttributes());
    }

    #[Test]
    public function it_checks_if_empty(): void
    {
        $empty = InlineValidationInfo::empty();
        $notEmpty = new InlineValidationInfo(rules: ['name' => 'required']);

        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($notEmpty->isEmpty());
    }

    #[Test]
    public function it_gets_field_names(): void
    {
        $info = new InlineValidationInfo(rules: [
            'name' => 'required',
            'email' => 'email',
            'password' => 'min:8',
        ]);

        $this->assertEquals(['name', 'email', 'password'], $info->getFieldNames());
    }

    #[Test]
    public function it_gets_rule_for_field(): void
    {
        $info = new InlineValidationInfo(rules: [
            'name' => 'required|string',
            'tags' => ['array', 'min:1'],
        ]);

        $this->assertEquals('required|string', $info->getRuleForField('name'));
        $this->assertEquals(['array', 'min:1'], $info->getRuleForField('tags'));
        $this->assertNull($info->getRuleForField('nonexistent'));
    }

    #[Test]
    public function it_checks_rule_for_field(): void
    {
        $info = new InlineValidationInfo(rules: ['name' => 'required']);

        $this->assertTrue($info->hasRuleForField('name'));
        $this->assertFalse($info->hasRuleForField('email'));
    }

    #[Test]
    public function it_handles_array_rules(): void
    {
        $info = new InlineValidationInfo(rules: [
            'items' => ['required', 'array'],
            'items.*' => ['string', 'max:255'],
        ]);

        $this->assertEquals(['required', 'array'], $info->getRuleForField('items'));
        $this->assertEquals(['string', 'max:255'], $info->getRuleForField('items.*'));
    }
}
