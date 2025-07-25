<?php

namespace LaravelSpectrum\Tests\Unit\MockServer;

use LaravelSpectrum\MockServer\ValidationSimulator;
use PHPUnit\Framework\TestCase;

class ValidationSimulatorTest extends TestCase
{
    private ValidationSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new ValidationSimulator;
    }

    public function test_validates_required_request_body(): void
    {
        $operation = [
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['name', 'email'],
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'email' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->simulator->validate($operation, null, [], []);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('_body', $result['errors']);
        $this->assertContains('The request body is required.', $result['errors']['_body']);
    }

    public function test_validates_required_fields(): void
    {
        $operation = [
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['name', 'email'],
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'email' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $requestBody = ['name' => 'John'];

        $result = $this->simulator->validate($operation, $requestBody, [], []);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertContains('The email field is required.', $result['errors']['email']);
    }

    public function test_validates_field_types(): void
    {
        $operation = [
            'requestBody' => [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'age' => ['type' => 'integer'],
                                'active' => ['type' => 'boolean'],
                                'tags' => ['type' => 'array'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $requestBody = [
            'age' => 'not a number',
            'active' => 'not a boolean',
            'tags' => 'not an array',
        ];

        $result = $this->simulator->validate($operation, $requestBody, [], []);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('age', $result['errors']);
        $this->assertArrayHasKey('active', $result['errors']);
        $this->assertArrayHasKey('tags', $result['errors']);
    }

    public function test_validates_string_constraints(): void
    {
        $operation = [
            'requestBody' => [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'username' => [
                                    'type' => 'string',
                                    'minLength' => 3,
                                    'maxLength' => 20,
                                ],
                                'email' => [
                                    'type' => 'string',
                                    'format' => 'email',
                                ],
                                'website' => [
                                    'type' => 'string',
                                    'format' => 'uri',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $requestBody = [
            'username' => 'ab',
            'email' => 'invalid-email',
            'website' => 'not-a-url',
        ];

        $result = $this->simulator->validate($operation, $requestBody, [], []);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('username', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertArrayHasKey('website', $result['errors']);
    }

    public function test_validates_number_constraints(): void
    {
        $operation = [
            'requestBody' => [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'price' => [
                                    'type' => 'number',
                                    'minimum' => 0,
                                    'maximum' => 1000,
                                ],
                                'quantity' => [
                                    'type' => 'integer',
                                    'minimum' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $requestBody = [
            'price' => -10,
            'quantity' => 0,
        ];

        $result = $this->simulator->validate($operation, $requestBody, [], []);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('price', $result['errors']);
        $this->assertArrayHasKey('quantity', $result['errors']);
    }

    public function test_validates_enum_constraint(): void
    {
        $operation = [
            'requestBody' => [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => [
                                    'type' => 'string',
                                    'enum' => ['pending', 'active', 'inactive'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $requestBody = ['status' => 'invalid'];

        $result = $this->simulator->validate($operation, $requestBody, [], []);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('status', $result['errors']);
        $this->assertStringContainsString('Valid options are: pending, active, inactive', $result['errors']['status'][0]);
    }

    public function test_validates_query_parameters(): void
    {
        $operation = [
            'parameters' => [
                [
                    'name' => 'page',
                    'in' => 'query',
                    'required' => true,
                    'schema' => ['type' => 'integer', 'minimum' => 1],
                ],
                [
                    'name' => 'limit',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ],
            ],
        ];

        $queryParams = ['limit' => '200'];

        $result = $this->simulator->validate($operation, null, $queryParams, []);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('page', $result['errors']);
        $this->assertArrayHasKey('limit', $result['errors']);
    }

    public function test_validates_path_parameters(): void
    {
        $operation = [
            'parameters' => [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'integer'],
                ],
            ],
        ];

        $pathParams = ['id' => 'not-a-number'];

        $result = $this->simulator->validate($operation, null, [], $pathParams);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('id', $result['errors']);
    }

    public function test_passes_validation(): void
    {
        $operation = [
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['name'],
                            'properties' => [
                                'name' => ['type' => 'string', 'minLength' => 3],
                                'age' => ['type' => 'integer', 'minimum' => 0],
                            ],
                        ],
                    ],
                ],
            ],
            'parameters' => [
                [
                    'name' => 'page',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'integer'],
                ],
            ],
        ];

        $requestBody = ['name' => 'John Doe', 'age' => 30];
        $queryParams = ['page' => '1'];

        $result = $this->simulator->validate($operation, $requestBody, $queryParams, []);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_handles_pattern_validation(): void
    {
        $operation = [
            'requestBody' => [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'phone' => [
                                    'type' => 'string',
                                    'pattern' => '^\+?[1-9]\d{1,14}$',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $requestBody = ['phone' => 'invalid-phone'];

        $result = $this->simulator->validate($operation, $requestBody, [], []);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('phone', $result['errors']);
        $this->assertStringContainsString('format is invalid', $result['errors']['phone'][0]);
    }
}
