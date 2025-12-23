<?php

namespace LaravelSpectrum\Tests\Unit\Converters;

use LaravelSpectrum\Converters\OpenApi31Converter;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OpenApi31ConverterTest extends TestCase
{
    private OpenApi31Converter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new OpenApi31Converter;
    }

    #[Test]
    public function it_updates_openapi_version_to_3_1_0(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
        ];

        $result = $this->converter->convert($spec);

        $this->assertEquals('3.1.0', $result['openapi']);
    }

    #[Test]
    public function it_converts_nullable_string_to_type_array(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $nameSchema = $result['components']['schemas']['User']['properties']['name'];
        $this->assertEquals(['string', 'null'], $nameSchema['type']);
        $this->assertArrayNotHasKey('nullable', $nameSchema);
    }

    #[Test]
    public function it_converts_nullable_integer_to_type_array(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Item' => [
                        'type' => 'object',
                        'properties' => [
                            'count' => [
                                'type' => 'integer',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $countSchema = $result['components']['schemas']['Item']['properties']['count'];
        $this->assertEquals(['integer', 'null'], $countSchema['type']);
    }

    #[Test]
    public function it_does_not_modify_non_nullable_properties(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $idSchema = $result['components']['schemas']['User']['properties']['id'];
        $this->assertEquals('integer', $idSchema['type']);
    }

    #[Test]
    public function it_converts_nested_nullable_properties(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'profile' => [
                                'type' => 'object',
                                'properties' => [
                                    'bio' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $bioSchema = $result['components']['schemas']['User']['properties']['profile']['properties']['bio'];
        $this->assertEquals(['string', 'null'], $bioSchema['type']);
        $this->assertArrayNotHasKey('nullable', $bioSchema);
    }

    #[Test]
    public function it_converts_nullable_array_items(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'UserList' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'nullable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $itemsSchema = $result['components']['schemas']['UserList']['items'];
        $this->assertEquals(['string', 'null'], $itemsSchema['type']);
    }

    #[Test]
    public function it_converts_request_body_schemas(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'post' => [
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'nickname' => [
                                                'type' => 'string',
                                                'nullable' => true,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $nicknameSchema = $result['paths']['/users']['post']['requestBody']['content']['application/json']['schema']['properties']['nickname'];
        $this->assertEquals(['string', 'null'], $nicknameSchema['type']);
    }

    #[Test]
    public function it_converts_response_schemas(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users/{id}' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'deleted_at' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                    'nullable' => true,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $deletedAtSchema = $result['paths']['/users/{id}']['get']['responses']['200']['content']['application/json']['schema']['properties']['deleted_at'];
        $this->assertEquals(['string', 'null'], $deletedAtSchema['type']);
        $this->assertEquals('date-time', $deletedAtSchema['format']);
    }

    #[Test]
    public function it_adds_empty_webhooks_section(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
        ];

        $result = $this->converter->convert($spec);

        $this->assertArrayHasKey('webhooks', $result);
        $this->assertInstanceOf(\stdClass::class, $result['webhooks']);
    }

    #[Test]
    public function it_preserves_existing_webhooks_section(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'webhooks' => [
                'newUser' => [
                    'post' => [
                        'summary' => 'New user webhook',
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertIsArray($result['webhooks']);
        $this->assertArrayHasKey('newUser', $result['webhooks']);
    }

    #[Test]
    public function it_converts_allof_schemas(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Combined' => [
                        'allOf' => [
                            [
                                'type' => 'object',
                                'properties' => [
                                    'field' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $fieldSchema = $result['components']['schemas']['Combined']['allOf'][0]['properties']['field'];
        $this->assertEquals(['string', 'null'], $fieldSchema['type']);
    }

    #[Test]
    public function it_converts_parameter_schemas(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/search' => [
                    'get' => [
                        'parameters' => [
                            [
                                'name' => 'filter',
                                'in' => 'query',
                                'schema' => [
                                    'type' => 'string',
                                    'nullable' => true,
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $filterSchema = $result['paths']['/search']['get']['parameters'][0]['schema'];
        $this->assertEquals(['string', 'null'], $filterSchema['type']);
    }

    #[Test]
    public function it_handles_nullable_without_type(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Unknown' => [
                        'nullable' => true,
                        'description' => 'A nullable field without explicit type',
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $schema = $result['components']['schemas']['Unknown'];
        $this->assertArrayNotHasKey('nullable', $schema);
        $this->assertArrayNotHasKey('type', $schema);
        $this->assertEquals('A nullable field without explicit type', $schema['description']);
    }

    #[Test]
    public function it_preserves_other_schema_properties(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Product' => [
                        'type' => 'object',
                        'properties' => [
                            'price' => [
                                'type' => 'number',
                                'nullable' => true,
                                'minimum' => 0,
                                'maximum' => 1000000,
                                'description' => 'Product price',
                                'example' => 99.99,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $priceSchema = $result['components']['schemas']['Product']['properties']['price'];
        $this->assertEquals(['number', 'null'], $priceSchema['type']);
        $this->assertEquals(0, $priceSchema['minimum']);
        $this->assertEquals(1000000, $priceSchema['maximum']);
        $this->assertEquals('Product price', $priceSchema['description']);
        $this->assertEquals(99.99, $priceSchema['example']);
    }

    #[Test]
    public function it_handles_type_already_as_array(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Flexible' => [
                        'type' => ['string', 'integer'],
                        'nullable' => true,
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $schema = $result['components']['schemas']['Flexible'];
        $this->assertEquals(['string', 'integer', 'null'], $schema['type']);
        $this->assertArrayNotHasKey('nullable', $schema);
    }

    #[Test]
    public function it_does_not_duplicate_null_in_type_array(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'AlreadyNullable' => [
                        'type' => ['string', 'null'],
                        'nullable' => true,
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $schema = $result['components']['schemas']['AlreadyNullable'];
        $this->assertEquals(['string', 'null'], $schema['type']);
        $this->assertCount(2, $schema['type']);
    }

    #[Test]
    public function it_converts_anyof_schemas(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Mixed' => [
                        'anyOf' => [
                            [
                                'type' => 'object',
                                'properties' => [
                                    'value' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                    ],
                                ],
                            ],
                            [
                                'type' => 'integer',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $anyOf = $result['components']['schemas']['Mixed']['anyOf'];
        $this->assertEquals(['string', 'null'], $anyOf[0]['properties']['value']['type']);
        $this->assertEquals(['integer', 'null'], $anyOf[1]['type']);
    }

    #[Test]
    public function it_converts_oneof_schemas(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Either' => [
                        'oneOf' => [
                            [
                                'type' => 'string',
                                'nullable' => true,
                            ],
                            [
                                'type' => 'number',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $oneOf = $result['components']['schemas']['Either']['oneOf'];
        $this->assertEquals(['string', 'null'], $oneOf[0]['type']);
        $this->assertEquals(['number', 'null'], $oneOf[1]['type']);
    }

    #[Test]
    public function it_converts_additional_properties_schema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Dictionary' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'string',
                            'nullable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $additionalProps = $result['components']['schemas']['Dictionary']['additionalProperties'];
        $this->assertEquals(['string', 'null'], $additionalProps['type']);
    }

    #[Test]
    public function it_converts_component_request_bodies(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'requestBodies' => [
                    'UserInput' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'email' => [
                                            'type' => 'string',
                                            'nullable' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $emailSchema = $result['components']['requestBodies']['UserInput']['content']['application/json']['schema']['properties']['email'];
        $this->assertEquals(['string', 'null'], $emailSchema['type']);
    }

    #[Test]
    public function it_converts_component_responses(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'responses' => [
                    'UserResponse' => [
                        'description' => 'User data',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'avatar' => [
                                            'type' => 'string',
                                            'nullable' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $avatarSchema = $result['components']['responses']['UserResponse']['content']['application/json']['schema']['properties']['avatar'];
        $this->assertEquals(['string', 'null'], $avatarSchema['type']);
    }

    #[Test]
    public function it_converts_component_parameters(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'parameters' => [
                    'OptionalFilter' => [
                        'name' => 'filter',
                        'in' => 'query',
                        'schema' => [
                            'type' => 'string',
                            'nullable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $filterSchema = $result['components']['parameters']['OptionalFilter']['schema'];
        $this->assertEquals(['string', 'null'], $filterSchema['type']);
    }

    #[Test]
    public function it_handles_non_array_path_methods(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => 'not-an-array',
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertEquals('not-an-array', $result['paths']['/users']);
    }

    #[Test]
    public function it_handles_non_array_operations(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'get' => 'not-an-array',
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertEquals('not-an-array', $result['paths']['/users']['get']);
    }

    #[Test]
    public function it_handles_response_without_content(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'delete' => [
                        'responses' => [
                            '204' => [
                                'description' => 'No Content',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertArrayNotHasKey('content', $result['paths']['/users']['delete']['responses']['204']);
    }

    #[Test]
    public function it_removes_nullable_false(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Required' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                                'nullable' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $idSchema = $result['components']['schemas']['Required']['properties']['id'];
        $this->assertEquals('integer', $idSchema['type']);
        // nullable: false should be removed in OpenAPI 3.1.0 as the keyword doesn't exist
        $this->assertArrayNotHasKey('nullable', $idSchema);
    }

    #[Test]
    public function it_handles_non_array_allof_subschema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Combined' => [
                        'allOf' => [
                            'not-an-array',
                            [
                                'type' => 'string',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $allOf = $result['components']['schemas']['Combined']['allOf'];
        $this->assertEquals('not-an-array', $allOf[0]);
        $this->assertEquals(['string', 'null'], $allOf[1]['type']);
    }

    #[Test]
    public function it_handles_non_array_anyof_subschema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Mixed' => [
                        'anyOf' => [
                            'not-an-array',
                            [
                                'type' => 'integer',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $anyOf = $result['components']['schemas']['Mixed']['anyOf'];
        $this->assertEquals('not-an-array', $anyOf[0]);
        $this->assertEquals(['integer', 'null'], $anyOf[1]['type']);
    }

    #[Test]
    public function it_handles_non_array_oneof_subschema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Either' => [
                        'oneOf' => [
                            'not-an-array',
                            [
                                'type' => 'boolean',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $oneOf = $result['components']['schemas']['Either']['oneOf'];
        $this->assertEquals('not-an-array', $oneOf[0]);
        $this->assertEquals(['boolean', 'null'], $oneOf[1]['type']);
    }

    #[Test]
    public function it_handles_non_array_property_schema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Object' => [
                        'type' => 'object',
                        'properties' => [
                            'simple' => 'not-an-array',
                            'complex' => [
                                'type' => 'string',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $properties = $result['components']['schemas']['Object']['properties'];
        $this->assertEquals('not-an-array', $properties['simple']);
        $this->assertEquals(['string', 'null'], $properties['complex']['type']);
    }

    #[Test]
    public function it_handles_request_body_without_content(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'post' => [
                        'requestBody' => [
                            'description' => 'No content defined',
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertArrayNotHasKey('content', $result['paths']['/users']['post']['requestBody']);
    }

    #[Test]
    public function it_handles_content_without_schema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/upload' => [
                    'post' => [
                        'requestBody' => [
                            'content' => [
                                'multipart/form-data' => [
                                    'encoding' => [
                                        'file' => ['contentType' => 'application/octet-stream'],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertArrayNotHasKey('schema', $result['paths']['/upload']['post']['requestBody']['content']['multipart/form-data']);
    }

    #[Test]
    public function it_handles_parameter_without_schema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/items' => [
                    'get' => [
                        'parameters' => [
                            [
                                'name' => 'legacy',
                                'in' => 'query',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $param = $result['paths']['/items']['get']['parameters'][0];
        $this->assertArrayNotHasKey('schema', $param);
        $this->assertArrayHasKey('content', $param);
    }

    #[Test]
    public function it_handles_component_request_body_without_content(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'requestBodies' => [
                    'Empty' => [
                        'description' => 'Empty request body',
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertArrayNotHasKey('content', $result['components']['requestBodies']['Empty']);
    }

    #[Test]
    public function it_handles_component_response_without_content(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'responses' => [
                    'NoContent' => [
                        'description' => 'No content response',
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertArrayNotHasKey('content', $result['components']['responses']['NoContent']);
    }

    #[Test]
    public function it_handles_component_parameter_without_schema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'parameters' => [
                    'ContentParam' => [
                        'name' => 'data',
                        'in' => 'query',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'object'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $param = $result['components']['parameters']['ContentParam'];
        $this->assertArrayNotHasKey('schema', $param);
        $this->assertArrayHasKey('content', $param);
    }

    #[Test]
    public function it_handles_response_content_without_schema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/files' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'File download',
                                'content' => [
                                    'application/octet-stream' => [
                                        'example' => 'binary data',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $content = $result['paths']['/files']['get']['responses']['200']['content']['application/octet-stream'];
        $this->assertArrayNotHasKey('schema', $content);
        $this->assertEquals('binary data', $content['example']);
    }

    #[Test]
    public function it_handles_component_response_content_without_schema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'responses' => [
                    'BinaryResponse' => [
                        'description' => 'Binary file',
                        'content' => [
                            'application/pdf' => [
                                'example' => 'PDF content',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $content = $result['components']['responses']['BinaryResponse']['content']['application/pdf'];
        $this->assertArrayNotHasKey('schema', $content);
        $this->assertEquals('PDF content', $content['example']);
    }

    #[Test]
    public function it_handles_component_request_body_content_without_schema(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'requestBodies' => [
                    'RawInput' => [
                        'content' => [
                            'text/plain' => [
                                'example' => 'plain text',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $content = $result['components']['requestBodies']['RawInput']['content']['text/plain'];
        $this->assertArrayNotHasKey('schema', $content);
        $this->assertEquals('plain text', $content['example']);
    }

    #[Test]
    public function it_handles_non_array_response_content(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/legacy' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => 'not-an-array',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertEquals('not-an-array', $result['paths']['/legacy']['get']['responses']['200']['content']);
    }

    #[Test]
    public function it_handles_non_array_component_request_body_content(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'requestBodies' => [
                    'Invalid' => [
                        'content' => 'not-an-array',
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertEquals('not-an-array', $result['components']['requestBodies']['Invalid']['content']);
    }

    #[Test]
    public function it_handles_non_array_component_response_content(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'responses' => [
                    'Invalid' => [
                        'description' => 'Invalid',
                        'content' => 'not-an-array',
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $this->assertEquals('not-an-array', $result['components']['responses']['Invalid']['content']);
    }

    #[Test]
    public function it_deeply_converts_nested_structures(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'DeepNested' => [
                        'type' => 'object',
                        'properties' => [
                            'level1' => [
                                'type' => 'object',
                                'properties' => [
                                    'level2' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'level3' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'value' => [
                                                            'type' => 'string',
                                                            'nullable' => true,
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        $valueSchema = $result['components']['schemas']['DeepNested']['properties']['level1']['properties']['level2']['properties']['level3']['items']['properties']['value'];
        $this->assertEquals(['string', 'null'], $valueSchema['type']);
    }

    #[Test]
    public function it_handles_multiple_media_types(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/data' => [
                    'post' => [
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'field' => ['type' => 'string', 'nullable' => true],
                                        ],
                                    ],
                                ],
                                'application/xml' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'field' => ['type' => 'string', 'nullable' => true],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'result' => ['type' => 'string', 'nullable' => true],
                                            ],
                                        ],
                                    ],
                                    'text/plain' => [
                                        'schema' => [
                                            'type' => 'string',
                                            'nullable' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        // Request body - JSON
        $jsonReqSchema = $result['paths']['/data']['post']['requestBody']['content']['application/json']['schema']['properties']['field'];
        $this->assertEquals(['string', 'null'], $jsonReqSchema['type']);

        // Request body - XML
        $xmlReqSchema = $result['paths']['/data']['post']['requestBody']['content']['application/xml']['schema']['properties']['field'];
        $this->assertEquals(['string', 'null'], $xmlReqSchema['type']);

        // Response - JSON
        $jsonResSchema = $result['paths']['/data']['post']['responses']['200']['content']['application/json']['schema']['properties']['result'];
        $this->assertEquals(['string', 'null'], $jsonResSchema['type']);

        // Response - text/plain
        $textResSchema = $result['paths']['/data']['post']['responses']['200']['content']['text/plain']['schema'];
        $this->assertEquals(['string', 'null'], $textResSchema['type']);
    }

    #[Test]
    public function it_preserves_schema_refs_without_modification(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/User',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'post' => [
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/CreateUserRequest',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Created',
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'CreateUserRequest' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        // $ref should be preserved as-is
        $this->assertEquals(
            '#/components/schemas/User',
            $result['paths']['/users']['get']['responses']['200']['content']['application/json']['schema']['$ref']
        );
        $this->assertEquals(
            '#/components/schemas/CreateUserRequest',
            $result['paths']['/users']['post']['requestBody']['content']['application/json']['schema']['$ref']
        );

        // Referenced schema should still be converted
        $nameSchema = $result['components']['schemas']['User']['properties']['name'];
        $this->assertEquals(['string', 'null'], $nameSchema['type']);
        $this->assertArrayNotHasKey('nullable', $nameSchema);
    }

    #[Test]
    public function it_handles_allof_with_refs(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'BaseModel' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                        ],
                    ],
                    'ExtendedModel' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/BaseModel'],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'extra' => ['type' => 'string', 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        // $ref in allOf should be preserved
        $this->assertEquals(
            '#/components/schemas/BaseModel',
            $result['components']['schemas']['ExtendedModel']['allOf'][0]['$ref']
        );

        // Inline schema in allOf should be converted
        $extraSchema = $result['components']['schemas']['ExtendedModel']['allOf'][1]['properties']['extra'];
        $this->assertEquals(['string', 'null'], $extraSchema['type']);
        $this->assertArrayNotHasKey('nullable', $extraSchema);
    }

    #[Test]
    public function it_handles_array_items_with_ref(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'User list',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => [
                                                '$ref' => '#/components/schemas/User',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($spec);

        // $ref in items should be preserved
        $this->assertEquals(
            '#/components/schemas/User',
            $result['paths']['/users']['get']['responses']['200']['content']['application/json']['schema']['items']['$ref']
        );
    }
}
