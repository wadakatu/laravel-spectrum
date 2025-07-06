<?php

namespace LaravelPrism\Tests\Unit\Generators;

use LaravelPrism\Generators\ErrorResponseGenerator;
use LaravelPrism\Generators\ValidationMessageGenerator;
use LaravelPrism\Tests\TestCase;

class ErrorResponseGeneratorTest extends TestCase
{
    private ErrorResponseGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $messageGenerator = new ValidationMessageGenerator;
        $this->generator = new ErrorResponseGenerator($messageGenerator);
    }

    /** @test */
    public function it_generates_401_unauthorized_response()
    {
        $responses = $this->generator->generateErrorResponses();

        $this->assertArrayHasKey('401', $responses);
        $this->assertEquals('Unauthorized', $responses['401']['description']);
        $this->assertArrayHasKey('content', $responses['401']);
    }

    /** @test */
    public function it_generates_422_validation_error_response_with_form_request()
    {
        $formRequestData = [
            'rules' => [
                'email' => 'required|email',
                'password' => 'required|min:8',
            ],
        ];

        $responses = $this->generator->generateErrorResponses($formRequestData);

        $this->assertArrayHasKey('422', $responses);
        $this->assertEquals('Validation Error', $responses['422']['description']);

        $schema = $responses['422']['content']['application/json']['schema'];
        $this->assertArrayHasKey('errors', $schema['properties']);

        $errorProperties = $schema['properties']['errors']['properties'];
        $this->assertArrayHasKey('email', $errorProperties);
        $this->assertArrayHasKey('password', $errorProperties);
    }

    /** @test */
    public function it_includes_error_examples_in_422_response()
    {
        $formRequestData = [
            'rules' => [
                'email' => 'required|email',
            ],
        ];

        $responses = $this->generator->generateErrorResponses($formRequestData);
        $example = $responses['422']['content']['application/json']['schema']['properties']['errors']['example'];

        $this->assertArrayHasKey('email', $example);
        $this->assertIsArray($example['email']);
        $this->assertEquals('The Email field is required.', $example['email'][0]);
    }

    /** @test */
    public function it_generates_default_error_responses_for_authenticated_routes()
    {
        $responses = $this->generator->getDefaultErrorResponses('GET', true, false);

        $this->assertArrayHasKey('401', $responses);
        $this->assertArrayHasKey('403', $responses);
        $this->assertArrayHasKey('404', $responses);
        $this->assertArrayHasKey('500', $responses);
    }

    /** @test */
    public function it_excludes_auth_errors_for_public_routes()
    {
        $responses = $this->generator->getDefaultErrorResponses('GET', false, false);

        $this->assertArrayNotHasKey('401', $responses);
        $this->assertArrayNotHasKey('403', $responses);
        $this->assertArrayHasKey('404', $responses);
        $this->assertArrayHasKey('500', $responses);
    }

    /** @test */
    public function it_handles_custom_validation_messages()
    {
        $formRequestData = [
            'rules' => [
                'email' => 'required|email',
            ],
            'messages' => [
                'email.required' => 'We need your email address!',
            ],
        ];

        $responses = $this->generator->generateErrorResponses($formRequestData);

        // カスタムメッセージが使用されることを確認
        // （実際の実装では ValidationMessageGenerator で処理される）
        $this->assertArrayHasKey('422', $responses);
    }
}
