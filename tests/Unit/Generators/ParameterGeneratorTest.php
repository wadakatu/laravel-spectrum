<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\DTO\OpenApiParameter;
use LaravelSpectrum\DTO\QueryParameterInfo;
use LaravelSpectrum\Generators\ParameterGenerator;
use LaravelSpectrum\Support\QueryParameterTypeInference;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ParameterGeneratorTest extends TestCase
{
    protected ParameterGenerator $generator;

    protected $mockTypeInference;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockTypeInference = Mockery::mock(QueryParameterTypeInference::class);
        $this->generator = new ParameterGenerator($this->mockTypeInference);
    }

    protected function tearDown(): void
    {
        // Reset config values modified by tests
        config([
            'spectrum.parameters.include_style' => true,
            'spectrum.parameters.array_style' => 'form',
            'spectrum.parameters.array_explode' => true,
        ]);

        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_route_parameters_when_no_controller_info(): void
    {
        $route = [
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
            ],
        ];
        $controllerInfo = [];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertInstanceOf(OpenApiParameter::class, $parameters[0]);
        $this->assertEquals('id', $parameters[0]->name);
    }

    #[Test]
    public function it_adds_enum_info_to_existing_route_parameter(): void
    {
        $route = [
            'parameters' => [
                ['name' => 'status', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
            ],
        ];
        $controllerInfo = [
            'enumParameters' => [
                [
                    'name' => 'status',
                    'type' => 'string',
                    'enum' => ['active', 'inactive', 'pending'],
                    'description' => 'User status',
                    'required' => true,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertEquals('status', $parameters[0]->name);
        $this->assertEquals(['active', 'inactive', 'pending'], $parameters[0]->schema->enum);
        $this->assertEquals('User status', $parameters[0]->description);
    }

    #[Test]
    public function it_replaces_schema_when_merging_enum_with_route_parameter(): void
    {
        $route = [
            'parameters' => [
                [
                    'name' => 'status',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50],
                ],
            ],
        ];
        $controllerInfo = [
            'enumParameters' => [
                [
                    'name' => 'status',
                    'type' => 'string',
                    'enum' => ['active', 'inactive'],
                    'description' => '',
                    'required' => true,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        // Verify enum is applied
        $this->assertEquals(['active', 'inactive'], $parameters[0]->schema->enum);
        // Original schema constraints are replaced by enum schema
        $this->assertNull($parameters[0]->schema->minLength);
        $this->assertNull($parameters[0]->schema->maxLength);
    }

    #[Test]
    public function it_adds_enum_as_query_parameter_when_not_in_route(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'enumParameters' => [
                [
                    'name' => 'sort',
                    'type' => 'string',
                    'enum' => ['asc', 'desc'],
                    'description' => 'Sort direction',
                    'required' => false,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertEquals('sort', $parameters[0]->name);
        $this->assertEquals('query', $parameters[0]->in);
        $this->assertFalse($parameters[0]->required);
        $this->assertEquals(['asc', 'desc'], $parameters[0]->schema->enum);
    }

    #[Test]
    public function it_adds_query_parameters(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'page',
                    'type' => 'integer',
                    'required' => false,
                    'default' => 1,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertEquals('page', $parameters[0]->name);
        $this->assertEquals('query', $parameters[0]->in);
        $this->assertEquals('integer', $parameters[0]->schema->type);
        $this->assertEquals(1, $parameters[0]->schema->default);
    }

    #[Test]
    public function it_adds_query_parameter_with_description(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'search',
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Search term',
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertEquals('Search term', $parameters[0]->description);
    }

    #[Test]
    public function it_adds_query_parameter_with_enum(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'order',
                    'type' => 'string',
                    'required' => false,
                    'enum' => ['name', 'date', 'price'],
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertEquals(['name', 'date', 'price'], $parameters[0]->schema->enum);
    }

    #[Test]
    public function it_adds_validation_constraints_to_query_parameter(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'limit',
                    'type' => 'integer',
                    'required' => false,
                    'validation_rules' => ['integer', 'min:1', 'max:100'],
                ],
            ],
        ];

        $this->mockTypeInference->shouldReceive('getConstraintsFromRules')
            ->once()
            ->with(['integer', 'min:1', 'max:100'])
            ->andReturn(['minimum' => 1, 'maximum' => 100]);

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertEquals(1, $parameters[0]->schema->minimum);
        $this->assertEquals(100, $parameters[0]->schema->maximum);
    }

    #[Test]
    public function it_combines_route_enum_and_query_parameters(): void
    {
        $route = [
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
            ],
        ];
        $controllerInfo = [
            'enumParameters' => [
                [
                    'name' => 'status',
                    'type' => 'string',
                    'enum' => ['active', 'inactive'],
                    'description' => '',
                    'required' => false,
                ],
            ],
            'queryParameters' => [
                [
                    'name' => 'page',
                    'type' => 'integer',
                    'required' => false,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(3, $parameters);

        // Route parameter
        $this->assertEquals('id', $parameters[0]->name);
        $this->assertEquals('path', $parameters[0]->in);

        // Enum as query parameter
        $this->assertEquals('status', $parameters[1]->name);
        $this->assertEquals('query', $parameters[1]->in);

        // Query parameter
        $this->assertEquals('page', $parameters[2]->name);
        $this->assertEquals('query', $parameters[2]->in);
    }

    #[Test]
    public function it_handles_empty_parameters(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertEquals([], $parameters);
    }

    #[Test]
    public function it_does_not_add_empty_description_to_enum_route_parameter(): void
    {
        $route = [
            'parameters' => [
                ['name' => 'status', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
            ],
        ];
        $controllerInfo = [
            'enumParameters' => [
                [
                    'name' => 'status',
                    'type' => 'string',
                    'enum' => ['active', 'inactive'],
                    'description' => '', // Empty description
                    'required' => true,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertNull($parameters[0]->description);
    }

    #[Test]
    public function it_does_not_add_empty_description_to_enum_query_parameter(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'enumParameters' => [
                [
                    'name' => 'status',
                    'type' => 'string',
                    'enum' => ['active', 'inactive'],
                    'description' => '', // Empty description
                    'required' => false,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertNull($parameters[0]->description);
    }

    #[Test]
    public function it_handles_required_query_parameter(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'api_key',
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertTrue($parameters[0]->required);
    }

    #[Test]
    public function it_adds_style_and_explode_to_array_query_parameters(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'ids',
                    'type' => 'array',
                    'required' => false,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertEquals('ids', $parameters[0]->name);
        $this->assertEquals('form', $parameters[0]->style);
        $this->assertTrue($parameters[0]->explode);
    }

    #[Test]
    public function it_adds_items_schema_to_array_parameters(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'tags',
                    'type' => 'array',
                    'required' => false,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertEquals('array', $parameters[0]->schema->type);
        $this->assertNotNull($parameters[0]->schema->items);
        $this->assertEquals('string', $parameters[0]->schema->items->type);
    }

    #[Test]
    public function it_does_not_add_style_for_simple_types(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'page',
                    'type' => 'integer',
                    'required' => false,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertCount(1, $parameters);
        $this->assertNull($parameters[0]->style);
        $this->assertNull($parameters[0]->explode);
    }

    #[Test]
    public function it_respects_include_style_config_when_disabled(): void
    {
        config(['spectrum.parameters.include_style' => false]);

        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'ids',
                    'type' => 'array',
                    'required' => false,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertNull($parameters[0]->style);
        $this->assertNull($parameters[0]->explode);
        // items should still be added for arrays
        $this->assertNotNull($parameters[0]->schema->items);
    }

    #[Test]
    public function it_uses_config_for_array_style(): void
    {
        config(['spectrum.parameters.array_style' => 'spaceDelimited']);

        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'ids',
                    'type' => 'array',
                    'required' => false,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertEquals('spaceDelimited', $parameters[0]->style);
    }

    #[Test]
    public function it_uses_config_for_array_explode(): void
    {
        config(['spectrum.parameters.array_explode' => false]);

        $route = ['parameters' => []];
        $controllerInfo = [
            'queryParameters' => [
                [
                    'name' => 'ids',
                    'type' => 'array',
                    'required' => false,
                ],
            ],
        ];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertFalse($parameters[0]->explode);
    }

    #[Test]
    public function it_creates_open_api_parameter_from_query_parameter_info(): void
    {
        $info = new QueryParameterInfo(
            name: 'page',
            type: 'integer',
            required: false,
            default: 1,
            description: 'Page number',
        );

        $param = $this->generator->createFromQueryParameterInfo($info);

        $this->assertInstanceOf(OpenApiParameter::class, $param);
        $this->assertEquals('page', $param->name);
        $this->assertEquals('query', $param->in);
        $this->assertFalse($param->required);
        $this->assertEquals('integer', $param->schema->type);
        $this->assertEquals(1, $param->schema->default);
        $this->assertEquals('Page number', $param->description);
    }

    #[Test]
    public function it_creates_open_api_parameter_with_enum(): void
    {
        $info = new QueryParameterInfo(
            name: 'status',
            type: 'string',
            required: true,
            enum: ['active', 'inactive', 'pending'],
        );

        $param = $this->generator->createFromQueryParameterInfo($info);

        $this->assertEquals(['active', 'inactive', 'pending'], $param->schema->enum);
        $this->assertTrue($param->required);
    }

    #[Test]
    public function it_creates_open_api_parameter_with_array_type(): void
    {
        $info = new QueryParameterInfo(
            name: 'ids',
            type: 'array',
            required: false,
        );

        $param = $this->generator->createFromQueryParameterInfo($info);

        $this->assertEquals('array', $param->schema->type);
        $this->assertNotNull($param->schema->items);
        $this->assertEquals('string', $param->schema->items->type);
        $this->assertEquals('form', $param->style);
        $this->assertTrue($param->explode);
    }

    #[Test]
    public function it_creates_open_api_parameter_with_validation_constraints(): void
    {
        $info = new QueryParameterInfo(
            name: 'limit',
            type: 'integer',
            validationRules: ['integer', 'min:1', 'max:100'],
        );

        $this->mockTypeInference->shouldReceive('getConstraintsFromRules')
            ->once()
            ->with(['integer', 'min:1', 'max:100'])
            ->andReturn(['minimum' => 1, 'maximum' => 100]);

        $param = $this->generator->createFromQueryParameterInfo($info);

        $this->assertEquals(1, $param->schema->minimum);
        $this->assertEquals(100, $param->schema->maximum);
    }

    #[Test]
    public function it_creates_multiple_open_api_parameters_from_infos(): void
    {
        $infos = [
            new QueryParameterInfo(name: 'page', type: 'integer', default: 1),
            new QueryParameterInfo(name: 'search', type: 'string'),
        ];

        $params = $this->generator->createMultipleFromQueryParameterInfos($infos);

        $this->assertCount(2, $params);
        $this->assertInstanceOf(OpenApiParameter::class, $params[0]);
        $this->assertInstanceOf(OpenApiParameter::class, $params[1]);
        $this->assertEquals('page', $params[0]->name);
        $this->assertEquals('search', $params[1]->name);
    }

    #[Test]
    public function it_respects_include_style_config_for_dto_array_parameters(): void
    {
        config(['spectrum.parameters.include_style' => false]);

        $info = new QueryParameterInfo(
            name: 'ids',
            type: 'array',
        );

        $param = $this->generator->createFromQueryParameterInfo($info);

        $this->assertNull($param->style);
        $this->assertNull($param->explode);
        // items should still be added
        $this->assertNotNull($param->schema->items);
    }

    #[Test]
    public function it_creates_open_api_parameter_with_both_default_and_enum(): void
    {
        $info = new QueryParameterInfo(
            name: 'status',
            type: 'string',
            default: 'active',
            enum: ['active', 'inactive', 'pending'],
        );

        $param = $this->generator->createFromQueryParameterInfo($info);

        $this->assertEquals('active', $param->schema->default);
        $this->assertEquals(['active', 'inactive', 'pending'], $param->schema->enum);
    }

    #[Test]
    public function it_handles_validation_rules_with_no_extractable_constraints(): void
    {
        $info = new QueryParameterInfo(
            name: 'field',
            type: 'string',
            validationRules: ['nullable', 'string'],
        );

        $this->mockTypeInference->shouldReceive('getConstraintsFromRules')
            ->once()
            ->with(['nullable', 'string'])
            ->andReturn([]);

        $param = $this->generator->createFromQueryParameterInfo($info);

        $this->assertNull($param->schema->minimum);
        $this->assertNull($param->schema->maximum);
    }
}
