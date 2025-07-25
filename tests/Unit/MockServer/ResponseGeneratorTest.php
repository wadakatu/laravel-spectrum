<?php

namespace LaravelSpectrum\Tests\Unit\MockServer;

use LaravelSpectrum\Generators\DynamicExampleGenerator;
use LaravelSpectrum\MockServer\ResponseGenerator;
use PHPUnit\Framework\TestCase;

class ResponseGeneratorTest extends TestCase
{
    private ResponseGenerator $generator;

    private DynamicExampleGenerator $exampleGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exampleGenerator = $this->createMock(DynamicExampleGenerator::class);
        $this->generator = new ResponseGenerator($this->exampleGenerator);
    }

    public function test_generates_response_with_example(): void
    {
        $operation = [
            'responses' => [
                200 => [
                    'content' => [
                        'application/json' => [
                            'example' => [
                                'id' => 123,
                                'name' => 'Test User',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->generator->generate($operation, 200);

        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('body', $result);
        $this->assertEquals(['id' => 123, 'name' => 'Test User'], $result['body']);
    }

    public function test_generates_response_from_schema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ];

        $this->exampleGenerator->expects($this->once())
            ->method('generateFromSchema')
            ->with($schema, $this->anything())
            ->willReturn(['id' => 1, 'name' => 'Generated Name']);

        $operation = [
            'responses' => [
                200 => [
                    'content' => [
                        'application/json' => [
                            'schema' => $schema,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->generator->generate($operation, 200);

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(['id' => 1, 'name' => 'Generated Name'], $result['body']);
    }

    public function test_generates_response_with_scenario(): void
    {
        $operation = [
            'responses' => [
                200 => [
                    'content' => [
                        'application/json' => [
                            'examples' => [
                                'success' => ['value' => ['status' => 'success']],
                                'error' => ['value' => ['status' => 'error']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->generator->generate($operation, 200, 'error');

        $this->assertEquals(['status' => 'error'], $result['body']);
    }

    public function test_generates_default_response_when_not_defined(): void
    {
        $operation = ['responses' => []];

        $result = $this->generator->generate($operation, 404);

        $this->assertEquals(404, $result['status']);
        $this->assertArrayHasKey('error', $result['body']);
        $this->assertArrayHasKey('message', $result['body']);
    }

    public function test_generates_paginated_response(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => ['type' => 'object'],
                ],
                'links' => ['type' => 'object'],
                'meta' => ['type' => 'object'],
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'total' => ['type' => 'integer'],
            ],
        ];

        $this->exampleGenerator->expects($this->any())
            ->method('generateFromSchema')
            ->willReturn(['id' => 1, 'name' => 'Item']);

        $operation = [
            'responses' => [
                200 => [
                    'content' => [
                        'application/json' => [
                            'schema' => $schema,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->generator->generate($operation, 200, 'success', ['_path' => '/api/users']);

        $this->assertArrayHasKey('data', $result['body']);
        $this->assertIsArray($result['body']['data']);
        $this->assertArrayHasKey('meta', $result['body']);
        $this->assertArrayHasKey('links', $result['body']);
        $this->assertEquals(1, $result['body']['meta']['current_page']);
    }

    public function test_generates_response_headers(): void
    {
        $operation = [
            'responses' => [
                200 => [
                    'headers' => [
                        'X-RateLimit-Limit' => [
                            'schema' => ['type' => 'integer'],
                            'example' => '100',
                        ],
                        'X-RateLimit-Remaining' => [
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                    'content' => [
                        'application/json' => [
                            'example' => ['success' => true],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->generator->generate($operation, 200);

        $this->assertArrayHasKey('headers', $result);
        $this->assertEquals('100', $result['headers']['X-RateLimit-Limit']);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $result['headers']);
    }

    public function test_generates_rate_limit_headers(): void
    {
        $operation = [
            'x-rate-limit' => true,
            'responses' => [
                200 => [
                    'content' => [
                        'application/json' => [
                            'example' => ['success' => true],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->generator->generate($operation, 200);

        $this->assertArrayHasKey('X-RateLimit-Limit', $result['headers']);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $result['headers']);
        $this->assertArrayHasKey('X-RateLimit-Reset', $result['headers']);
    }

    public function test_processes_example_with_path_parameters(): void
    {
        $operation = [
            'responses' => [
                200 => [
                    'content' => [
                        'application/json' => [
                            'example' => [
                                'id' => 123,
                                'self' => '/api/users/{id}',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->generator->generate($operation, 200, 'success', ['id' => '456']);

        $this->assertEquals('/api/users/456', $result['body']['self']);
    }

    public function test_handles_no_content_response(): void
    {
        $operation = [
            'responses' => [
                204 => [
                    'description' => 'No Content',
                ],
            ],
        ];

        $result = $this->generator->generate($operation, 204);

        $this->assertEquals(204, $result['status']);
        $this->assertNull($result['body']);
    }

    public function test_generates_error_responses(): void
    {
        $statusCodes = [400, 401, 403, 404, 422, 500];

        foreach ($statusCodes as $statusCode) {
            $result = $this->generator->generate(['responses' => []], $statusCode);

            $this->assertEquals($statusCode, $result['status']);
            $this->assertNotNull($result['body']);

            if ($statusCode === 422) {
                $this->assertArrayHasKey('errors', $result['body']);
            } else {
                $this->assertArrayHasKey('error', $result['body']);
            }
        }
    }
}
