<?php

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\OpenApiParameter;
use LaravelSpectrum\DTO\OpenApiSchema;
use LaravelSpectrum\DTO\QueryParameterInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiParameterTest extends TestCase
{
    #[Test]
    public function it_creates_query_parameter(): void
    {
        $param = OpenApiParameter::query(
            name: 'search',
            schema: OpenApiSchema::string(),
            required: false,
            description: 'Search query',
        );

        $this->assertEquals('search', $param->name);
        $this->assertEquals('query', $param->in);
        $this->assertFalse($param->required);
        $this->assertEquals('string', $param->schema->type);
        $this->assertEquals('Search query', $param->description);
    }

    #[Test]
    public function it_creates_path_parameter(): void
    {
        $param = OpenApiParameter::path(
            name: 'id',
            schema: OpenApiSchema::integer(),
            description: 'Resource ID',
        );

        $this->assertEquals('id', $param->name);
        $this->assertEquals('path', $param->in);
        $this->assertTrue($param->required); // Path params are always required
        $this->assertEquals('integer', $param->schema->type);
    }

    #[Test]
    public function it_creates_header_parameter(): void
    {
        $param = OpenApiParameter::header(
            name: 'X-Api-Key',
            schema: OpenApiSchema::string(),
            required: true,
            description: 'API Key',
        );

        $this->assertEquals('X-Api-Key', $param->name);
        $this->assertEquals('header', $param->in);
        $this->assertTrue($param->required);
    }

    #[Test]
    public function it_creates_from_query_parameter_info(): void
    {
        $info = new QueryParameterInfo(
            name: 'page',
            type: 'integer',
            required: false,
            default: 1,
            description: 'Page number',
        );

        $param = OpenApiParameter::fromQueryParameterInfo($info);

        $this->assertEquals('page', $param->name);
        $this->assertEquals('query', $param->in);
        $this->assertFalse($param->required);
        $this->assertEquals('integer', $param->schema->type);
        $this->assertEquals(1, $param->schema->default);
        $this->assertEquals('Page number', $param->description);
    }

    #[Test]
    public function it_creates_from_query_parameter_info_with_enum(): void
    {
        $info = new QueryParameterInfo(
            name: 'status',
            type: 'string',
            required: true,
            default: 'active',
            enum: ['active', 'inactive', 'pending'],
            description: 'Status filter',
        );

        $param = OpenApiParameter::fromQueryParameterInfo($info);

        $this->assertEquals(['active', 'inactive', 'pending'], $param->schema->enum);
        $this->assertEquals('active', $param->schema->default);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'name' => 'limit',
            'in' => 'query',
            'required' => false,
            'schema' => [
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'description' => 'Maximum items to return',
        ];

        $param = OpenApiParameter::fromArray($data);

        $this->assertEquals('limit', $param->name);
        $this->assertEquals('query', $param->in);
        $this->assertFalse($param->required);
        $this->assertEquals('integer', $param->schema->type);
        $this->assertEquals(10, $param->schema->default);
        $this->assertEquals(1, $param->schema->minimum);
        $this->assertEquals(100, $param->schema->maximum);
        $this->assertEquals('Maximum items to return', $param->description);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $param = new OpenApiParameter(
            name: 'page',
            in: 'query',
            required: false,
            schema: new OpenApiSchema(
                type: 'integer',
                default: 1,
                minimum: 1,
            ),
            description: 'Page number',
        );

        $array = $param->toArray();

        $this->assertEquals('page', $array['name']);
        $this->assertEquals('query', $array['in']);
        $this->assertFalse($array['required']);
        $this->assertEquals('integer', $array['schema']['type']);
        $this->assertEquals(1, $array['schema']['default']);
        $this->assertEquals(1, $array['schema']['minimum']);
        $this->assertEquals('Page number', $array['description']);
    }

    #[Test]
    public function it_converts_to_array_with_style_and_explode(): void
    {
        $param = new OpenApiParameter(
            name: 'tags',
            in: 'query',
            required: false,
            schema: OpenApiSchema::stringArray(),
            style: 'form',
            explode: true,
        );

        $array = $param->toArray();

        $this->assertEquals('form', $array['style']);
        $this->assertTrue($array['explode']);
    }

    #[Test]
    public function it_only_includes_non_null_values_in_array(): void
    {
        $param = OpenApiParameter::query(
            name: 'q',
            schema: OpenApiSchema::string(),
        );

        $array = $param->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('in', $array);
        $this->assertArrayHasKey('required', $array);
        $this->assertArrayHasKey('schema', $array);
        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayNotHasKey('style', $array);
        $this->assertArrayNotHasKey('explode', $array);
    }

    #[Test]
    public function it_creates_immutable_copy_with_style_and_explode(): void
    {
        $original = OpenApiParameter::query(
            name: 'ids',
            schema: OpenApiSchema::stringArray(),
        );

        $updated = $original->withStyleAndExplode('form', true);

        $this->assertNull($original->style);
        $this->assertNull($original->explode);
        $this->assertEquals('form', $updated->style);
        $this->assertTrue($updated->explode);
    }

    #[Test]
    public function it_creates_immutable_copy_with_schema(): void
    {
        $original = OpenApiParameter::query(
            name: 'status',
            schema: OpenApiSchema::string(),
        );

        $newSchema = OpenApiSchema::string()->withEnum(['active', 'inactive']);
        $updated = $original->withSchema($newSchema);

        $this->assertNull($original->schema->enum);
        $this->assertEquals(['active', 'inactive'], $updated->schema->enum);
    }

    #[Test]
    public function it_checks_if_array_type(): void
    {
        $arrayParam = OpenApiParameter::query('ids', OpenApiSchema::stringArray());
        $stringParam = OpenApiParameter::query('name', OpenApiSchema::string());

        $this->assertTrue($arrayParam->isArrayType());
        $this->assertFalse($stringParam->isArrayType());
    }

    #[Test]
    public function it_checks_if_query_parameter(): void
    {
        $queryParam = OpenApiParameter::query('search', OpenApiSchema::string());
        $pathParam = OpenApiParameter::path('id', OpenApiSchema::integer());

        $this->assertTrue($queryParam->isQueryParameter());
        $this->assertFalse($pathParam->isQueryParameter());
    }

    #[Test]
    public function it_checks_if_path_parameter(): void
    {
        $queryParam = OpenApiParameter::query('search', OpenApiSchema::string());
        $pathParam = OpenApiParameter::path('id', OpenApiSchema::integer());

        $this->assertFalse($queryParam->isPathParameter());
        $this->assertTrue($pathParam->isPathParameter());
    }

    #[Test]
    public function it_uses_constants_for_locations(): void
    {
        $this->assertEquals('query', OpenApiParameter::IN_QUERY);
        $this->assertEquals('path', OpenApiParameter::IN_PATH);
        $this->assertEquals('header', OpenApiParameter::IN_HEADER);
        $this->assertEquals('cookie', OpenApiParameter::IN_COOKIE);
    }

    #[Test]
    public function it_uses_constants_for_styles(): void
    {
        $this->assertEquals('form', OpenApiParameter::STYLE_FORM);
        $this->assertEquals('simple', OpenApiParameter::STYLE_SIMPLE);
        $this->assertEquals('spaceDelimited', OpenApiParameter::STYLE_SPACE_DELIMITED);
        $this->assertEquals('pipeDelimited', OpenApiParameter::STYLE_PIPE_DELIMITED);
        $this->assertEquals('deepObject', OpenApiParameter::STYLE_DEEP_OBJECT);
    }

    #[Test]
    public function it_handles_deprecated_property(): void
    {
        $param = new OpenApiParameter(
            name: 'old_field',
            in: 'query',
            required: false,
            schema: OpenApiSchema::string(),
            deprecated: true,
        );

        $this->assertTrue($param->deprecated);

        $array = $param->toArray();
        $this->assertTrue($array['deprecated']);
    }

    #[Test]
    public function it_handles_allow_empty_value_property(): void
    {
        $param = new OpenApiParameter(
            name: 'filter',
            in: 'query',
            required: false,
            schema: OpenApiSchema::string(),
            allowEmptyValue: true,
        );

        $this->assertTrue($param->allowEmptyValue);

        $array = $param->toArray();
        $this->assertTrue($array['allowEmptyValue']);
    }

    #[Test]
    public function it_creates_from_array_with_deprecated_and_allow_empty_value(): void
    {
        $data = [
            'name' => 'legacy_param',
            'in' => 'query',
            'required' => false,
            'schema' => ['type' => 'string'],
            'deprecated' => true,
            'allowEmptyValue' => true,
        ];

        $param = OpenApiParameter::fromArray($data);

        $this->assertTrue($param->deprecated);
        $this->assertTrue($param->allowEmptyValue);
    }
}
