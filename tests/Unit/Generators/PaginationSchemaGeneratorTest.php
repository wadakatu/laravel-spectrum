<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\PaginationSchemaGenerator;
use PHPUnit\Framework\TestCase;

class PaginationSchemaGeneratorTest extends TestCase
{
    private PaginationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PaginationSchemaGenerator;
    }

    public function test_generates_length_aware_paginator_schema(): void
    {
        $dataSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ];

        $result = $this->generator->generate('length_aware', $dataSchema);

        $this->assertEquals('object', $result['type']);
        $this->assertArrayHasKey('data', $result['properties']);
        $this->assertEquals('array', $result['properties']['data']['type']);
        $this->assertEquals($dataSchema, $result['properties']['data']['items']);

        // Check all required fields
        $this->assertArrayHasKey('current_page', $result['properties']);
        $this->assertArrayHasKey('first_page_url', $result['properties']);
        $this->assertArrayHasKey('from', $result['properties']);
        $this->assertArrayHasKey('last_page', $result['properties']);
        $this->assertArrayHasKey('last_page_url', $result['properties']);
        $this->assertArrayHasKey('links', $result['properties']);
        $this->assertArrayHasKey('next_page_url', $result['properties']);
        $this->assertArrayHasKey('path', $result['properties']);
        $this->assertArrayHasKey('per_page', $result['properties']);
        $this->assertArrayHasKey('prev_page_url', $result['properties']);
        $this->assertArrayHasKey('to', $result['properties']);
        $this->assertArrayHasKey('total', $result['properties']);

        // Check links structure
        $this->assertEquals('array', $result['properties']['links']['type']);
        $this->assertArrayHasKey('items', $result['properties']['links']);
        $this->assertEquals('object', $result['properties']['links']['items']['type']);
    }

    public function test_generates_simple_paginator_schema(): void
    {
        $dataSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
            ],
        ];

        $result = $this->generator->generate('simple', $dataSchema);

        $this->assertEquals('object', $result['type']);
        $this->assertArrayHasKey('data', $result['properties']);
        $this->assertEquals('array', $result['properties']['data']['type']);
        $this->assertEquals($dataSchema, $result['properties']['data']['items']);

        // Check all required fields
        $this->assertArrayHasKey('first_page_url', $result['properties']);
        $this->assertArrayHasKey('from', $result['properties']);
        $this->assertArrayHasKey('next_page_url', $result['properties']);
        $this->assertArrayHasKey('path', $result['properties']);
        $this->assertArrayHasKey('per_page', $result['properties']);
        $this->assertArrayHasKey('prev_page_url', $result['properties']);
        $this->assertArrayHasKey('to', $result['properties']);

        // Should not have length-aware specific fields
        $this->assertArrayNotHasKey('current_page', $result['properties']);
        $this->assertArrayNotHasKey('last_page', $result['properties']);
        $this->assertArrayNotHasKey('total', $result['properties']);
        $this->assertArrayNotHasKey('links', $result['properties']);
    }

    public function test_generates_cursor_paginator_schema(): void
    {
        $dataSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'content' => ['type' => 'string'],
            ],
        ];

        $result = $this->generator->generate('cursor', $dataSchema);

        $this->assertEquals('object', $result['type']);
        $this->assertArrayHasKey('data', $result['properties']);
        $this->assertEquals('array', $result['properties']['data']['type']);
        $this->assertEquals($dataSchema, $result['properties']['data']['items']);

        // Check all required fields
        $this->assertArrayHasKey('path', $result['properties']);
        $this->assertArrayHasKey('per_page', $result['properties']);
        $this->assertArrayHasKey('next_cursor', $result['properties']);
        $this->assertArrayHasKey('next_page_url', $result['properties']);
        $this->assertArrayHasKey('prev_cursor', $result['properties']);
        $this->assertArrayHasKey('prev_page_url', $result['properties']);

        // Should not have other paginator fields
        $this->assertArrayNotHasKey('current_page', $result['properties']);
        $this->assertArrayNotHasKey('total', $result['properties']);
        $this->assertArrayNotHasKey('from', $result['properties']);
        $this->assertArrayNotHasKey('to', $result['properties']);
    }

    public function test_returns_original_schema_for_unknown_type(): void
    {
        $dataSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
        ];

        $result = $this->generator->generate('unknown', $dataSchema);

        $this->assertEquals($dataSchema, $result);
    }

    public function test_handles_nullable_fields_correctly(): void
    {
        $dataSchema = ['type' => 'object'];

        $result = $this->generator->generate('length_aware', $dataSchema);

        // Check nullable fields
        $this->assertTrue($result['properties']['from']['nullable']);
        $this->assertTrue($result['properties']['to']['nullable']);
        $this->assertTrue($result['properties']['next_page_url']['nullable']);
        $this->assertTrue($result['properties']['prev_page_url']['nullable']);

        // Links items should have nullable url
        $this->assertTrue($result['properties']['links']['items']['properties']['url']['nullable']);
    }

    public function test_includes_format_for_url_fields(): void
    {
        $dataSchema = ['type' => 'object'];

        $result = $this->generator->generate('length_aware', $dataSchema);

        // Check URL format
        $this->assertEquals('uri', $result['properties']['first_page_url']['format']);
        $this->assertEquals('uri', $result['properties']['last_page_url']['format']);
        $this->assertEquals('uri', $result['properties']['next_page_url']['format']);
        $this->assertEquals('uri', $result['properties']['prev_page_url']['format']);
        $this->assertEquals('uri', $result['properties']['path']['format']);
    }

    public function test_includes_examples_for_numeric_fields(): void
    {
        $dataSchema = ['type' => 'object'];

        $result = $this->generator->generate('length_aware', $dataSchema);

        // Check examples
        $this->assertEquals(1, $result['properties']['current_page']['example']);
    }
}
