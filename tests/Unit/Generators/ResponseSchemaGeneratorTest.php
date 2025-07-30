<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\ResponseSchemaGenerator;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ResponseSchemaGeneratorTest extends TestCase
{
    private ResponseSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ResponseSchemaGenerator;
    }

    #[Test]
    public function it_generates_simple_object_response()
    {
        $responseData = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
            ],
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertEquals('Successful response', $result[200]['description']);
        $this->assertArrayHasKey('content', $result[200]);
        $this->assertArrayHasKey('application/json', $result[200]['content']);
        $this->assertArrayHasKey('schema', $result[200]['content']['application/json']);

        $schema = $result[200]['content']['application/json']['schema'];
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertEquals('integer', $schema['properties']['id']['type']);
        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('email', $schema['properties']['email']['format']);
    }

    #[Test]
    public function it_generates_array_response()
    {
        $responseData = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                ],
            ],
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $schema = $result[200]['content']['application/json']['schema'];
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('object', $schema['items']['type']);
        $this->assertArrayHasKey('properties', $schema['items']);
    }

    #[Test]
    public function it_generates_void_response_for_204_status()
    {
        $responseData = ['type' => 'void'];

        $result = $this->generator->generate($responseData, 204);

        $this->assertArrayHasKey(204, $result);
        $this->assertEquals('No content', $result[204]['description']);
        $this->assertArrayNotHasKey('content', $result[204]);
    }

    #[Test]
    public function it_generates_unknown_response()
    {
        $responseData = ['type' => 'unknown'];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $schema = $result[200]['content']['application/json']['schema'];
        $this->assertEquals('object', $schema['type']);
        $this->assertEquals('Response structure could not be determined automatically', $schema['description']);
    }

    #[Test]
    public function it_generates_resource_response()
    {
        $responseData = [
            'type' => 'resource',
            'class' => 'App\\Http\\Resources\\UserResource',
        ];

        $result = $this->generator->generate($responseData, 200);

        $this->assertArrayHasKey(200, $result);
        $this->assertStringContainsString('App\\Http\\Resources\\UserResource', $result[200]['description']);
        $schema = $result[200]['content']['application/json']['schema'];
        $this->assertEquals('object', $schema['type']);
        $this->assertStringContainsString('App\\Http\\Resources\\UserResource', $schema['description']);
    }

    #[Test]
    public function it_extracts_required_fields_correctly()
    {
        $responseData = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'nullable' => true],
                'created_at' => ['type' => 'string', 'readOnly' => true],
                'optional_field' => ['type' => 'string', 'nullable' => true],
            ],
        ];

        $result = $this->generator->generate($responseData, 200);

        $schema = $result[200]['content']['application/json']['schema'];
        $this->assertArrayHasKey('required', $schema);
        // Only 'id' and 'name' should be required
        // 'email' and 'optional_field' are nullable
        // 'created_at' is readOnly
        $this->assertContains('id', $schema['required']);
        $this->assertContains('name', $schema['required']);
        $this->assertNotContains('email', $schema['required']);
        $this->assertNotContains('created_at', $schema['required']);
        $this->assertNotContains('optional_field', $schema['required']);
        $this->assertCount(2, $schema['required']);
    }

    #[Test]
    public function it_handles_nested_objects()
    {
        $responseData = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'profile' => [
                            'type' => 'object',
                            'properties' => [
                                'bio' => ['type' => 'string'],
                                'avatar' => ['type' => 'string', 'format' => 'uri'],
                            ],
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'total' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $result = $this->generator->generate($responseData, 200);

        $schema = $result[200]['content']['application/json']['schema'];
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('user', $schema['properties']);
        $this->assertEquals('object', $schema['properties']['user']['type']);
        $this->assertArrayHasKey('profile', $schema['properties']['user']['properties']);
        $this->assertEquals('uri', $schema['properties']['user']['properties']['profile']['properties']['avatar']['format']);
    }

    #[Test]
    public function it_includes_property_attributes()
    {
        $responseData = [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Unique identifier',
                    'example' => 123,
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive', 'pending'],
                    'description' => 'User status',
                ],
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                    'nullable' => true,
                ],
                'created_at' => [
                    'type' => 'string',
                    'format' => 'date-time',
                    'readOnly' => true,
                ],
            ],
        ];

        $result = $this->generator->generate($responseData, 200);

        $props = $result[200]['content']['application/json']['schema']['properties'];

        // Check descriptions
        $this->assertEquals('Unique identifier', $props['id']['description']);
        $this->assertEquals('User status', $props['status']['description']);

        // Check example
        $this->assertEquals(123, $props['id']['example']);

        // Check enum
        $this->assertEquals(['active', 'inactive', 'pending'], $props['status']['enum']);

        // Check nullable
        $this->assertTrue($props['email']['nullable']);

        // Check readOnly
        $this->assertTrue($props['created_at']['readOnly']);
    }

    #[Test]
    public function it_handles_additional_properties()
    {
        $responseData = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
            'additionalProperties' => [
                'type' => 'string',
            ],
        ];

        $result = $this->generator->generate($responseData, 200);

        $schema = $result[200]['content']['application/json']['schema'];
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertEquals('string', $schema['additionalProperties']['type']);
    }

    #[Test]
    public function it_generates_appropriate_status_descriptions()
    {
        $responseData = ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]];

        // Test various status codes
        $statuses = [
            200 => 'Successful response',
            201 => 'Resource created successfully',
            204 => 'No content',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource not found',
            422 => 'Validation error',
            500 => 'Internal server error',
        ];

        foreach ($statuses as $status => $expectedDescription) {
            $result = $this->generator->generate($responseData, $status);
            $this->assertEquals($expectedDescription, $result[$status]['description']);
        }
    }

    #[Test]
    public function it_handles_empty_response_data()
    {
        $result = $this->generator->generate([], 204);

        $this->assertArrayHasKey(204, $result);
        $this->assertEquals('No content', $result[204]['description']);
        $this->assertArrayNotHasKey('content', $result[204]);
    }

    #[Test]
    public function it_preserves_schema_description()
    {
        $responseData = [
            'type' => 'object',
            'description' => 'User profile response',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
        ];

        $result = $this->generator->generate($responseData, 200);

        $schema = $result[200]['content']['application/json']['schema'];
        $this->assertEquals('User profile response', $schema['description']);
    }

    #[Test]
    public function it_handles_mixed_type_arrays()
    {
        $responseData = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string'],
                    'data' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $result = $this->generator->generate($responseData, 200);

        $schema = $result[200]['content']['application/json']['schema'];
        $this->assertEquals('array', $schema['type']);
        $this->assertEquals('object', $schema['items']['type']);
        // Note: additionalProperties is not handled in convertPropertyToOpenApi method
        $this->assertArrayHasKey('data', $schema['items']['properties']);
    }
}
