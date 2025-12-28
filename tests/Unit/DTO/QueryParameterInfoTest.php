<?php

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\QueryParameterInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryParameterInfoTest extends TestCase
{
    #[Test]
    public function it_creates_with_minimal_parameters(): void
    {
        $info = new QueryParameterInfo(
            name: 'search',
            type: 'string',
        );

        $this->assertEquals('search', $info->name);
        $this->assertEquals('string', $info->type);
        $this->assertFalse($info->required);
        $this->assertNull($info->default);
        $this->assertEquals('input', $info->source);
        $this->assertNull($info->description);
        $this->assertNull($info->enum);
        $this->assertNull($info->validationRules);
        $this->assertEquals([], $info->context);
    }

    #[Test]
    public function it_creates_with_all_parameters(): void
    {
        $info = new QueryParameterInfo(
            name: 'status',
            type: 'string',
            required: true,
            default: 'active',
            source: 'input',
            description: 'Filter by status',
            enum: ['active', 'inactive', 'pending'],
            validationRules: ['required', 'in:active,inactive,pending'],
            context: ['line' => 42],
        );

        $this->assertEquals('status', $info->name);
        $this->assertEquals('string', $info->type);
        $this->assertTrue($info->required);
        $this->assertEquals('active', $info->default);
        $this->assertEquals('input', $info->source);
        $this->assertEquals('Filter by status', $info->description);
        $this->assertEquals(['active', 'inactive', 'pending'], $info->enum);
        $this->assertEquals(['required', 'in:active,inactive,pending'], $info->validationRules);
        $this->assertEquals(['line' => 42], $info->context);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'name' => 'page',
            'type' => 'integer',
            'required' => false,
            'default' => 1,
            'source' => 'integer',
            'description' => 'Page number',
            'validation_rules' => ['integer', 'min:1'],
        ];

        $info = QueryParameterInfo::fromArray($data);

        $this->assertEquals('page', $info->name);
        $this->assertEquals('integer', $info->type);
        $this->assertFalse($info->required);
        $this->assertEquals(1, $info->default);
        $this->assertEquals('integer', $info->source);
        $this->assertEquals('Page number', $info->description);
        $this->assertEquals(['integer', 'min:1'], $info->validationRules);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new QueryParameterInfo(
            name: 'per_page',
            type: 'integer',
            required: false,
            default: 15,
            source: 'integer',
            description: 'Items per page',
            enum: null,
            validationRules: ['integer', 'between:10,100'],
        );

        $array = $info->toArray();

        $this->assertEquals('per_page', $array['name']);
        $this->assertEquals('integer', $array['type']);
        $this->assertFalse($array['required']);
        $this->assertEquals(15, $array['default']);
        $this->assertEquals('integer', $array['source']);
        $this->assertEquals('Items per page', $array['description']);
        $this->assertEquals(['integer', 'between:10,100'], $array['validation_rules']);
        $this->assertArrayNotHasKey('enum', $array);
    }

    #[Test]
    public function it_creates_immutable_copy_with_validation_rules(): void
    {
        $original = new QueryParameterInfo(
            name: 'email',
            type: 'string',
        );

        $updated = $original->withValidationRules(['required', 'email']);

        $this->assertNull($original->validationRules);
        $this->assertEquals(['required', 'email'], $updated->validationRules);
        $this->assertEquals('email', $updated->name);
        $this->assertEquals('string', $updated->type);
    }

    #[Test]
    public function it_creates_immutable_copy_with_type(): void
    {
        $original = new QueryParameterInfo(
            name: 'count',
            type: 'string',
        );

        $updated = $original->withType('integer');

        $this->assertEquals('string', $original->type);
        $this->assertEquals('integer', $updated->type);
        $this->assertEquals('count', $updated->name);
    }

    #[Test]
    public function it_creates_immutable_copy_with_required(): void
    {
        $original = new QueryParameterInfo(
            name: 'token',
            type: 'string',
            required: false,
        );

        $updated = $original->withRequired(true);

        $this->assertFalse($original->required);
        $this->assertTrue($updated->required);
    }

    #[Test]
    public function it_handles_from_array_with_minimal_data(): void
    {
        $data = ['name' => 'q'];

        $info = QueryParameterInfo::fromArray($data);

        $this->assertEquals('q', $info->name);
        $this->assertEquals('string', $info->type);
        $this->assertFalse($info->required);
        $this->assertNull($info->default);
        $this->assertEquals('input', $info->source);
    }
}
