<?php

namespace LaravelSpectrum\Tests\Unit\MockServer;

use LaravelSpectrum\Generators\DynamicExampleGenerator;
use PHPUnit\Framework\TestCase;

class DynamicExampleGeneratorTest extends TestCase
{
    private DynamicExampleGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new DynamicExampleGenerator;
    }

    public function test_generates_string_example(): void
    {
        $schema = ['type' => 'string'];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_generates_integer_example(): void
    {
        $schema = ['type' => 'integer'];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertIsInt($result);
    }

    public function test_generates_number_example(): void
    {
        $schema = ['type' => 'number'];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertIsNumeric($result);
    }

    public function test_generates_boolean_example(): void
    {
        $schema = ['type' => 'boolean'];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertIsBool($result);
    }

    public function test_generates_array_example(): void
    {
        $schema = [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        foreach ($result as $item) {
            $this->assertIsString($item);
        }
    }

    public function test_generates_object_example(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['name', 'age', 'active'],
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
                'active' => ['type' => 'boolean'],
            ],
        ];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('age', $result);
        $this->assertArrayHasKey('active', $result);
        $this->assertIsString($result['name']);
        $this->assertIsInt($result['age']);
        $this->assertIsBool($result['active']);
    }

    public function test_generates_example_with_format(): void
    {
        $schemas = [
            ['type' => 'string', 'format' => 'email'],
            ['type' => 'string', 'format' => 'uri'],
            ['type' => 'string', 'format' => 'uuid'],
            ['type' => 'string', 'format' => 'date'],
            ['type' => 'string', 'format' => 'date-time'],
        ];

        foreach ($schemas as $schema) {
            $result = $this->generator->generateFromSchema($schema);
            $this->assertIsString($result);

            switch ($schema['format']) {
                case 'email':
                    $this->assertMatchesRegularExpression('/^.+@.+\..+$/', $result);
                    break;
                case 'uri':
                    $this->assertMatchesRegularExpression('/^https?:\/\//', $result);
                    break;
                case 'uuid':
                    $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $result);
                    break;
                case 'date':
                    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
                    break;
                case 'date-time':
                    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result);
                    break;
            }
        }
    }

    public function test_generates_example_with_constraints(): void
    {
        $schema = [
            'type' => 'integer',
            'minimum' => 10,
            'maximum' => 20,
        ];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertGreaterThanOrEqual(10, $result);
        $this->assertLessThanOrEqual(20, $result);
    }

    public function test_generates_example_with_enum(): void
    {
        $schema = [
            'type' => 'string',
            'enum' => ['active', 'inactive', 'pending'],
        ];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertContains($result, ['active', 'inactive', 'pending']);
    }

    public function test_generates_realistic_data_when_requested(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'format' => 'email'],
                'first_name' => ['type' => 'string'],
                'last_name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
        ];

        $result = $this->generator->generateFromSchema($schema, [
            'use_realistic_data' => true,
            'include_all_properties' => true,
        ]);

        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayHasKey('age', $result);

        // Check if data looks realistic
        $this->assertMatchesRegularExpression('/^.+@.+\..+$/', $result['email']);
        $this->assertNotEquals('string', $result['first_name']);
        $this->assertNotEquals('string', $result['last_name']);
    }

    public function test_generates_nested_objects(): void
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

        $result = $this->generator->generateFromSchema($schema, [
            'include_all_properties' => true,
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('name', $result['user']);
        $this->assertArrayHasKey('profile', $result['user']);
        $this->assertArrayHasKey('bio', $result['user']['profile']);
        $this->assertArrayHasKey('age', $result['user']['profile']);
    }

    public function test_handles_required_fields(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['name', 'email'],
            'properties' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'optional' => ['type' => 'string'],
            ],
        ];

        $result = $this->generator->generateFromSchema($schema);

        // Required fields should always be present
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        // Optional field may or may not be present
    }

    public function test_generates_string_with_length(): void
    {
        $schema = [
            'type' => 'string',
            'minLength' => 5,
            'maxLength' => 10,
        ];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertGreaterThanOrEqual(5, strlen($result));
        $this->assertLessThanOrEqual(10, strlen($result));
    }

    public function test_uses_example_if_provided(): void
    {
        $schema = [
            'type' => 'string',
            'example' => 'test@example.com',
        ];

        $result = $this->generator->generateFromSchema($schema);

        $this->assertEquals('test@example.com', $result);
    }
}
