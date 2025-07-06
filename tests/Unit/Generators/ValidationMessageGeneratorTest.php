<?php

namespace LaravelPrism\Tests\Unit\Generators;

use LaravelPrism\Generators\ValidationMessageGenerator;
use LaravelPrism\Tests\TestCase;

class ValidationMessageGeneratorTest extends TestCase
{
    private ValidationMessageGenerator $generator;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ValidationMessageGenerator();
    }
    
    /** @test */
    public function it_generates_messages_for_required_rule()
    {
        $rules = ['name' => 'required|string'];
        $messages = $this->generator->generateMessages($rules);
        
        $this->assertContains('The Name field is required.', $messages['name']);
    }
    
    /** @test */
    public function it_generates_messages_for_email_rule()
    {
        $rules = ['email' => 'required|email'];
        $messages = $this->generator->generateMessages($rules);
        
        $this->assertContains('The Email must be a valid email address.', $messages['email']);
    }
    
    /** @test */
    public function it_generates_messages_for_min_rule_with_different_types()
    {
        // 文字列の場合
        $rules = ['name' => 'string|min:3'];
        $messages = $this->generator->generateMessages($rules);
        $this->assertContains('The Name must be at least 3 characters.', $messages['name']);
        
        // 数値の場合
        $rules = ['age' => 'integer|min:18'];
        $messages = $this->generator->generateMessages($rules);
        $this->assertContains('The Age must be at least 18.', $messages['age']);
        
        // 配列の場合
        $rules = ['tags' => 'array|min:2'];
        $messages = $this->generator->generateMessages($rules);
        $this->assertContains('The Tags must have at least 2 items.', $messages['tags']);
    }
    
    /** @test */
    public function it_uses_custom_messages_when_provided()
    {
        $rules = ['email' => 'required|email'];
        $customMessages = ['email.required' => 'メールアドレスは必須です。'];
        
        $messages = $this->generator->generateMessages($rules, $customMessages);
        
        $this->assertContains('メールアドレスは必須です。', $messages['email']);
    }
    
    /** @test */
    public function it_humanizes_field_names()
    {
        $rules = [
            'first_name' => 'required',
            'user.email' => 'required',
        ];
        
        $messages = $this->generator->generateMessages($rules);
        
        $this->assertContains('The First Name field is required.', $messages['first_name']);
        $this->assertContains('The User Email field is required.', $messages['user.email']);
    }
    
    /** @test */
    public function it_generates_sample_message()
    {
        $sampleMessage = $this->generator->generateSampleMessage('email', 'required|email');
        
        $this->assertEquals('The Email field is required.', $sampleMessage);
    }
}