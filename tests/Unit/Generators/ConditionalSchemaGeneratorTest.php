<?php

namespace Tests\Unit\Generators;

use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConditionalSchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SchemaGenerator;
    }

    #[Test]
    public function it_generates_oneof_schema_for_conditional_parameters()
    {
        $parameters = [
            [
                'name' => 'email',
                'type' => 'string',
                'required' => true,
                'description' => 'Email address',
                'example' => 'user@example.com',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ['type' => 'http_method', 'method' => 'POST'],
                        ],
                        'rules' => ['required', 'email', 'unique:users'],
                    ],
                    [
                        'conditions' => [
                            ['type' => 'else', 'negated_conditions' => []],
                        ],
                        'rules' => ['sometimes', 'email'],
                    ],
                ],
            ],
            [
                'name' => 'password',
                'type' => 'string',
                'required' => true,
                'description' => 'Password',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ['type' => 'http_method', 'method' => 'POST'],
                        ],
                        'rules' => ['required', 'min:8'],
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        // Should generate oneOf schema
        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertCount(2, $schema['oneOf']);

        // Find POST schema
        $postSchema = null;
        foreach ($schema['oneOf'] as $s) {
            if ($s['title'] === 'POST Request') {
                $postSchema = $s;
                break;
            }
        }

        $this->assertNotNull($postSchema);
        $this->assertEquals('object', $postSchema['type']);
        $this->assertArrayHasKey('properties', $postSchema);
        $this->assertArrayHasKey('email', $postSchema['properties']);
        $this->assertArrayHasKey('password', $postSchema['properties']);
        $this->assertContains('email', $postSchema['required']);
        $this->assertContains('password', $postSchema['required']);
    }

    #[Test]
    public function it_generates_regular_schema_when_no_conditions()
    {
        $parameters = [
            [
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'description' => 'Name',
            ],
            [
                'name' => 'email',
                'type' => 'string',
                'required' => true,
                'description' => 'Email',
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        // Should generate regular schema (no oneOf)
        $this->assertArrayNotHasKey('oneOf', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
    }

    #[Test]
    public function it_handles_non_http_method_conditions()
    {
        $parameters = [
            [
                'name' => 'admin_field',
                'type' => 'string',
                'required' => true,
                'description' => 'Admin only field',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ['type' => 'user_method', 'expression' => '$this->user()->isAdmin()'],
                        ],
                        'rules' => ['required', 'string'],
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        // Non-HTTP method conditions should be grouped as DEFAULT
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('admin_field', $schema['properties']);
    }

    #[Test]
    public function it_merges_duplicate_fields_correctly()
    {
        $parameters = [
            [
                'name' => 'status',
                'type' => 'string',
                'required' => true,
                'description' => 'Status field',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ['type' => 'http_method', 'method' => 'POST'],
                        ],
                        'rules' => ['required', 'in:draft,published'],
                    ],
                    [
                        'conditions' => [
                            ['type' => 'http_method', 'method' => 'POST'],
                            ['type' => 'user_method', 'expression' => '$this->user()->isAdmin()'],
                        ],
                        'rules' => ['required', 'in:draft,published,archived'],
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        // When all conditions have the same HTTP method, it generates a single schema
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('status', $schema['properties']);

        // Should only have one status field
        $statusCount = 0;
        foreach ($schema['properties'] as $name => $prop) {
            if ($name === 'status') {
                $statusCount++;
            }
        }
        $this->assertEquals(1, $statusCount, 'Should only have one status field');
    }
}
