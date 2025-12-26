<?php

namespace LaravelSpectrum\Tests\Unit\Formatters;

use LaravelSpectrum\Formatters\RequestExampleFormatter;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
    public function it_generates_uri_string(): void
    {
        $result = $this->formatter->generateExample('string', 'uri');
        $this->assertStringContainsString('https://', $result);

        $result = $this->formatter->generateExample('string', 'website_url');
        $this->assertStringContainsString('https://', $result);
    }

    #[Test]
    public function it_generates_description_string(): void
    {
        $result = $this->formatter->generateExample('string', 'bio');
        $this->assertStringContainsString('bio', $result);

        $result = $this->formatter->generateExample('string', 'description');
        $this->assertStringContainsString('description', $result);
    }

    #[Test]
    public function it_generates_token_string(): void
    {
        $result = $this->formatter->generateExample('string', 'api_token');
        $this->assertIsString($result);
        $this->assertGreaterThanOrEqual(32, strlen($result));

        $result = $this->formatter->generateExample('string', 'secret_key');
        $this->assertIsString($result);
        $this->assertGreaterThanOrEqual(32, strlen($result));
    }

    #[Test]
    public function it_generates_date_string(): void
    {
        $result = $this->formatter->generateExample('string', 'birth_date');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
    }

    #[Test]
    public function it_generates_time_string(): void
    {
        $result = $this->formatter->generateExample('string', 'created_at');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result);

        $result = $this->formatter->generateExample('string', 'event_time');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result);
    }

    #[Test]
    public function it_generates_boolean_prefixes(): void
    {
        // is_ prefix
        $result = $this->formatter->generateExample('boolean', 'is_admin');
        $this->assertTrue($result);

        // has_ prefix
        $result = $this->formatter->generateExample('boolean', 'has_subscription');
        $this->assertTrue($result);

        // can_ prefix
        $result = $this->formatter->generateExample('boolean', 'can_edit');
        $this->assertTrue($result);
    }

    #[Test]
    public function it_generates_boolean_for_enabled_disabled(): void
    {
        // enabled/published (is_ prefix takes precedence)
        $result = $this->formatter->generateExample('boolean', 'is_enabled');
        $this->assertTrue($result);

        $result = $this->formatter->generateExample('boolean', 'is_published');
        $this->assertTrue($result);

        // For deleted/disabled/archived, use field names without is_ prefix
        // (is_ prefix takes precedence and returns true)
        $result = $this->formatter->generateExample('boolean', 'deleted');
        $this->assertFalse($result);

        $result = $this->formatter->generateExample('boolean', 'disabled');
        $this->assertFalse($result);

        $result = $this->formatter->generateExample('boolean', 'archived');
        $this->assertFalse($result);
    }

    #[Test]
    public function it_generates_integer_for_various_fields(): void
    {
        $result = $this->formatter->generateExample('integer', 'count');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);

        $result = $this->formatter->generateExample('integer', 'quantity');
        $this->assertIsInt($result);

        $result = $this->formatter->generateExample('integer', 'total_price');
        $this->assertIsInt($result);

        $result = $this->formatter->generateExample('integer', 'amount');
        $this->assertIsInt($result);

        $result = $this->formatter->generateExample('integer', 'birth_year');
        $this->assertIsInt($result);
        $this->assertEquals((int) date('Y'), $result);
    }

    #[Test]
    public function it_generates_number_for_various_fields(): void
    {
        $result = $this->formatter->generateExample('number', 'tax_rate');
        $this->assertIsFloat($result);

        $result = $this->formatter->generateExample('number', 'percentage');
        $this->assertIsFloat($result);

        $result = $this->formatter->generateExample('number', 'latitude');
        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(-90, $result);
        $this->assertLessThanOrEqual(90, $result);

        $result = $this->formatter->generateExample('number', 'longitude');
        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(-180, $result);
        $this->assertLessThanOrEqual(180, $result);
    }

    #[Test]
    public function it_generates_array_for_various_fields(): void
    {
        $result = $this->formatter->generateExample('array', 'categories');
        $this->assertIsArray($result);
        $this->assertContains('category1', $result);

        $result = $this->formatter->generateExample('array', 'roles');
        $this->assertIsArray($result);

        $result = $this->formatter->generateExample('array', 'permissions');
        $this->assertIsArray($result);
        $this->assertContains('read', $result);
    }

    #[Test]
    public function it_handles_unknown_type(): void
    {
        $result = $this->formatter->generateExample('custom_type', 'field');
        $this->assertEquals('example_field', $result);
    }

    #[Test]
    public function it_generates_ip_formats(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'ipv4_address' => ['type' => 'string', 'format' => 'ipv4'],
                'ipv6_address' => ['type' => 'string', 'format' => 'ipv6'],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertEquals('192.168.1.1', $result['ipv4_address']);
        $this->assertEquals('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $result['ipv6_address']);
    }

    #[Test]
    public function it_handles_schema_with_url_format(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'website' => ['type' => 'string', 'format' => 'url'],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);
        $this->assertStringContainsString('https://', $result['website']);
    }

    #[Test]
    public function it_handles_unknown_format(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'custom' => ['type' => 'string', 'format' => 'unknown-format'],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);
        $this->assertEquals('example_string', $result['custom']);
    }

    #[Test]
    public function it_handles_number_with_constraints(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'rate' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertIsFloat($result['rate']);
        $this->assertGreaterThanOrEqual(0, $result['rate']);
        $this->assertLessThanOrEqual(1, $result['rate']);
    }

    #[Test]
    public function it_handles_array_without_items(): void
    {
        $schema = [
            'type' => 'array',
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertIsArray($result);
        $this->assertContains('example', $result);
    }

    #[Test]
    public function it_handles_schema_without_type(): void
    {
        $schema = [];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_allof_with_non_array_result(): void
    {
        $schema = [
            'allOf' => [
                ['type' => 'string'],
                ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            ],
        ];

        $result = $this->formatter->generateFromSchema($schema);

        // The string result is skipped, only the object properties are merged
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
    }

    #[Test]
    public function it_handles_ref_schema(): void
    {
        $schema = [
            '$ref' => '#/components/schemas/SomeSchema',
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_handles_object_without_properties(): void
    {
        $schema = [
            'type' => 'object',
        ];

        $result = $this->formatter->generateFromSchema($schema);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
