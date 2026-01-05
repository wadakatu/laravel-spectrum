<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\DTO\ResponseType;
use LaravelSpectrum\Generators\ResponseSchemaGenerator;
use LaravelSpectrum\Tests\TestCase;

/**
 * Integration tests for non-JSON response types in OpenAPI generation.
 */
class NonJsonResponseTest extends TestCase
{
    private ResponseSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ResponseSchemaGenerator;
    }

    public function test_generates_binary_file_response_with_pdf_content_type(): void
    {
        $responseData = [
            'type' => ResponseType::BINARY_FILE->value,
            'contentType' => 'application/pdf',
            'fileName' => 'report.pdf',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('File download: report.pdf', $result[200]['description']);
        $this->assertArrayHasKey('application/pdf', $result[200]['content']);
        $this->assertEquals('string', $result[200]['content']['application/pdf']['schema']['type']);
        $this->assertEquals('binary', $result[200]['content']['application/pdf']['schema']['format']);
    }

    public function test_generates_binary_file_response_without_filename(): void
    {
        $responseData = [
            'type' => ResponseType::BINARY_FILE->value,
            'contentType' => 'application/octet-stream',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('File download', $result[200]['description']);
        $this->assertArrayHasKey('application/octet-stream', $result[200]['content']);
        $this->assertEquals('string', $result[200]['content']['application/octet-stream']['schema']['type']);
        $this->assertEquals('binary', $result[200]['content']['application/octet-stream']['schema']['format']);
    }

    public function test_generates_streamed_response_with_content_type(): void
    {
        $responseData = [
            'type' => ResponseType::STREAMED->value,
            'contentType' => 'text/event-stream',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('Streamed response', $result[200]['description']);
        $this->assertArrayHasKey('text/event-stream', $result[200]['content']);
        $this->assertEquals('string', $result[200]['content']['text/event-stream']['schema']['type']);
        $this->assertEquals('binary', $result[200]['content']['text/event-stream']['schema']['format']);
    }

    public function test_generates_streamed_response_with_filename(): void
    {
        $responseData = [
            'type' => ResponseType::STREAMED->value,
            'contentType' => 'text/csv',
            'fileName' => 'export.csv',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('Streamed response: export.csv', $result[200]['description']);
        $this->assertArrayHasKey('text/csv', $result[200]['content']);
    }

    public function test_generates_xml_response(): void
    {
        $responseData = [
            'type' => ResponseType::XML->value,
            'contentType' => 'application/xml',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('XML response', $result[200]['description']);
        $this->assertArrayHasKey('application/xml', $result[200]['content']);
        $this->assertEquals('string', $result[200]['content']['application/xml']['schema']['type']);
        $this->assertArrayNotHasKey('format', $result[200]['content']['application/xml']['schema']);
    }

    public function test_generates_plain_text_response(): void
    {
        $responseData = [
            'type' => ResponseType::PLAIN_TEXT->value,
            'contentType' => 'text/plain',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('Plain text response', $result[200]['description']);
        $this->assertArrayHasKey('text/plain', $result[200]['content']);
        $this->assertEquals('string', $result[200]['content']['text/plain']['schema']['type']);
    }

    public function test_generates_html_response(): void
    {
        $responseData = [
            'type' => ResponseType::HTML->value,
            'contentType' => 'text/html',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('HTML response', $result[200]['description']);
        $this->assertArrayHasKey('text/html', $result[200]['content']);
        $this->assertEquals('string', $result[200]['content']['text/html']['schema']['type']);
    }

    public function test_generates_custom_content_type_response(): void
    {
        $responseData = [
            'type' => ResponseType::CUSTOM->value,
            'contentType' => 'application/x-custom',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('Custom content type response', $result[200]['description']);
        $this->assertArrayHasKey('application/x-custom', $result[200]['content']);
        $this->assertEquals('string', $result[200]['content']['application/x-custom']['schema']['type']);
    }

    public function test_uses_default_content_type_when_not_specified(): void
    {
        $responseData = [
            'type' => ResponseType::BINARY_FILE->value,
            // No contentType specified
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        // Should use default 'application/octet-stream'
        $this->assertArrayHasKey('application/octet-stream', $result[200]['content']);
    }

    public function test_json_responses_are_not_affected(): void
    {
        $responseData = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertArrayHasKey('application/json', $result[200]['content']);
        $this->assertEquals('object', $result[200]['content']['application/json']['schema']['type']);
    }

    public function test_void_responses_are_not_affected(): void
    {
        $responseData = [
            'type' => 'void',
        ];

        $result = $this->generator->generate($responseData, 204);

        $this->assertArrayHasKey(204, $result);
        $this->assertArrayNotHasKey('content', $result[204]);
    }

    public function test_resource_responses_are_not_affected(): void
    {
        $responseData = [
            'type' => 'resource',
            'class' => 'UserResource',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertArrayHasKey('application/json', $result[200]['content']);
    }

    public function test_generates_xlsx_file_response(): void
    {
        $responseData = [
            'type' => ResponseType::BINARY_FILE->value,
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'fileName' => 'data.xlsx',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('File download: data.xlsx', $result[200]['description']);
        $this->assertArrayHasKey(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $result[200]['content']
        );
    }
}
