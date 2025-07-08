<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Tests\TestCase;

class SchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SchemaGenerator;
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
    public function it_generates_schema_from_resource_without_example_keys()
    {
        $resourceStructure = [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string', 'example' => 'user@example.com'],
            'created_at' => ['type' => 'string'],
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
}
