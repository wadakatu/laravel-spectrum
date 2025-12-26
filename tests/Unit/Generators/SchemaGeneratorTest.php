<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

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
        $resourceStructure = [
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                'created_at' => ['type' => 'string'],
            ],
        ];

        $schema = $this->generator->generateFromResource($resourceStructure);

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
        $resourceStructure = [
            'properties' => [
                'id' => ['type' => 'integer'],
                'secret' => [
                    'type' => 'string',
                ],
            ],
        ];

        $schema = $this->generator->generateFromResource($resourceStructure);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('secret', $schema['properties']);
        $this->assertEquals('integer', $schema['properties']['id']['type']);
        $this->assertEquals('string', $schema['properties']['secret']['type']);
    }

    #[Test]
    public function it_generates_schema_from_resource_preserves_nested_type(): void
    {
        $resourceStructure = [
            'properties' => [
                'id' => ['type' => 'integer'],
                'address' => [
                    'type' => 'object',
                ],
            ],
        ];

        $schema = $this->generator->generateFromResource($resourceStructure);

        $this->assertEquals('object', $schema['properties']['address']['type']);
    }

    #[Test]
    public function it_generates_schema_from_resource_preserves_array_type(): void
    {
        $resourceStructure = [
            'properties' => [
                'tags' => [
                    'type' => 'array',
                ],
            ],
        ];

        $schema = $this->generator->generateFromResource($resourceStructure);

        $this->assertEquals('array', $schema['properties']['tags']['type']);
    }

    #[Test]
    public function it_generates_schema_from_empty_resource(): void
    {
        $resourceStructure = [
            'properties' => [],
        ];

        $schema = $this->generator->generateFromResource($resourceStructure);

        $this->assertEquals('object', $schema['type']);
        $this->assertEmpty($schema['properties']);
    }

    #[Test]
    public function it_generates_schema_from_resource_with_example(): void
    {
        $resourceStructure = [
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'example' => 'user@example.com',
                ],
            ],
        ];

        $schema = $this->generator->generateFromResource($resourceStructure);

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
                            ['type' => 'http_method', 'method' => 'POST'],
                        ],
                        'rules' => ['required', 'email'],
                    ],
                    [
                        'conditions' => [
                            ['type' => 'http_method', 'method' => 'PUT'],
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
                            ['type' => 'http_method', 'method' => 'POST'],
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
                            ['type' => 'other_condition', 'value' => 'test'],
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
        $resourceStructure = [
            'properties' => [
                'user' => [
                    'type' => 'object',
                ],
            ],
        ];

        $schema = $this->generator->generateFromResource($resourceStructure);

        // generateFromResource only extracts type and example
        $this->assertEquals('object', $schema['properties']['user']['type']);
    }

    #[Test]
    public function it_handles_resource_with_array_type(): void
    {
        $resourceStructure = [
            'properties' => [
                'items' => [
                    'type' => 'array',
                ],
            ],
        ];

        $schema = $this->generator->generateFromResource($resourceStructure);

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
}
