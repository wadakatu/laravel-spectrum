<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\DTO\ConditionResult;
use LaravelSpectrum\DTO\ResourceInfo;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SchemaGenerator;
    }

    #[Test]
    public function it_generates_schema_from_fractal_transformer()
    {
        $fractalData = [
            'type' => 'fractal',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
                'name' => ['type' => 'string', 'example' => 'John Doe'],
                'email' => ['type' => 'string', 'example' => 'user@example.com'],
            ],
            'availableIncludes' => [],
            'defaultIncludes' => [],
        ];

        $schema = $this->generator->generateFromFractal($fractalData);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('data', $schema['properties']);
        $this->assertEquals('object', $schema['properties']['data']['type']);
        $this->assertArrayHasKey('properties', $schema['properties']['data']);
        $this->assertArrayHasKey('id', $schema['properties']['data']['properties']);
        $this->assertArrayHasKey('name', $schema['properties']['data']['properties']);
        $this->assertArrayHasKey('email', $schema['properties']['data']['properties']);
    }

    #[Test]
    public function it_generates_schema_with_available_includes()
    {
        $fractalData = [
            'type' => 'fractal',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
                'name' => ['type' => 'string', 'example' => 'John Doe'],
            ],
            'availableIncludes' => [
                'posts' => ['type' => 'array', 'collection' => true],
                'profile' => ['type' => 'object', 'collection' => false],
            ],
            'defaultIncludes' => ['profile'],
        ];

        $schema = $this->generator->generateFromFractal($fractalData);

        // Check includes are added as optional properties
        $this->assertArrayHasKey('posts', $schema['properties']['data']['properties']);
        $this->assertArrayHasKey('profile', $schema['properties']['data']['properties']);

        // Check types
        $this->assertEquals('array', $schema['properties']['data']['properties']['posts']['type']);
        $this->assertEquals('object', $schema['properties']['data']['properties']['profile']['type']);

        // Check descriptions mention they are includes
        $this->assertStringContainsString('Optional include', $schema['properties']['data']['properties']['posts']['description']);
        $this->assertStringContainsString('Default include', $schema['properties']['data']['properties']['profile']['description']);
    }

    #[Test]
    public function it_generates_collection_schema_for_fractal()
    {
        $fractalData = [
            'type' => 'fractal',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
                'name' => ['type' => 'string', 'example' => 'John Doe'],
            ],
            'availableIncludes' => [],
            'defaultIncludes' => [],
        ];

        $schema = $this->generator->generateFromFractal($fractalData, true);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('data', $schema['properties']);
        $this->assertEquals('array', $schema['properties']['data']['type']);
        $this->assertArrayHasKey('items', $schema['properties']['data']);
        $this->assertEquals('object', $schema['properties']['data']['items']['type']);
    }

    #[Test]
    public function it_adds_pagination_metadata_for_collections()
    {
        $fractalData = [
            'type' => 'fractal',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
            ],
            'availableIncludes' => [],
            'defaultIncludes' => [],
        ];

        $schema = $this->generator->generateFromFractal($fractalData, true, true);

        $this->assertArrayHasKey('meta', $schema['properties']);
        $this->assertArrayHasKey('pagination', $schema['properties']['meta']['properties']);

        $pagination = $schema['properties']['meta']['properties']['pagination'];
        $this->assertArrayHasKey('total', $pagination['properties']);
        $this->assertArrayHasKey('count', $pagination['properties']);
        $this->assertArrayHasKey('per_page', $pagination['properties']);
        $this->assertArrayHasKey('current_page', $pagination['properties']);
        $this->assertArrayHasKey('total_pages', $pagination['properties']);
    }

    #[Test]
    public function it_handles_nested_properties_in_fractal()
    {
        $fractalData = [
            'type' => 'fractal',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'primary' => ['type' => 'string', 'example' => 'primary data'],
                        'secondary' => ['type' => 'string', 'example' => 'secondary data'],
                    ],
                ],
                'flags' => [
                    'type' => 'object',
                    'properties' => [
                        'is_active' => ['type' => 'boolean', 'example' => true],
                        'is_featured' => ['type' => 'boolean', 'example' => false],
                    ],
                ],
            ],
            'availableIncludes' => [],
            'defaultIncludes' => [],
        ];

        $schema = $this->generator->generateFromFractal($fractalData);

        $dataProperties = $schema['properties']['data']['properties'];

        // Check nested object
        $this->assertArrayHasKey('data', $dataProperties);
        $this->assertEquals('object', $dataProperties['data']['type']);
        $this->assertArrayHasKey('properties', $dataProperties['data']);
        $this->assertArrayHasKey('primary', $dataProperties['data']['properties']);

        // Check flags object
        $this->assertArrayHasKey('flags', $dataProperties);
        $this->assertEquals('object', $dataProperties['flags']['type']);
        $this->assertArrayHasKey('is_active', $dataProperties['flags']['properties']);
    }

    #[Test]
    public function it_generates_schema_from_resource_without_example_keys()
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                'created_at' => ['type' => 'string'],
            ],
        ]);

        $schema = $this->generator->generateFromResource($resourceInfo);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);

        // Check that properties exist
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
        $this->assertArrayHasKey('created_at', $schema['properties']);

        // Check types
        $this->assertEquals('integer', $schema['properties']['id']['type']);
        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('string', $schema['properties']['email']['type']);
        $this->assertEquals('string', $schema['properties']['created_at']['type']);

        // Check example key existence
        $this->assertArrayNotHasKey('example', $schema['properties']['id']);
        $this->assertArrayNotHasKey('example', $schema['properties']['name']);
        $this->assertArrayHasKey('example', $schema['properties']['email']);
        $this->assertEquals('user@example.com', $schema['properties']['email']['example']);
        $this->assertArrayNotHasKey('example', $schema['properties']['created_at']);
    }

    #[Test]
    public function it_generates_schema_from_parameters(): void
    {
        $parameters = [
            [
                'name' => 'email',
                'type' => 'string',
                'required' => true,
                'description' => 'User email address',
            ],
            [
                'name' => 'age',
                'type' => 'integer',
                'required' => false,
                'description' => 'User age',
            ],
            [
                'name' => 'is_active',
                'type' => 'boolean',
                'required' => true,
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        // Check properties
        $this->assertArrayHasKey('email', $schema['properties']);
        $this->assertArrayHasKey('age', $schema['properties']);
        $this->assertArrayHasKey('is_active', $schema['properties']);

        // Check types
        $this->assertEquals('string', $schema['properties']['email']['type']);
        $this->assertEquals('integer', $schema['properties']['age']['type']);
        $this->assertEquals('boolean', $schema['properties']['is_active']['type']);

        // Check required fields
        $this->assertContains('email', $schema['required']);
        $this->assertContains('is_active', $schema['required']);
        $this->assertNotContains('age', $schema['required']);
    }

    #[Test]
    public function it_generates_schema_from_parameters_without_required_fields(): void
    {
        $parameters = [
            [
                'name' => 'optional_field',
                'type' => 'string',
                'required' => false,
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayNotHasKey('required', $schema);
    }

    #[Test]
    public function it_skips_parameters_without_name(): void
    {
        $parameters = [
            [
                'type' => 'string',
                'required' => true,
            ],
            [
                'name' => 'valid_field',
                'type' => 'string',
                'required' => true,
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertCount(1, $schema['properties']);
        $this->assertArrayHasKey('valid_field', $schema['properties']);
    }

    #[Test]
    public function it_generates_multipart_schema_for_file_uploads(): void
    {
        $parameters = [
            [
                'name' => 'avatar',
                'type' => 'file',
                'required' => true,
                'description' => 'Profile picture',
            ],
            [
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'description' => 'User name',
            ],
        ];

        $result = $this->generator->generateFromParameters($parameters);

        // File uploads return multipart/form-data content structure
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('multipart/form-data', $result['content']);
        $this->assertArrayHasKey('schema', $result['content']['multipart/form-data']);

        $schema = $result['content']['multipart/form-data']['schema'];
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);

        // Check that both file and normal fields are present
        $this->assertArrayHasKey('avatar', $schema['properties']);
        $this->assertArrayHasKey('name', $schema['properties']);

        // Check file field has correct format
        $this->assertEquals('string', $schema['properties']['avatar']['type']);
        $this->assertEquals('binary', $schema['properties']['avatar']['format']);
    }

    #[Test]
    public function it_generates_schema_from_resource_preserves_type(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'secret' => [
                    'type' => 'string',
                ],
            ],
        ]);

        $schema = $this->generator->generateFromResource($resourceInfo);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('secret', $schema['properties']);
        $this->assertEquals('integer', $schema['properties']['id']['type']);
        $this->assertEquals('string', $schema['properties']['secret']['type']);
    }

    #[Test]
    public function it_generates_schema_from_resource_preserves_nested_type(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'address' => [
                    'type' => 'object',
                ],
            ],
        ]);

        $schema = $this->generator->generateFromResource($resourceInfo);

        $this->assertEquals('object', $schema['properties']['address']['type']);
    }

    #[Test]
    public function it_generates_schema_from_resource_preserves_array_type(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'tags' => [
                    'type' => 'array',
                ],
            ],
        ]);

        $schema = $this->generator->generateFromResource($resourceInfo);

        $this->assertEquals('array', $schema['properties']['tags']['type']);
    }

    #[Test]
    public function it_generates_schema_from_empty_resource(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [],
        ]);

        $schema = $this->generator->generateFromResource($resourceInfo);

        $this->assertEquals('object', $schema['type']);
        $this->assertEmpty($schema['properties']);
    }

    #[Test]
    public function it_generates_schema_from_resource_with_example(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'example' => 'user@example.com',
                ],
            ],
        ]);

        $schema = $this->generator->generateFromResource($resourceInfo);

        $this->assertArrayHasKey('example', $schema['properties']['email']);
        $this->assertEquals('user@example.com', $schema['properties']['email']['example']);
    }

    #[Test]
    public function it_handles_parameters_with_format(): void
    {
        $parameters = [
            [
                'name' => 'email',
                'type' => 'string',
                'required' => true,
                'format' => 'email',
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertEquals('email', $schema['properties']['email']['format']);
    }

    #[Test]
    public function it_handles_parameters_with_enum(): void
    {
        $parameters = [
            [
                'name' => 'status',
                'type' => 'string',
                'required' => true,
                'enum' => ['active', 'inactive', 'pending'],
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertArrayHasKey('enum', $schema['properties']['status']);
        $this->assertEquals(['active', 'inactive', 'pending'], $schema['properties']['status']['enum']);
    }

    #[Test]
    public function it_handles_parameters_with_description(): void
    {
        $parameters = [
            [
                'name' => 'website',
                'type' => 'string',
                'required' => false,
                'description' => 'User website URL',
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertEquals('User website URL', $schema['properties']['website']['description']);
    }

    #[Test]
    public function it_handles_parameters_with_min_max_constraints(): void
    {
        $parameters = [
            [
                'name' => 'age',
                'type' => 'integer',
                'required' => true,
                'minimum' => 0,
                'maximum' => 150,
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertEquals(0, $schema['properties']['age']['minimum']);
        $this->assertEquals(150, $schema['properties']['age']['maximum']);
    }

    #[Test]
    public function it_handles_parameters_with_min_max_length(): void
    {
        $parameters = [
            [
                'name' => 'description',
                'type' => 'string',
                'required' => true,
                'minLength' => 10,
                'maxLength' => 1000,
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertEquals(10, $schema['properties']['description']['minLength']);
        $this->assertEquals(1000, $schema['properties']['description']['maxLength']);
    }

    #[Test]
    public function it_handles_parameters_with_example(): void
    {
        $parameters = [
            [
                'name' => 'email',
                'type' => 'string',
                'required' => true,
                'example' => 'test@example.com',
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertEquals('test@example.com', $schema['properties']['email']['example']);
    }

    #[Test]
    public function it_handles_parameters_with_nullable(): void
    {
        $parameters = [
            [
                'name' => 'optional_field',
                'type' => 'string',
                'required' => false,
                'nullable' => true,
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertTrue($schema['properties']['optional_field']['nullable']);
    }

    #[Test]
    public function it_generates_schema_from_conditional_parameters_with_single_condition(): void
    {
        $parameters = [
            [
                'name' => 'email',
                'type' => 'string',
                'required' => true,
                'description' => 'User email',
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        // Single condition should generate regular schema
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('email', $schema['properties']);
    }

    #[Test]
    public function it_generates_oneof_schema_from_conditional_parameters_with_multiple_methods(): void
    {
        $parameters = [
            [
                'name' => 'email',
                'type' => 'string',
                'required' => true,
                'description' => 'User email',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                        ],
                        'rules' => ['required', 'email'],
                    ],
                    [
                        'conditions' => [
                            ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")'),
                        ],
                        'rules' => ['sometimes', 'email'],
                    ],
                ],
            ],
            [
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'description' => 'User name',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                        ],
                        'rules' => ['required', 'string'],
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        // Multiple HTTP methods should generate oneOf schema
        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertCount(2, $schema['oneOf']);

        // Each schema should have a title
        $this->assertArrayHasKey('title', $schema['oneOf'][0]);
        $this->assertArrayHasKey('title', $schema['oneOf'][1]);
    }

    #[Test]
    public function it_generates_conditional_schema_with_default_method(): void
    {
        $parameters = [
            [
                'name' => 'field',
                'type' => 'string',
                'required' => true,
                'description' => 'A test field',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ConditionResult::custom('other_condition'),
                        ],
                        'rules' => ['required', 'string'],
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        // Without http_method condition, should fall back to DEFAULT
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
    }

    #[Test]
    public function it_handles_array_field_notation_with_asterisk(): void
    {
        $parameters = [
            [
                'name' => 'items.*',
                'type' => 'string',
                'required' => true,
                'description' => 'Array items',
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        // Array notation is kept as-is in property names
        $this->assertArrayHasKey('items.*', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['items.*']['type']);
    }

    #[Test]
    public function it_handles_array_field_with_bracket_notation(): void
    {
        $parameters = [
            [
                'name' => 'tags[]',
                'type' => 'string',
                'required' => false,
                'description' => 'Tag list',
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        // Bracket notation is kept as-is
        $this->assertArrayHasKey('tags[]', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['tags[]']['type']);
    }

    #[Test]
    public function it_handles_nested_array_field(): void
    {
        $parameters = [
            [
                'name' => 'users.*.email',
                'type' => 'string',
                'required' => true,
                'description' => 'User emails in array',
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        // Nested array notation is kept as-is
        $this->assertArrayHasKey('users.*.email', $schema['properties']);
    }

    #[Test]
    public function it_generates_schema_with_validation_constraints(): void
    {
        $parameters = [
            [
                'name' => 'username',
                'type' => 'string',
                'required' => true,
                'validation' => ['required', 'string', 'min:3', 'max:50'],
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertArrayHasKey('username', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['username']['type']);
    }

    #[Test]
    public function it_handles_empty_parameters_array(): void
    {
        $schema = $this->generator->generateFromParameters([]);

        $this->assertEquals('object', $schema['type']);
        $this->assertEmpty($schema['properties']);
        $this->assertArrayNotHasKey('required', $schema);
    }

    #[Test]
    public function it_generates_fractal_schema_without_pagination(): void
    {
        $fractalData = [
            'type' => 'fractal',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
            ],
            'availableIncludes' => [],
            'defaultIncludes' => [],
        ];

        $schema = $this->generator->generateFromFractal($fractalData, true, false);

        // Collection without pagination should not have meta
        $this->assertArrayNotHasKey('meta', $schema['properties']);
    }

    #[Test]
    public function it_handles_fractal_with_complex_includes(): void
    {
        $fractalData = [
            'type' => 'fractal',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
            ],
            'availableIncludes' => [
                'comments' => [
                    'type' => 'array',
                    'collection' => true,
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'body' => ['type' => 'string'],
                    ],
                ],
            ],
            'defaultIncludes' => [],
        ];

        $schema = $this->generator->generateFromFractal($fractalData);

        $this->assertArrayHasKey('comments', $schema['properties']['data']['properties']);
        $this->assertEquals('array', $schema['properties']['data']['properties']['comments']['type']);
    }

    #[Test]
    public function it_handles_resource_with_nested_objects(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'user' => [
                    'type' => 'object',
                ],
            ],
        ]);

        $schema = $this->generator->generateFromResource($resourceInfo);

        // generateFromResource only extracts type and example
        $this->assertEquals('object', $schema['properties']['user']['type']);
    }

    #[Test]
    public function it_handles_resource_with_array_type(): void
    {
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'items' => [
                    'type' => 'array',
                ],
            ],
        ]);

        $schema = $this->generator->generateFromResource($resourceInfo);

        // generateFromResource only extracts type
        $this->assertEquals('array', $schema['properties']['items']['type']);
    }

    #[Test]
    public function it_handles_parameters_with_pattern(): void
    {
        $parameters = [
            [
                'name' => 'phone',
                'type' => 'string',
                'required' => true,
                'pattern' => '^\\d{3}-\\d{4}-\\d{4}$',
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertArrayHasKey('pattern', $schema['properties']['phone']);
        $this->assertEquals('^\\d{3}-\\d{4}-\\d{4}$', $schema['properties']['phone']['pattern']);
    }

    #[Test]
    public function it_handles_parameters_with_default_value(): void
    {
        $parameters = [
            [
                'name' => 'page',
                'type' => 'integer',
                'required' => false,
                'default' => 1,
            ],
        ];

        $schema = $this->generator->generateFromParameters($parameters);

        $this->assertArrayHasKey('default', $schema['properties']['page']);
        $this->assertEquals(1, $schema['properties']['page']['default']);
    }

    #[Test]
    public function it_handles_multiple_file_uploads(): void
    {
        $parameters = [
            [
                'name' => 'documents',
                'type' => 'file',
                'required' => true,
                'description' => 'Multiple documents',
            ],
            [
                'name' => 'images',
                'type' => 'file',
                'required' => false,
                'description' => 'Multiple images',
            ],
        ];

        $result = $this->generator->generateFromParameters($parameters);

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('multipart/form-data', $result['content']);

        $schema = $result['content']['multipart/form-data']['schema'];
        $this->assertArrayHasKey('documents', $schema['properties']);
        $this->assertArrayHasKey('images', $schema['properties']);
    }

    #[Test]
    public function it_handles_conditional_parameters_without_conditions(): void
    {
        $parameters = [
            [
                'name' => 'field',
                'type' => 'string',
                'required' => true,
                'description' => 'A test field',
                'conditional_rules' => [],
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        // Empty conditional rules should generate regular schema
        $this->assertEquals('object', $schema['type']);
    }

    #[Test]
    public function it_generates_conditional_schema_with_single_rule_set(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                    ],
                    'rules' => [
                        'name' => 'required|string',
                        'email' => 'required|email',
                    ],
                ],
            ],
        ];

        $parameters = [
            ['name' => 'name', 'type' => 'string', 'required' => true],
            ['name' => 'email', 'type' => 'string', 'required' => true],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, $parameters);

        // Single rule set should generate regular schema
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayNotHasKey('oneOf', $schema);
    }

    #[Test]
    public function it_generates_conditional_schema_with_multiple_rule_sets(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                    ],
                    'rules' => [
                        'name' => 'required|string|min:3',
                        'email' => 'required|email',
                    ],
                ],
                [
                    'conditions' => [
                        ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")'),
                    ],
                    'rules' => [
                        'name' => 'string|max:100',
                        'email' => 'email',
                    ],
                ],
            ],
        ];

        $parameters = [];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, $parameters);

        // Multiple rule sets should generate oneOf schema
        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertCount(2, $schema['oneOf']);
        $this->assertArrayHasKey('discriminator', $schema);
        $this->assertEquals('_condition', $schema['discriminator']['propertyName']);
    }

    #[Test]
    public function it_generates_condition_description_for_http_method(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                    ],
                    'rules' => [
                        'name' => 'required',
                    ],
                ],
                [
                    'conditions' => [
                        ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")'),
                    ],
                    'rules' => [
                        'name' => 'string',
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertStringContainsString('HTTP method is POST', $schema['oneOf'][0]['description']);
        $this->assertStringContainsString('HTTP method is PUT', $schema['oneOf'][1]['description']);
    }

    #[Test]
    public function it_generates_condition_description_for_user_check(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::userCheck('isAdmin', '$this->isAdmin()'),
                    ],
                    'rules' => [
                        'level' => 'required|integer',
                    ],
                ],
                [
                    'conditions' => [
                        ConditionResult::userCheck('isGuest', '$this->isGuest()'),
                    ],
                    'rules' => [
                        'level' => 'integer',
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertStringContainsString('user isAdmin()', $schema['oneOf'][0]['description']);
        $this->assertStringContainsString('user isGuest()', $schema['oneOf'][1]['description']);
    }

    #[Test]
    public function it_generates_condition_description_for_request_field(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::requestField('has', 'type', '$this->has("type")'),
                    ],
                    'rules' => [
                        'data' => 'required',
                    ],
                ],
                [
                    'conditions' => [
                        ConditionResult::requestField('filled', 'value', '$this->filled("value")'),
                    ],
                    'rules' => [
                        'data' => 'string',
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertStringContainsString("request has 'type'", $schema['oneOf'][0]['description']);
        $this->assertStringContainsString("request filled 'value'", $schema['oneOf'][1]['description']);
    }

    #[Test]
    public function it_generates_condition_description_for_else_type(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                    ],
                    'rules' => [
                        'name' => 'required',
                    ],
                ],
                [
                    'conditions' => [
                        ConditionResult::elseBranch(),
                    ],
                    'rules' => [
                        'name' => 'string',
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertStringContainsString('Otherwise', $schema['oneOf'][1]['description']);
    }

    #[Test]
    public function it_generates_condition_description_for_default_expression(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::custom('count > 10'),
                    ],
                    'rules' => [
                        'items' => 'required|array',
                    ],
                ],
                [
                    'conditions' => [
                        ConditionResult::custom('count <= 10'),
                    ],
                    'rules' => [
                        'items' => 'array',
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertStringContainsString('count > 10', $schema['oneOf'][0]['description']);
        $this->assertStringContainsString('count <= 10', $schema['oneOf'][1]['description']);
    }

    #[Test]
    public function it_generates_condition_description_for_empty_conditions(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [],
                    'rules' => [
                        'name' => 'required',
                    ],
                ],
                [
                    'conditions' => [
                        ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")'),
                    ],
                    'rules' => [
                        'name' => 'string',
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertEquals('Default validation rules', $schema['oneOf'][0]['description']);
    }

    #[Test]
    public function it_applies_rule_constraints_min_for_string(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['name' => 'string|min:3'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['name' => 'string|min:5'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('minLength', $schema['oneOf'][0]['properties']['name']);
        $this->assertEquals(3, $schema['oneOf'][0]['properties']['name']['minLength']);
    }

    #[Test]
    public function it_applies_rule_constraints_max_for_string(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['name' => 'string|max:50'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['name' => 'string|max:100'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('maxLength', $schema['oneOf'][0]['properties']['name']);
        $this->assertEquals(50, $schema['oneOf'][0]['properties']['name']['maxLength']);
    }

    #[Test]
    public function it_applies_rule_constraints_min_max_for_numeric(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['age' => 'integer|min:18|max:120'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['age' => 'integer|min:0'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('minimum', $schema['oneOf'][0]['properties']['age']);
        $this->assertEquals(18, $schema['oneOf'][0]['properties']['age']['minimum']);
        $this->assertArrayHasKey('maximum', $schema['oneOf'][0]['properties']['age']);
        $this->assertEquals(120, $schema['oneOf'][0]['properties']['age']['maximum']);
    }

    #[Test]
    public function it_applies_rule_constraints_email_format(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['contact' => 'email'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['contact' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('format', $schema['oneOf'][0]['properties']['contact']);
        $this->assertEquals('email', $schema['oneOf'][0]['properties']['contact']['format']);
    }

    #[Test]
    public function it_applies_rule_constraints_url_format(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['website' => 'url'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['website' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('format', $schema['oneOf'][0]['properties']['website']);
        $this->assertEquals('uri', $schema['oneOf'][0]['properties']['website']['format']);
    }

    #[Test]
    public function it_applies_rule_constraints_date_format(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['birth_date' => 'date'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['birth_date' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('format', $schema['oneOf'][0]['properties']['birth_date']);
        $this->assertEquals('date', $schema['oneOf'][0]['properties']['birth_date']['format']);
    }

    #[Test]
    public function it_applies_rule_constraints_datetime_format(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['created_at' => 'datetime'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['created_at' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('format', $schema['oneOf'][0]['properties']['created_at']);
        $this->assertEquals('date-time', $schema['oneOf'][0]['properties']['created_at']['format']);
    }

    #[Test]
    public function it_applies_rule_constraints_in_enum(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['status' => 'in:active,inactive,pending'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['status' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('enum', $schema['oneOf'][0]['properties']['status']);
        $this->assertEquals(['active', 'inactive', 'pending'], $schema['oneOf'][0]['properties']['status']['enum']);
    }

    #[Test]
    public function it_applies_rule_constraints_regex_pattern(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['phone' => 'regex:^\\d{3}-\\d{4}$'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['phone' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('pattern', $schema['oneOf'][0]['properties']['phone']);
        $this->assertEquals('^\\d{3}-\\d{4}$', $schema['oneOf'][0]['properties']['phone']['pattern']);
    }

    #[Test]
    public function it_generates_discriminator_mapping(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['name' => 'required'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['name' => 'string'],
                ],
                [
                    'conditions' => [ConditionResult::elseBranch()],
                    'rules' => ['name' => 'nullable'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('discriminator', $schema);
        $this->assertArrayHasKey('mapping', $schema['discriminator']);
        $this->assertArrayHasKey('post', $schema['discriminator']['mapping']);
        $this->assertArrayHasKey('put', $schema['discriminator']['mapping']);
        $this->assertArrayHasKey('else', $schema['discriminator']['mapping']);
    }

    #[Test]
    public function it_generates_condition_key_for_default(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [],
                    'rules' => ['name' => 'required'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['name' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertArrayHasKey('default', $schema['discriminator']['mapping']);
    }

    #[Test]
    public function it_handles_object_rules_in_apply_rule_constraints(): void
    {
        // Use an object rule (like Rule::in()) to test the object handling path
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => [
                        'status' => ['required', new \stdClass],
                    ],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['status' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        // Should not throw exception, object rules are skipped
        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertArrayHasKey('status', $schema['oneOf'][0]['properties']);
    }

    #[Test]
    public function it_handles_required_if_rule(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['name' => 'required_if:type,admin'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['name' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertContains('name', $schema['oneOf'][0]['required']);
    }

    #[Test]
    public function it_handles_required_unless_rule(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['name' => 'required_unless:type,guest'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['name' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertContains('name', $schema['oneOf'][0]['required']);
    }

    #[Test]
    public function it_handles_required_with_rule(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['name' => 'required_with:email'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['name' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertContains('name', $schema['oneOf'][0]['required']);
    }

    #[Test]
    public function it_handles_required_without_rule(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [ConditionResult::httpMethod('POST', '$this->isMethod("POST")')],
                    'rules' => ['name' => 'required_without:nickname'],
                ],
                [
                    'conditions' => [ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")')],
                    'rules' => ['name' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        $this->assertContains('name', $schema['oneOf'][0]['required']);
    }

    #[Test]
    public function it_handles_file_upload_with_description_and_constraints(): void
    {
        $parameters = [
            [
                'name' => 'avatar',
                'type' => 'file',
                'required' => true,
                'description' => 'Profile picture',
                'file_info' => [
                    'max_size' => 5242880,
                    'mime_types' => ['image/jpeg', 'image/png'],
                ],
            ],
        ];

        $result = $this->generator->generateFromParameters($parameters);

        $schema = $result['content']['multipart/form-data']['schema'];
        $this->assertEquals('Profile picture', $schema['properties']['avatar']['description']);
        $this->assertEquals(5242880, $schema['properties']['avatar']['maxSize']);
        $this->assertEquals('image/jpeg, image/png', $schema['properties']['avatar']['contentMediaType']);
    }

    #[Test]
    public function it_handles_array_file_upload_with_constraints(): void
    {
        $parameters = [
            [
                'name' => 'photos.*',
                'type' => 'file',
                'required' => true,
                'description' => 'Multiple photos',
                'file_info' => [
                    'max_size' => 10485760,
                    'mime_types' => ['image/jpeg', 'image/png', 'image/gif'],
                ],
            ],
        ];

        $result = $this->generator->generateFromParameters($parameters);

        $schema = $result['content']['multipart/form-data']['schema'];
        $this->assertArrayHasKey('photos', $schema['properties']);
        $this->assertEquals('array', $schema['properties']['photos']['type']);
        $this->assertEquals('binary', $schema['properties']['photos']['items']['format']);
        $this->assertEquals(10485760, $schema['properties']['photos']['items']['maxSize']);
        $this->assertEquals('Multiple photos', $schema['properties']['photos']['description']);
    }

    #[Test]
    public function it_handles_file_upload_without_name(): void
    {
        $parameters = [
            [
                'type' => 'file',
                'required' => true,
            ],
            [
                'name' => 'document',
                'type' => 'file',
                'required' => true,
            ],
        ];

        $result = $this->generator->generateFromParameters($parameters);

        $schema = $result['content']['multipart/form-data']['schema'];
        // File without name should be skipped
        $this->assertArrayHasKey('document', $schema['properties']);
        $this->assertCount(1, $schema['properties']);
    }

    #[Test]
    public function it_handles_mixed_file_and_normal_fields(): void
    {
        $parameters = [
            [
                'name' => 'title',
                'type' => 'string',
                'required' => true,
            ],
            [
                'name' => 'attachment',
                'type' => 'file',
                'required' => true,
            ],
        ];

        $result = $this->generator->generateFromParameters($parameters);

        $schema = $result['content']['multipart/form-data']['schema'];
        $this->assertArrayHasKey('title', $schema['properties']);
        $this->assertArrayHasKey('attachment', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['title']['type']);
        $this->assertEquals('binary', $schema['properties']['attachment']['format']);
    }

    #[Test]
    public function it_handles_fractal_nested_properties(): void
    {
        $fractalData = [
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'example' => 1],
                        'name' => ['type' => 'string', 'example' => 'John'],
                    ],
                ],
            ],
            'availableIncludes' => [],
            'defaultIncludes' => [],
        ];

        $schema = $this->generator->generateFromFractal($fractalData);

        $this->assertArrayHasKey('user', $schema['properties']['data']['properties']);
        $this->assertEquals('object', $schema['properties']['data']['properties']['user']['type']);
        $this->assertArrayHasKey('properties', $schema['properties']['data']['properties']['user']);
        $this->assertArrayHasKey('id', $schema['properties']['data']['properties']['user']['properties']);
    }

    #[Test]
    public function it_handles_normal_field_without_name_in_multipart(): void
    {
        $parameters = [
            [
                'type' => 'string',
                'required' => true,
            ],
            [
                'name' => 'file',
                'type' => 'file',
                'required' => true,
            ],
        ];

        $result = $this->generator->generateFromParameters($parameters);

        $schema = $result['content']['multipart/form-data']['schema'];
        // Normal field without name should be skipped
        $this->assertArrayHasKey('file', $schema['properties']);
        $this->assertCount(1, $schema['properties']);
    }

    #[Test]
    public function it_handles_conditional_parameters_grouping_by_method(): void
    {
        $parameters = [
            [
                'name' => 'title',
                'type' => 'string',
                'required' => false,
                'description' => 'Title field',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                        ],
                        'rules' => ['required', 'string', 'min:3'],
                    ],
                    [
                        'conditions' => [
                            ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")'),
                        ],
                        'rules' => ['string', 'max:100'],
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertCount(2, $schema['oneOf']);
        $this->assertEquals('POST Request', $schema['oneOf'][0]['title']);
        $this->assertEquals('PUT Request', $schema['oneOf'][1]['title']);
    }

    #[Test]
    public function it_handles_conditional_parameters_with_default_method(): void
    {
        $parameters = [
            [
                'name' => 'field',
                'type' => 'string',
                'required' => false,
                'description' => 'Test field',
                'conditional_rules' => [
                    [
                        'conditions' => [],
                        'rules' => ['required'],
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        // Single condition group should generate regular schema
        $this->assertEquals('object', $schema['type']);
    }

    #[Test]
    public function it_handles_conditional_parameters_deduplication(): void
    {
        $parameters = [
            [
                'name' => 'title',
                'type' => 'string',
                'required' => false,
                'description' => 'Title field',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                        ],
                        'rules' => ['required'],
                    ],
                    [
                        'conditions' => [
                            ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                        ],
                        'rules' => ['string'],
                    ],
                ],
            ],
            [
                'name' => 'body',
                'type' => 'string',
                'required' => false,
                'description' => 'Body field',
                'conditional_rules' => [
                    [
                        'conditions' => [
                            ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")'),
                        ],
                        'rules' => ['string'],
                    ],
                ],
            ],
        ];

        $schema = $this->generator->generateFromConditionalParameters($parameters);

        $this->assertArrayHasKey('oneOf', $schema);
        // POST should have only title (deduplicated)
        // PUT should have only body
    }

    #[Test]
    public function it_handles_array_file_upload_with_bracket_notation(): void
    {
        $parameters = [
            [
                'name' => 'documents[*]',
                'type' => 'file',
                'required' => true,
            ],
        ];

        $result = $this->generator->generateFromParameters($parameters);

        $schema = $result['content']['multipart/form-data']['schema'];
        $this->assertArrayHasKey('documents', $schema['properties']);
        $this->assertEquals('array', $schema['properties']['documents']['type']);
    }

    #[Test]
    public function it_handles_array_file_upload_with_empty_bracket_notation(): void
    {
        $parameters = [
            [
                'name' => 'files[]',
                'type' => 'file',
                'required' => true,
            ],
        ];

        $result = $this->generator->generateFromParameters($parameters);

        $schema = $result['content']['multipart/form-data']['schema'];
        $this->assertArrayHasKey('files', $schema['properties']);
        $this->assertEquals('array', $schema['properties']['files']['type']);
    }

    #[Test]
    public function it_handles_combined_conditions_in_description(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::httpMethod('POST', '$this->isMethod("POST")'),
                        ConditionResult::userCheck('isAdmin', '$this->isAdmin()'),
                    ],
                    'rules' => ['level' => 'required|integer'],
                ],
                [
                    'conditions' => [
                        ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")'),
                    ],
                    'rules' => ['level' => 'integer'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        // Combined conditions should be joined with AND
        $this->assertStringContainsString('HTTP method is POST', $schema['oneOf'][0]['description']);
        $this->assertStringContainsString('AND', $schema['oneOf'][0]['description']);
        $this->assertStringContainsString('user isAdmin()', $schema['oneOf'][0]['description']);
    }

    #[Test]
    public function it_generates_condition_key_for_request_field_with_field_value(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::requestField('has', 'type', '$this->has("type")'),
                    ],
                    'rules' => ['data' => 'required'],
                ],
                [
                    'conditions' => [
                        ConditionResult::requestField('filled', 'value', '$this->filled("value")'),
                    ],
                    'rules' => ['data' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        // Verify discriminator mapping keys include both check and field values
        $this->assertArrayHasKey('request_has_type', $schema['discriminator']['mapping']);
        $this->assertArrayHasKey('request_filled_value', $schema['discriminator']['mapping']);
    }

    #[Test]
    public function it_generates_condition_key_for_custom_expression(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::custom('count > 10'),
                    ],
                    'rules' => ['items' => 'required'],
                ],
                [
                    'conditions' => [
                        ConditionResult::custom('count <= 10'),
                    ],
                    'rules' => ['items' => 'array'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        // Custom expressions should generate md5-based keys
        $mapping = $schema['discriminator']['mapping'];
        $keys = array_keys($mapping);

        // Keys should be 8-character md5 substrings
        $this->assertCount(2, $keys);
        $this->assertEquals(8, strlen($keys[0]));
        $this->assertEquals(8, strlen($keys[1]));

        // Different expressions should generate different keys
        $this->assertNotEquals($keys[0], $keys[1]);
    }

    #[Test]
    public function it_generates_condition_key_for_user_check(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::userCheck('isAdmin', '$this->isAdmin()'),
                    ],
                    'rules' => ['level' => 'required'],
                ],
                [
                    'conditions' => [
                        ConditionResult::userCheck('isGuest', '$this->isGuest()'),
                    ],
                    'rules' => ['level' => 'integer'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        // Verify user_check generates specific keys
        $this->assertArrayHasKey('user_isadmin', $schema['discriminator']['mapping']);
        $this->assertArrayHasKey('user_isguest', $schema['discriminator']['mapping']);
    }

    #[Test]
    public function it_handles_condition_without_expression_key(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [
                        ConditionResult::custom('unknown'),
                    ],
                    'rules' => ['data' => 'required'],
                ],
                [
                    'conditions' => [
                        ConditionResult::httpMethod('PUT', '$this->isMethod("PUT")'),
                    ],
                    'rules' => ['data' => 'string'],
                ],
            ],
        ];

        $schema = $this->generator->generateConditionalSchema($conditionalRules, []);

        // Unknown type without expression should use 'unknown' as fallback
        $mapping = $schema['discriminator']['mapping'];
        $keys = array_keys($mapping);

        // First key should be md5 of 'unknown' (8 chars)
        $expectedKey = substr(md5('unknown'), 0, 8);
        $this->assertArrayHasKey($expectedKey, $mapping);
        $this->assertArrayHasKey('put', $mapping);
    }
}
