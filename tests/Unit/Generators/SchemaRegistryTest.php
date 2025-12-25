<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\SchemaRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SchemaRegistryTest extends TestCase
{
    private SchemaRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SchemaRegistry;
    }

    #[Test]
    public function it_can_register_a_schema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ];

        $this->registry->register('UserResource', $schema);

        $this->assertTrue($this->registry->has('UserResource'));
        $this->assertEquals($schema, $this->registry->get('UserResource'));
    }

    #[Test]
    public function it_generates_ref_for_registered_schema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
        ];

        $this->registry->register('PostResource', $schema);

        $ref = $this->registry->getRef('PostResource');

        $this->assertEquals(['$ref' => '#/components/schemas/PostResource'], $ref);
    }

    #[Test]
    public function it_returns_all_registered_schemas(): void
    {
        $userSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ];

        $postSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
            ],
        ];

        $this->registry->register('UserResource', $userSchema);
        $this->registry->register('PostResource', $postSchema);

        $allSchemas = $this->registry->all();

        $this->assertCount(2, $allSchemas);
        $this->assertArrayHasKey('UserResource', $allSchemas);
        $this->assertArrayHasKey('PostResource', $allSchemas);
        $this->assertEquals($userSchema, $allSchemas['UserResource']);
        $this->assertEquals($postSchema, $allSchemas['PostResource']);
    }

    #[Test]
    public function it_does_not_duplicate_same_schema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
        ];

        $this->registry->register('UserResource', $schema);
        $this->registry->register('UserResource', $schema);

        $allSchemas = $this->registry->all();

        $this->assertCount(1, $allSchemas);
    }

    #[Test]
    public function it_can_be_cleared(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
        ];

        $this->registry->register('UserResource', $schema);
        $this->assertTrue($this->registry->has('UserResource'));

        $this->registry->clear();

        $this->assertFalse($this->registry->has('UserResource'));
        $this->assertEmpty($this->registry->all());
    }

    #[Test]
    public function it_extracts_schema_name_from_class_name(): void
    {
        $schemaName = $this->registry->extractSchemaName('App\\Http\\Resources\\UserResource');

        $this->assertEquals('UserResource', $schemaName);
    }

    #[Test]
    public function it_extracts_schema_name_from_simple_class_name(): void
    {
        $schemaName = $this->registry->extractSchemaName('UserResource');

        $this->assertEquals('UserResource', $schemaName);
    }

    #[Test]
    public function it_handles_nested_namespace_class_names(): void
    {
        $schemaName = $this->registry->extractSchemaName('App\\Http\\Resources\\Api\\V1\\UserResource');

        $this->assertEquals('UserResource', $schemaName);
    }

    #[Test]
    public function it_registers_and_returns_ref_in_one_call(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
        ];

        $ref = $this->registry->registerAndGetRef('App\\Http\\Resources\\PostResource', $schema);

        $this->assertEquals(['$ref' => '#/components/schemas/PostResource'], $ref);
        $this->assertTrue($this->registry->has('PostResource'));
    }

    #[Test]
    public function it_returns_false_for_non_existent_schema(): void
    {
        $this->assertFalse($this->registry->has('NonExistentResource'));
    }

    #[Test]
    public function it_returns_null_for_non_existent_schema(): void
    {
        $this->assertNull($this->registry->get('NonExistentResource'));
    }
}
