<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\DTO\ResourceInfo;
use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\ExampleValueFactory;
use LaravelSpectrum\Tests\Fixtures\Resources\PostResource;
use LaravelSpectrum\Tests\Fixtures\Resources\UserResourceWithExample;
use LaravelSpectrum\Tests\TestCase;

class ExampleGeneratorTest extends TestCase
{
    private ExampleGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable Faker for existing tests to use static values
        config(['spectrum.example_generation.use_faker' => false]);

        $valueFactory = new ExampleValueFactory;
        $this->generator = new ExampleGenerator($valueFactory);
    }

    public function test_generates_example_from_resource_schema(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'is_active' => ['type' => 'boolean'],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertIsArray($example);
        $this->assertArrayHasKey('id', $example);
        $this->assertIsInt($example['id']);
        $this->assertEquals('user@example.com', $example['email']);
        $this->assertIsBool($example['is_active']);
        $this->assertEquals('2024-01-15T10:30:00Z', $example['created_at']);
    }

    public function test_uses_custom_example_when_available(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, UserResourceWithExample::class);

        // Should use custom example from HasExamples interface
        $this->assertEquals(123, $example['id']);
        $this->assertEquals('Test User', $example['name']);
        $this->assertEquals('test@example.com', $example['email']);
    }

    public function test_generates_paginated_collection_example(): void
    {
        $itemExample = ['id' => 1, 'name' => 'Item'];
        $result = $this->generator->generateCollectionExample($itemExample, true);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('links', $result);
        $this->assertArrayHasKey('meta', $result);

        // Check pagination structure
        $this->assertIsArray($result['data']);
        $this->assertCount(3, $result['data']); // Should generate 3 example items

        // Check links
        $this->assertArrayHasKey('first', $result['links']);
        $this->assertArrayHasKey('last', $result['links']);
        $this->assertArrayHasKey('prev', $result['links']);
        $this->assertArrayHasKey('next', $result['links']);

        // Check meta
        $this->assertArrayHasKey('current_page', $result['meta']);
        $this->assertArrayHasKey('per_page', $result['meta']);
        $this->assertArrayHasKey('total', $result['meta']);
        $this->assertEquals(1, $result['meta']['current_page']);
    }

    public function test_generates_simple_collection_example(): void
    {
        $itemExample = ['id' => 1, 'name' => 'Item'];
        $result = $this->generator->generateCollectionExample($itemExample, false);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('data', $result);
        $this->assertArrayNotHasKey('links', $result);
        $this->assertCount(3, $result);
        $this->assertEquals(['id' => 1, 'name' => 'Item'], $result[0]);
        $this->assertEquals(['id' => 2, 'name' => 'Item'], $result[1]);
        $this->assertEquals(['id' => 3, 'name' => 'Item'], $result[2]);
    }

    public function test_generates_error_example_for_validation_errors(): void
    {
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ];

        $result = $this->generator->generateErrorExample(422, $validationRules);

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals('The given data was invalid.', $result['message']);

        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertIsArray($result['errors']['name']);
        $this->assertIsArray($result['errors']['email']);
    }

    public function test_generates_error_example_for_not_found(): void
    {
        $result = $this->generator->generateErrorExample(404);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Not found', $result['message']);
    }

    public function test_generates_error_example_for_unauthorized(): void
    {
        $result = $this->generator->generateErrorExample(401);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Unauthenticated.', $result['message']);
    }

    public function test_handles_nested_resources(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                    ],
                ],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertArrayHasKey('user', $example);
        $this->assertIsArray($example['user']);
        $this->assertEquals(1, $example['user']['id']);
        $this->assertEquals('John Doe', $example['user']['name']);
        $this->assertEquals('user@example.com', $example['user']['email']);
    }

    public function test_handles_array_of_objects(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'tags' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertArrayHasKey('tags', $example);
        $this->assertIsArray($example['tags']);
        $this->assertCount(3, $example['tags']); // Should generate 3 example items
        $this->assertEquals(['id' => 1, 'name' => 'John Doe'], $example['tags'][0]);
    }

    public function test_handles_enum_values(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive', 'pending'],
                ],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertEquals('active', $example['status']); // Should use first enum value
    }

    public function test_handles_null_values(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'deleted_at' => [
                    'type' => 'string',
                    'format' => 'date-time',
                    'nullable' => true,
                ],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertArrayHasKey('deleted_at', $example);
        $this->assertNull($example['deleted_at']);
    }

    public function test_generates_from_transformer(): void
    {
        $transformerSchema = [
            'default' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                ],
            ],
            'includes' => [
                'user' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                    ],
                ],
                'comments' => [
                    'type' => 'array',
                    'items' => [
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'text' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $example = $this->generator->generateFromTransformer($transformerSchema);

        $this->assertArrayHasKey('id', $example);
        $this->assertArrayHasKey('title', $example);
        $this->assertArrayHasKey('content', $example);
        $this->assertArrayNotHasKey('user', $example); // Includes should not be in default example
        $this->assertArrayNotHasKey('comments', $example);
    }

    public function test_generates_error_example_for_forbidden(): void
    {
        $result = $this->generator->generateErrorExample(403);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Forbidden.', $result['message']);
    }

    public function test_generates_error_example_for_server_error(): void
    {
        $result = $this->generator->generateErrorExample(500);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Server Error', $result['message']);
    }

    public function test_handles_validation_error_with_array_rules(): void
    {
        $validationRules = [
            'name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'numeric', 'min:18'],
        ];

        $result = $this->generator->generateErrorExample(422, $validationRules);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('age', $result['errors']);
    }

    public function test_handles_validation_error_with_various_rules(): void
    {
        $validationRules = [
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8',
            'url' => 'required|url',
            'birthday' => 'required|date',
        ];

        $result = $this->generator->generateErrorExample(422, $validationRules);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertArrayHasKey('password', $result['errors']);
        $this->assertArrayHasKey('url', $result['errors']);
        $this->assertArrayHasKey('birthday', $result['errors']);

        // Check that error messages are limited to 2 per field
        $this->assertLessThanOrEqual(2, count($result['errors']['email']));
    }

    public function test_handles_array_of_primitive_types(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertArrayHasKey('tags', $example);
        $this->assertIsArray($example['tags']);
    }

    public function test_generates_example_without_properties(): void
    {
        $resourceInfo = ResourceInfo::fromArray([]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertIsArray($example);
        $this->assertEmpty($example);
    }

    public function test_generates_from_transformer_without_default(): void
    {
        $transformerSchema = [
            'includes' => [
                'user' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $example = $this->generator->generateFromTransformer($transformerSchema);

        $this->assertIsArray($example);
        $this->assertEmpty($example);
    }

    public function test_generates_integer_field_value(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'count' => ['type' => 'integer'],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertArrayHasKey('count', $example);
        $this->assertIsInt($example['count']);
    }

    public function test_generates_number_field_value(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'price' => ['type' => 'number'],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertArrayHasKey('price', $example);
        $this->assertIsNumeric($example['price']);
    }

    public function test_generates_boolean_field_value(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'active' => ['type' => 'boolean'],
            ],
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertArrayHasKey('active', $example);
        $this->assertIsBool($example['active']);
    }

    public function test_uses_custom_example_mapping(): void
    {
        // Custom generators require Faker to be enabled
        config(['spectrum.example_generation.use_faker' => true]);
        $valueFactory = new ExampleValueFactory;
        $generator = new ExampleGenerator($valueFactory);

        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'status' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
        ]);

        // Create a resource that implements HasCustomExamples
        $resource = new class implements \LaravelSpectrum\Contracts\HasCustomExamples
        {
            public static function getExampleMapping(): array
            {
                return [
                    'status' => fn ($faker) => 'custom_status_value',
                ];
            }
        };

        $example = $generator->generateFromResource($resourceInfo, get_class($resource));

        $this->assertEquals('custom_status_value', $example['status']);
    }

    public function test_generates_error_example_default(): void
    {
        $result = $this->generator->generateErrorExample(418);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Error occurred', $result['message']);
    }

    public function test_uses_custom_example_from_resource_info(): void
    {
        $customExample = ['id' => 999, 'custom_field' => 'custom_value'];
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
            'customExample' => $customExample,
        ]);

        $example = $this->generator->generateFromResource($resourceInfo, PostResource::class);

        $this->assertEquals($customExample, $example);
    }

    public function test_falls_back_to_schema_when_has_examples_throws(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ]);

        // Create a resource that throws an exception in getExample()
        $resource = new class implements \LaravelSpectrum\Contracts\HasExamples
        {
            public function getExample(): array
            {
                throw new \RuntimeException('Example generation failed');
            }

            public function getExamples(): array
            {
                return [];
            }
        };

        // Should not throw, should fall back to schema-based generation
        $example = $this->generator->generateFromResource($resourceInfo, get_class($resource));

        $this->assertIsArray($example);
        $this->assertArrayHasKey('id', $example);
        $this->assertArrayHasKey('name', $example);
    }
}
