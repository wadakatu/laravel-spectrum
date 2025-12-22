<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

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
        $this->assertEquals('id', $parameters[0]['name']);
    }

    #[Test]
    public function it_adds_enum_info_to_existing_route_parameter(): void
    {
        $route = [
            'parameters' => [
                ['name' => 'status', 'in' => 'path', 'required' => true],
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
        $this->assertEquals('status', $parameters[0]['name']);
        $this->assertEquals(['active', 'inactive', 'pending'], $parameters[0]['schema']['enum']);
        $this->assertEquals('User status', $parameters[0]['description']);
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
        $this->assertEquals('sort', $parameters[0]['name']);
        $this->assertEquals('query', $parameters[0]['in']);
        $this->assertFalse($parameters[0]['required']);
        $this->assertEquals(['asc', 'desc'], $parameters[0]['schema']['enum']);
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
        $this->assertEquals('page', $parameters[0]['name']);
        $this->assertEquals('query', $parameters[0]['in']);
        $this->assertEquals('integer', $parameters[0]['schema']['type']);
        $this->assertEquals(1, $parameters[0]['schema']['default']);
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
        $this->assertEquals('Search term', $parameters[0]['description']);
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
        $this->assertEquals(['name', 'date', 'price'], $parameters[0]['schema']['enum']);
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
        $this->assertEquals(1, $parameters[0]['schema']['minimum']);
        $this->assertEquals(100, $parameters[0]['schema']['maximum']);
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
        $this->assertEquals('id', $parameters[0]['name']);
        $this->assertEquals('path', $parameters[0]['in']);

        // Enum as query parameter
        $this->assertEquals('status', $parameters[1]['name']);
        $this->assertEquals('query', $parameters[1]['in']);

        // Query parameter
        $this->assertEquals('page', $parameters[2]['name']);
        $this->assertEquals('query', $parameters[2]['in']);
    }

    // extractConstraintsFromRules tests

    #[Test]
    public function it_extracts_minimum_constraint(): void
    {
        $rules = ['min:5'];

        $constraints = $this->generator->extractConstraintsFromRules($rules);

        $this->assertEquals(['minimum' => 5], $constraints);
    }

    #[Test]
    public function it_extracts_maximum_constraint(): void
    {
        $rules = ['max:100'];

        $constraints = $this->generator->extractConstraintsFromRules($rules);

        $this->assertEquals(['maximum' => 100], $constraints);
    }

    #[Test]
    public function it_extracts_between_constraints(): void
    {
        $rules = ['between:1,50'];

        $constraints = $this->generator->extractConstraintsFromRules($rules);

        $this->assertEquals(['minimum' => 1, 'maximum' => 50], $constraints);
    }

    #[Test]
    public function it_extracts_multiple_constraints(): void
    {
        $rules = ['integer', 'min:1', 'max:100'];

        $constraints = $this->generator->extractConstraintsFromRules($rules);

        $this->assertEquals(['minimum' => 1, 'maximum' => 100], $constraints);
    }

    #[Test]
    public function it_ignores_non_string_rules(): void
    {
        $rules = [new \stdClass, 'min:5'];

        $constraints = $this->generator->extractConstraintsFromRules($rules);

        $this->assertEquals(['minimum' => 5], $constraints);
    }

    #[Test]
    public function it_returns_empty_for_rules_without_constraints(): void
    {
        $rules = ['required', 'string', 'email'];

        $constraints = $this->generator->extractConstraintsFromRules($rules);

        $this->assertEquals([], $constraints);
    }

    #[Test]
    public function it_handles_empty_parameters(): void
    {
        $route = ['parameters' => []];
        $controllerInfo = [];

        $parameters = $this->generator->generate($route, $controllerInfo);

        $this->assertEquals([], $parameters);
    }
}
