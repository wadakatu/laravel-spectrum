<?php

namespace LaravelPrism\Tests\Unit;

use LaravelPrism\Analyzers\ResourceAnalyzer;
use LaravelPrism\Tests\Fixtures\BooleanTestResource;
use LaravelPrism\Tests\Fixtures\CollectionTestResource;
use LaravelPrism\Tests\Fixtures\DateTestResource;
use LaravelPrism\Tests\Fixtures\NestedTestResource;
use LaravelPrism\Tests\Fixtures\UserResource;
use LaravelPrism\Tests\TestCase;
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
        $this->analyzer = new ResourceAnalyzer;
    }

    /** @test */
    public function it_analyzes_resource_structure()
    {
        // Act
        $structure = $this->analyzer->analyze(UserResource::class);

        // Assert
        $this->assertArrayHasKey('id', $structure);
        $this->assertArrayHasKey('name', $structure);
        $this->assertArrayHasKey('email', $structure);
        $this->assertEquals('integer', $structure['id']['type']);
        $this->assertEquals('string', $structure['name']['type']);
    }

    /** @test */
    public function it_detects_date_fields()
    {
        // Arrange - Resource with date fields
        $testResourceClass = DateTestResource::class;

        // Act
        $structure = $this->analyzer->analyze($testResourceClass);

        // Assert
        $this->assertEquals('string', $structure['created_at']['type']);
        $this->assertStringContainsString(' ', $structure['created_at']['example']);
    }

    /** @test */
    public function it_returns_empty_array_for_non_resource_class()
    {
        // Act
        $structure = $this->analyzer->analyze(\stdClass::class);

        // Assert
        $this->assertIsArray($structure);
        $this->assertEmpty($structure);
    }

    /** @test */
    public function it_handles_nested_properties()
    {
        // Arrange
        $testResourceClass = NestedTestResource::class;

        // Act
        $structure = $this->analyzer->analyze($testResourceClass);

        // Assert
        $this->assertArrayHasKey('id', $structure);
        $this->assertArrayHasKey('posts_count', $structure);
        $this->assertEquals('integer', $structure['id']['type']);
        $this->assertEquals('integer', $structure['posts_count']['type']);
    }

    /** @test */
    public function it_detects_collection_fields()
    {
        // Arrange
        $testResourceClass = CollectionTestResource::class;

        // Act
        $structure = $this->analyzer->analyze($testResourceClass);

        // Assert
        $this->assertArrayHasKey('tags', $structure);
        $this->assertArrayHasKey('categories', $structure);
        $this->assertEquals('array', $structure['tags']['type']);
        $this->assertEquals('array', $structure['categories']['type']);
    }

    /** @test */
    public function it_detects_boolean_fields()
    {
        // Arrange
        $testResourceClass = BooleanTestResource::class;

        // Act
        $structure = $this->analyzer->analyze($testResourceClass);

        // Assert
        $this->assertEquals('boolean', $structure['verified']['type']);
    }

    /** @test */
    public function it_can_analyze_simple_resource()
    {
        $result = $this->analyzer->analyze(SimpleUserResource::class, true);
        
        $this->assertArrayHasKey('properties', $result);
        $this->assertArrayHasKey('id', $result['properties']);
        $this->assertArrayHasKey('name', $result['properties']);
        $this->assertArrayHasKey('email', $result['properties']);
        
        $this->assertEquals('integer', $result['properties']['id']['type']);
        $this->assertEquals('string', $result['properties']['name']['type']);
        $this->assertEquals('string', $result['properties']['email']['type']);
    }
    
    /** @test */
    public function it_can_analyze_conditional_fields()
    {
        $result = $this->analyzer->analyze(ConditionalFieldsResource::class, true);
        
        $this->assertArrayHasKey('conditionalFields', $result);
        $this->assertNotEmpty($result['conditionalFields']);
        
        // secret フィールドが条件付きとして認識されているか
        $this->assertTrue($result['properties']['secret']['conditional']);
    }
    
    /** @test */
    public function it_can_analyze_nested_resources()
    {
        $result = $this->analyzer->analyze(NestedResourcesResource::class, true);
        
        $this->assertArrayHasKey('nestedResources', $result);
        $this->assertContains('PostResource', $result['nestedResources']);
        $this->assertContains('ProfileResource', $result['nestedResources']);
        
        // posts が配列として認識されているか
        $this->assertEquals('array', $result['properties']['posts']['type']);
    }
    
    /** @test */
    public function it_can_analyze_when_loaded_relationships()
    {
        $result = $this->analyzer->analyze(RelationshipResource::class, true);
        
        $this->assertArrayHasKey('posts', $result['properties']);
        $this->assertTrue($result['properties']['posts']['conditional']);
        $this->assertEquals('whenLoaded', $result['properties']['posts']['condition']);
        $this->assertEquals('posts', $result['properties']['posts']['relation']);
    }
    
    /** @test */
    public function it_can_analyze_date_formatting()
    {
        $result = $this->analyzer->analyze(DateFormattingResource::class, true);
        
        $this->assertEquals('string', $result['properties']['created_at']['type']);
        $this->assertEquals('date-time', $result['properties']['created_at']['format']);
    }
    
    /** @test */
    public function it_can_analyze_method_chains()
    {
        $result = $this->analyzer->analyze(MethodChainResource::class, true);
        
        // Enumのvalue
        $this->assertEquals('string', $result['properties']['status']['type']);
        $this->assertEquals('enum', $result['properties']['status']['source']);
        
        // 文字列連結
        $this->assertEquals('string', $result['properties']['full_name']['type']);
    }
    
    /** @test */
    public function it_can_generate_openapi_schema()
    {
        $result = $this->analyzer->analyze(SimpleUserResource::class, true);
        $schema = $this->analyzer->generateSchema($result);
        
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
        
        // 必須フィールドの確認
        $this->assertContains('id', $schema['required']);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('email', $schema['required']);
    }
    
    /** @test */
    public function it_handles_missing_toarray_method_gracefully()
    {
        $result = $this->analyzer->analyze(NoToArrayResource::class);
        
        $this->assertEmpty($result);
    }
    
    /** @test */
    public function it_can_analyze_resource_collection()
    {
        $result = $this->analyzer->analyze(UserCollection::class);
        
        $this->assertTrue($result['isCollection']);
    }
    
    /** @test */
    public function it_can_analyze_with_method()
    {
        $result = $this->analyzer->analyze(ResourceWithMeta::class);
        
        $this->assertArrayHasKey('with', $result);
        $this->assertArrayHasKey('meta', $result['with']);
    }
}
