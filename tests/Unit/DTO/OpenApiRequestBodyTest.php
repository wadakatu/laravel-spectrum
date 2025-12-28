<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\OpenApiRequestBody;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiRequestBodyTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $requestBody = new OpenApiRequestBody(
            content: [
                'application/json' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
            required: true,
            description: 'User data',
        );

        $this->assertTrue($requestBody->required);
        $this->assertEquals('User data', $requestBody->description);
        $this->assertIsArray($requestBody->content);
    }

    #[Test]
    public function it_can_be_constructed_with_defaults(): void
    {
        $requestBody = new OpenApiRequestBody(
            content: ['application/json' => []],
        );

        $this->assertFalse($requestBody->required);
        $this->assertNull($requestBody->description);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $requestBody = new OpenApiRequestBody(
            content: [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/CreateUser'],
                ],
            ],
            required: true,
            description: 'User creation data',
        );

        $array = $requestBody->toArray();

        $this->assertEquals([
            'required' => true,
            'description' => 'User creation data',
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/CreateUser'],
                ],
            ],
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_without_optional_fields(): void
    {
        $requestBody = new OpenApiRequestBody(
            content: ['application/json' => ['schema' => ['type' => 'object']]],
        );

        $array = $requestBody->toArray();

        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayHasKey('required', $array);
        $this->assertFalse($array['required']);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'content' => [
                'application/json' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
            'required' => true,
            'description' => 'Request body',
        ];

        $requestBody = OpenApiRequestBody::fromArray($data);

        $this->assertTrue($requestBody->required);
        $this->assertEquals('Request body', $requestBody->description);
        $this->assertNotNull($requestBody->content);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $requestBody = OpenApiRequestBody::fromArray([
            'content' => ['application/json' => []],
        ]);

        $this->assertFalse($requestBody->required);
        $this->assertNull($requestBody->description);
    }

    #[Test]
    public function it_checks_if_is_json(): void
    {
        $jsonBody = new OpenApiRequestBody(
            content: ['application/json' => []],
        );
        $formBody = new OpenApiRequestBody(
            content: ['multipart/form-data' => []],
        );
        $bothBody = new OpenApiRequestBody(
            content: [
                'application/json' => [],
                'multipart/form-data' => [],
            ],
        );

        $this->assertTrue($jsonBody->isJson());
        $this->assertFalse($formBody->isJson());
        $this->assertTrue($bothBody->isJson());
    }

    #[Test]
    public function it_checks_if_is_multipart(): void
    {
        $multipartBody = new OpenApiRequestBody(
            content: ['multipart/form-data' => []],
        );
        $jsonBody = new OpenApiRequestBody(
            content: ['application/json' => []],
        );

        $this->assertTrue($multipartBody->isMultipart());
        $this->assertFalse($jsonBody->isMultipart());
    }

    #[Test]
    public function it_checks_if_is_form_urlencoded(): void
    {
        $formBody = new OpenApiRequestBody(
            content: ['application/x-www-form-urlencoded' => []],
        );
        $jsonBody = new OpenApiRequestBody(
            content: ['application/json' => []],
        );

        $this->assertTrue($formBody->isFormUrlEncoded());
        $this->assertFalse($jsonBody->isFormUrlEncoded());
    }

    #[Test]
    public function it_gets_content_types(): void
    {
        $requestBody = new OpenApiRequestBody(
            content: [
                'application/json' => [],
                'multipart/form-data' => [],
            ],
        );

        $this->assertEquals(['application/json', 'multipart/form-data'], $requestBody->getContentTypes());
    }

    #[Test]
    public function it_gets_schema_for_content_type(): void
    {
        $schema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];
        $requestBody = new OpenApiRequestBody(
            content: [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        );

        $this->assertEquals($schema, $requestBody->getSchemaFor('application/json'));
        $this->assertNull($requestBody->getSchemaFor('multipart/form-data'));
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new OpenApiRequestBody(
            content: [
                'application/json' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
            required: true,
            description: 'Test body',
        );

        $restored = OpenApiRequestBody::fromArray($original->toArray());

        $this->assertEquals($original->required, $restored->required);
        $this->assertEquals($original->description, $restored->description);
        $this->assertEquals($original->content, $restored->content);
    }
}
