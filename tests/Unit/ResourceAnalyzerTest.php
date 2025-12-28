<?php

namespace LaravelSpectrum\Tests\Unit;

use LaravelSpectrum\Analyzers\ResourceAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\DTO\ResourceInfo;
use LaravelSpectrum\Tests\Fixtures\BooleanTestResource;
use LaravelSpectrum\Tests\Fixtures\CollectionTestResource;
use LaravelSpectrum\Tests\Fixtures\DateTestResource;
use LaravelSpectrum\Tests\Fixtures\NestedTestResource;
use LaravelSpectrum\Tests\Fixtures\UserResource;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\Analyzers\Fixtures\ConditionalFieldsResource;
use Tests\Unit\Analyzers\Fixtures\DateFormattingResource;
use Tests\Unit\Analyzers\Fixtures\MethodChainResource;
use Tests\Unit\Analyzers\Fixtures\NestedResourcesResource;
use Tests\Unit\Analyzers\Fixtures\NoToArrayResource;
use Tests\Unit\Analyzers\Fixtures\RelationshipResource;
use Tests\Unit\Analyzers\Fixtures\ResourceWithMeta;
use Tests\Unit\Analyzers\Fixtures\SimpleUserResource;
use Tests\Unit\Analyzers\Fixtures\UserCollection;

class ResourceAnalyzerTest extends TestCase
{
    protected ResourceAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock cache that always calls the callback
        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberResource')
            ->willReturnCallback(function ($class, $callback) {
                return $callback();
            });

        // Register mock cache in container and get analyzer via DI
        $this->app->instance(DocumentationCache::class, $cache);
        $this->analyzer = $this->app->make(ResourceAnalyzer::class);
    }

    #[Test]
    public function it_analyzes_resource_structure()
    {
        // Act
        $structure = $this->analyzer->analyze(UserResource::class);

        // Assert
        $this->assertInstanceOf(ResourceInfo::class, $structure);
        $properties = $structure->properties;
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('email', $properties);
        $this->assertEquals('integer', $properties['id']['type']);
        $this->assertEquals('string', $properties['name']['type']);
    }

    #[Test]
    public function it_detects_date_fields()
    {
        // Arrange - Resource with date fields
        $testResourceClass = DateTestResource::class;

        // Act
        $structure = $this->analyzer->analyze($testResourceClass);

        // Assert
        $properties = $structure->properties;
        $this->assertEquals('string', $properties['created_at']['type']);
        $this->assertStringContainsString(' ', $properties['created_at']['example']);
    }

    #[Test]
    public function it_returns_empty_array_for_non_resource_class()
    {
        // Act
        $structure = $this->analyzer->analyze(\stdClass::class);

        // Assert
        $this->assertInstanceOf(ResourceInfo::class, $structure);
        $this->assertTrue($structure->isEmpty());
    }

    #[Test]
    public function it_handles_nested_properties()
    {
        // Arrange
        $testResourceClass = NestedTestResource::class;

        // Act
        $structure = $this->analyzer->analyze($testResourceClass);

        // Assert
        $properties = $structure->properties;
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('posts_count', $properties);
        $this->assertEquals('integer', $properties['id']['type']);
        $this->assertEquals('integer', $properties['posts_count']['type']);
    }

    #[Test]
    public function it_detects_collection_fields()
    {
        // Arrange
        $testResourceClass = CollectionTestResource::class;

        // Act
        $structure = $this->analyzer->analyze($testResourceClass);

        // Assert
        $properties = $structure->properties;
        $this->assertArrayHasKey('tags', $properties);
        $this->assertArrayHasKey('categories', $properties);
        $this->assertEquals('array', $properties['tags']['type']);
        $this->assertEquals('array', $properties['categories']['type']);
    }

    #[Test]
    public function it_detects_boolean_fields()
    {
        // Arrange
        $testResourceClass = BooleanTestResource::class;

        // Act
        $structure = $this->analyzer->analyze($testResourceClass);

        // Assert
        $properties = $structure->properties;
        $this->assertEquals('boolean', $properties['verified']['type']);
    }

    #[Test]
    public function it_can_analyze_simple_resource()
    {
        $result = $this->analyzer->analyze(SimpleUserResource::class);

        $this->assertNotEmpty($result->properties);
        $this->assertArrayHasKey('id', $result->properties);
        $this->assertArrayHasKey('name', $result->properties);
        $this->assertArrayHasKey('email', $result->properties);

        $this->assertEquals('integer', $result->properties['id']['type']);
        $this->assertEquals('string', $result->properties['name']['type']);
        $this->assertEquals('string', $result->properties['email']['type']);
    }

    #[Test]
    public function it_can_analyze_conditional_fields()
    {
        $result = $this->analyzer->analyze(ConditionalFieldsResource::class);

        $this->assertNotEmpty($result->conditionalFields);

        // secret フィールドが条件付きとして認識されているか
        $this->assertTrue($result->properties['secret']['conditional']);
    }

    #[Test]
    public function it_can_analyze_nested_resources()
    {
        $result = $this->analyzer->analyze(NestedResourcesResource::class);

        $this->assertNotEmpty($result->nestedResources);
        $this->assertContains('PostResource', $result->nestedResources);
        $this->assertContains('ProfileResource', $result->nestedResources);

        // posts が配列として認識されているか
        $this->assertEquals('array', $result->properties['posts']['type']);
    }

    #[Test]
    public function it_can_analyze_when_loaded_relationships()
    {
        $result = $this->analyzer->analyze(RelationshipResource::class);

        $this->assertArrayHasKey('posts', $result->properties);
        $this->assertTrue($result->properties['posts']['conditional']);
        $this->assertEquals('whenLoaded', $result->properties['posts']['condition']);
        $this->assertEquals('posts', $result->properties['posts']['relation']);
    }

    #[Test]
    public function it_can_analyze_date_formatting()
    {
        $result = $this->analyzer->analyze(DateFormattingResource::class);

        $this->assertEquals('string', $result->properties['created_at']['type']);
        $this->assertEquals('date-time', $result->properties['created_at']['format']);
    }

    #[Test]
    public function it_can_analyze_method_chains()
    {
        $result = $this->analyzer->analyze(MethodChainResource::class);

        // Enumのvalue
        $this->assertEquals('string', $result->properties['status']['type']);
        $this->assertEquals('enum', $result->properties['status']['source']);

        // 文字列連結
        $this->assertEquals('string', $result->properties['full_name']['type']);
    }

    #[Test]
    public function it_can_generate_openapi_schema()
    {
        $result = $this->analyzer->analyze(SimpleUserResource::class);
        $schema = $this->analyzer->generateSchema($result->toArray());

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        // 必須フィールドの確認
        $this->assertContains('id', $schema['required']);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('email', $schema['required']);
    }

    #[Test]
    public function it_handles_missing_toarray_method_gracefully()
    {
        $result = $this->analyzer->analyze(NoToArrayResource::class);

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_can_analyze_resource_collection()
    {
        $result = $this->analyzer->analyze(UserCollection::class);

        $this->assertTrue($result->isCollection);
    }

    #[Test]
    public function it_can_analyze_with_method()
    {
        $result = $this->analyzer->analyze(ResourceWithMeta::class);

        $this->assertTrue($result->hasWithData());
        $this->assertArrayHasKey('meta', $result->with);
    }

    #[Test]
    public function it_generates_schema_with_empty_properties(): void
    {
        $schema = $this->analyzer->generateSchema([]);

        $this->assertEquals(['type' => 'object'], $schema);
    }

    #[Test]
    public function it_generates_schema_with_array_items(): void
    {
        $structure = [
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertEquals('array', $schema['properties']['tags']['type']);
        $this->assertEquals('string', $schema['properties']['tags']['items']['type']);
    }

    #[Test]
    public function it_generates_schema_with_nested_object(): void
    {
        $structure = [
            'properties' => [
                'address' => [
                    'type' => 'object',
                    'properties' => [
                        'city' => ['type' => 'string'],
                        'country' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertEquals('object', $schema['properties']['address']['type']);
        $this->assertArrayHasKey('properties', $schema['properties']['address']);
        $this->assertArrayHasKey('city', $schema['properties']['address']['properties']);
        $this->assertArrayHasKey('country', $schema['properties']['address']['properties']);
    }

    #[Test]
    public function it_generates_schema_with_conditional_field_description(): void
    {
        $structure = [
            'properties' => [
                'secret' => [
                    'type' => 'string',
                    'conditional' => true,
                    'condition' => 'whenNotNull',
                ],
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertTrue($schema['properties']['secret']['nullable']);
        $this->assertStringContainsString('Conditional field', $schema['properties']['secret']['description']);
        $this->assertStringContainsString('whenNotNull', $schema['properties']['secret']['description']);
    }

    #[Test]
    public function it_generates_schema_with_example_values(): void
    {
        $structure = [
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'example' => 'active',
                ],
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertEquals('active', $schema['properties']['status']['example']);
    }

    #[Test]
    public function it_generates_schema_with_format(): void
    {
        $structure = [
            'properties' => [
                'created_at' => [
                    'type' => 'string',
                    'format' => 'date-time',
                ],
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertEquals('date-time', $schema['properties']['created_at']['format']);
    }

    #[Test]
    public function it_generates_schema_with_additional_meta_from_with_method(): void
    {
        $result = $this->analyzer->analyze(ResourceWithMeta::class);
        $schema = $this->analyzer->generateSchema($result->toArray());

        // with() メソッドからの追加メタデータがスキーマに含まれる
        $this->assertArrayHasKey('meta', $schema['properties']);
        $this->assertEquals('object', $schema['properties']['meta']['type']);
        $this->assertArrayHasKey('properties', $schema['properties']['meta']);
    }

    #[Test]
    public function it_does_not_include_conditional_fields_in_required(): void
    {
        $structure = [
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'secret' => [
                    'type' => 'string',
                    'conditional' => true,
                ],
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertContains('id', $schema['required']);
        $this->assertContains('name', $schema['required']);
        $this->assertNotContains('secret', $schema['required']);
    }

    #[Test]
    public function it_returns_empty_array_for_nonexistent_class(): void
    {
        $structure = $this->analyzer->analyze('NonExistentClass');

        $this->assertInstanceOf(ResourceInfo::class, $structure);
        $this->assertTrue($structure->isEmpty());
    }

    #[Test]
    public function it_handles_analyze_exception(): void
    {
        // Create a cache that throws exception
        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberResource')
            ->willThrowException(new \Exception('Cache error'));

        $astHelper = $this->app->make(\LaravelSpectrum\Analyzers\Support\AstHelper::class);
        $analyzer = new ResourceAnalyzer($astHelper, $cache);

        $result = $analyzer->analyze(UserResource::class);

        $this->assertInstanceOf(ResourceInfo::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_handles_has_examples_interface(): void
    {
        $result = $this->analyzer->analyze(\LaravelSpectrum\Tests\Fixtures\Resources\ResourceWithExamples::class);

        $this->assertNotEmpty($result->properties);
        $this->assertTrue($result->hasExamples);
        $this->assertNotNull($result->customExample);
        $this->assertTrue($result->hasCustomExamples());
        $this->assertEquals(1, $result->customExample['id']);
        $this->assertArrayHasKey('default', $result->customExamples);
        $this->assertArrayHasKey('admin', $result->customExamples);
    }

    #[Test]
    public function it_detects_resource_collection_by_parent_class(): void
    {
        $result = $this->analyzer->analyze(UserCollection::class);

        $this->assertTrue($result->isCollection);
    }

    #[Test]
    public function it_generates_property_schema_with_nested_object(): void
    {
        $structure = [
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'example' => 1],
                        'name' => ['type' => 'string', 'example' => 'John'],
                    ],
                ],
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertArrayHasKey('user', $schema['properties']);
        $this->assertEquals('object', $schema['properties']['user']['type']);
        $this->assertArrayHasKey('properties', $schema['properties']['user']);
        $this->assertArrayHasKey('id', $schema['properties']['user']['properties']);
        $this->assertArrayHasKey('name', $schema['properties']['user']['properties']);
    }

    #[Test]
    public function it_generates_property_schema_with_array_items(): void
    {
        $structure = [
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'example' => 'tag1',
                    ],
                ],
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertArrayHasKey('tags', $schema['properties']);
        $this->assertEquals('array', $schema['properties']['tags']['type']);
        $this->assertArrayHasKey('items', $schema['properties']['tags']);
        $this->assertEquals('string', $schema['properties']['tags']['items']['type']);
    }

    #[Test]
    public function it_generates_property_schema_with_conditional_and_condition(): void
    {
        $structure = [
            'properties' => [
                'secret' => [
                    'type' => 'string',
                    'conditional' => true,
                    'condition' => 'when user is admin',
                ],
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertArrayHasKey('secret', $schema['properties']);
        $this->assertTrue($schema['properties']['secret']['nullable']);
        $this->assertStringContainsString('Conditional field', $schema['properties']['secret']['description']);
        $this->assertStringContainsString('when user is admin', $schema['properties']['secret']['description']);
    }

    #[Test]
    public function it_handles_ast_parse_failure_gracefully(): void
    {
        // Create a mock AstHelper that throws a parse error
        $astHelper = $this->createMock(\LaravelSpectrum\Analyzers\Support\AstHelper::class);
        $astHelper->method('parseFile')
            ->willThrowException(new \PhpParser\Error('Parse error'));

        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberResource')
            ->willReturnCallback(function ($class, $callback) {
                return $callback();
            });

        $analyzer = new ResourceAnalyzer($astHelper, $cache);
        $result = $analyzer->analyze(UserResource::class);

        $this->assertInstanceOf(ResourceInfo::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_handles_class_not_found_in_ast(): void
    {
        // Create a mock AstHelper that returns empty AST
        $astHelper = $this->createMock(\LaravelSpectrum\Analyzers\Support\AstHelper::class);
        $astHelper->method('parseFile')->willReturn([]);
        $astHelper->method('findClassNode')->willReturn(null);

        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberResource')
            ->willReturnCallback(function ($class, $callback) {
                return $callback();
            });

        $analyzer = new ResourceAnalyzer($astHelper, $cache);
        $result = $analyzer->analyze(UserResource::class);

        $this->assertInstanceOf(ResourceInfo::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_handles_null_ast_from_parse(): void
    {
        $astHelper = $this->createMock(\LaravelSpectrum\Analyzers\Support\AstHelper::class);
        $astHelper->method('parseFile')->willReturn(null);

        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberResource')
            ->willReturnCallback(function ($class, $callback) {
                return $callback();
            });

        $analyzer = new ResourceAnalyzer($astHelper, $cache);
        $result = $analyzer->analyze(UserResource::class);

        $this->assertInstanceOf(ResourceInfo::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_generates_properties_from_array_with_nested_values(): void
    {
        $structure = [
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
            'with' => [
                'meta' => [
                    'version' => '1.0',
                    'nested' => [
                        'key' => 'value',
                    ],
                ],
                'status' => 'ok',
            ],
        ];

        $schema = $this->analyzer->generateSchema($structure);

        $this->assertArrayHasKey('meta', $schema['properties']);
        $this->assertEquals('object', $schema['properties']['meta']['type']);
        $this->assertArrayHasKey('properties', $schema['properties']['meta']);
        $this->assertArrayHasKey('version', $schema['properties']['meta']['properties']);
        $this->assertArrayHasKey('nested', $schema['properties']['meta']['properties']);

        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['status']['type']);
        $this->assertEquals('ok', $schema['properties']['status']['example']);
    }
}
