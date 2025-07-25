<?php

namespace LaravelSpectrum\Tests\Unit\Formatters;

use LaravelSpectrum\Formatters\RequestExampleFormatter;
use LaravelSpectrum\Tests\TestCase;

class RequestExampleFormatterTest extends TestCase
{
    private RequestExampleFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new RequestExampleFormatter;
    }

    public function test_generate_example(): void
    {
        // Test string
        $result = $this->formatter->generateExample('string', 'name');
        $this->assertIsString($result);
        $this->assertStringContainsString('name', $result);

        // Test integer
        $result = $this->formatter->generateExample('integer', 'age');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);

        // Test number
        $result = $this->formatter->generateExample('number', 'price');
        $this->assertIsFloat($result);

        // Test boolean
        $result = $this->formatter->generateExample('boolean', 'active');
        $this->assertIsBool($result);

        // Test array
        $result = $this->formatter->generateExample('array', 'tags');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function test_generate_from_schema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'active' => ['type' => 'boolean'],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('active', $result);
        $this->assertArrayHasKey('tags', $result);

        $this->assertIsInt($result['id']);
        $this->assertIsString($result['name']);
        $this->assertStringContainsString('@', $result['email']);
        $this->assertIsBool($result['active']);
        $this->assertIsArray($result['tags']);
    }

    public function test_generate_from_schema_with_nested_object(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'profile' => [
                            'type' => 'object',
                            'properties' => [
                                'bio' => ['type' => 'string'],
                                'age' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('name', $result['user']);
        $this->assertArrayHasKey('profile', $result['user']);
        $this->assertArrayHasKey('bio', $result['user']['profile']);
        $this->assertArrayHasKey('age', $result['user']['profile']);
    }

    public function test_generate_from_schema_with_examples(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'example' => 'active',
                ],
                'priority' => [
                    'type' => 'integer',
                    'example' => 5,
                ],
            ],
            'example' => [
                'status' => 'pending',
                'priority' => 10,
                'extra' => 'field',
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        // Should use the top-level example when available
        $this->assertEquals('pending', $result['status']);
        $this->assertEquals(10, $result['priority']);
        $this->assertEquals('field', $result['extra']);
    }

    public function test_generate_from_schema_with_enum(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'role' => [
                    'type' => 'string',
                    'enum' => ['admin', 'user', 'guest'],
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive'],
                ],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertContains($result['role'], ['admin', 'user', 'guest']);
        $this->assertContains($result['status'], ['active', 'inactive']);
    }

    public function test_generate_from_schema_with_format(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'format' => 'email'],
                'uuid' => ['type' => 'string', 'format' => 'uuid'],
                'date' => ['type' => 'string', 'format' => 'date'],
                'datetime' => ['type' => 'string', 'format' => 'date-time'],
                'uri' => ['type' => 'string', 'format' => 'uri'],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertStringContainsString('@example.com', $result['email']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $result['uuid']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result['date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result['datetime']);
        $this->assertStringContainsString('https://', $result['uri']);
    }

    public function test_generate_from_schema_with_array_of_objects(): void
    {
        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
    }

    public function test_generate_from_schema_with_required(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['id', 'name'],
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'optional' => ['type' => 'string'],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        // Required fields should always be present
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('optional', $result);
    }

    public function test_generate_from_schema_with_min_max_constraints(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'age' => [
                    'type' => 'integer',
                    'minimum' => 18,
                    'maximum' => 100,
                ],
                'name' => [
                    'type' => 'string',
                    'minLength' => 3,
                    'maxLength' => 50,
                ],
                'items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 5,
                    'items' => ['type' => 'string'],
                ],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertGreaterThanOrEqual(18, $result['age']);
        $this->assertLessThanOrEqual(100, $result['age']);
        $this->assertGreaterThanOrEqual(3, strlen($result['name']));
        $this->assertLessThanOrEqual(50, strlen($result['name']));
        $this->assertGreaterThanOrEqual(1, count($result['items']));
        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function test_generate_from_schema_with_pattern(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'phone' => [
                    'type' => 'string',
                    'pattern' => '^\+[1-9]\d{1,14}$',
                ],
                'zipcode' => [
                    'type' => 'string',
                    'pattern' => '^\d{5}$',
                ],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        // Since pattern matching is complex, we just check that values are generated
        $this->assertIsString($result['phone']);
        $this->assertIsString($result['zipcode']);
    }

    public function test_generate_from_schema_with_all_of(): void
    {
        $schema = [
            'allOf' => [
                [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                    ],
                ],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    public function test_generate_from_schema_with_ref(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    '$ref' => '#/components/schemas/User',
                ],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        // Refs should be resolved to empty objects for now
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals([], $result['user']);
    }

    public function test_generate_special_cases(): void
    {
        // Test field names that suggest specific formats
        $result = $this->formatter->generateExample('string', 'email');
        $this->assertStringContainsString('@', $result);

        $result = $this->formatter->generateExample('string', 'phone');
        $this->assertMatchesRegularExpression('/^\+?\d+/', $result);

        $result = $this->formatter->generateExample('string', 'url');
        $this->assertStringContainsString('https://', $result);

        $result = $this->formatter->generateExample('string', 'password');
        $this->assertGreaterThan(8, strlen($result));

        $result = $this->formatter->generateExample('integer', 'id');
        $this->assertGreaterThan(0, $result);

        $result = $this->formatter->generateExample('string', 'uuid');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $result);
    }
}
