<?php

namespace LaravelPrism\Tests\Unit;

use LaravelPrism\Analyzers\ResourceAnalyzer;
use LaravelPrism\Tests\Fixtures\BooleanTestResource;
use LaravelPrism\Tests\Fixtures\CollectionTestResource;
use LaravelPrism\Tests\Fixtures\DateTestResource;
use LaravelPrism\Tests\Fixtures\NestedTestResource;
use LaravelPrism\Tests\Fixtures\UserResource;
use LaravelPrism\Tests\TestCase;

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
}
