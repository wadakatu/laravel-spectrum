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
}
