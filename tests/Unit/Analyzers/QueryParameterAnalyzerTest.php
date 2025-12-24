<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\QueryParameterAnalyzer;
use LaravelSpectrum\Support\QueryParameterDetector;
use LaravelSpectrum\Support\QueryParameterTypeInference;
use LaravelSpectrum\Tests\Fixtures\Controllers\FilteredController;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class QueryParameterAnalyzerTest extends TestCase
{
    private QueryParameterAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->analyzer = new QueryParameterAnalyzer(
            new QueryParameterDetector($parser),
            new QueryParameterTypeInference
        );
    }

    public function test_detects_basic_input_method(): void
    {
        $controller = new class
        {
            public function index(\Illuminate\Http\Request $request)
            {
                $search = $request->input('search');
                $page = $request->input('page', 1);
            }
        };

        $method = new \ReflectionMethod($controller, 'index');
        $result = $this->analyzer->analyze($method);

        $this->assertCount(2, $result['parameters']);

        $this->assertEquals('search', $result['parameters'][0]['name']);
        $this->assertEquals('string', $result['parameters'][0]['type']);
        $this->assertFalse($result['parameters'][0]['required']);
        $this->assertNull($result['parameters'][0]['default']);

        $this->assertEquals('page', $result['parameters'][1]['name']);
        $this->assertEquals('integer', $result['parameters'][1]['type']);
        $this->assertFalse($result['parameters'][1]['required']);
        $this->assertEquals(1, $result['parameters'][1]['default']);
    }

    public function test_detects_query_method(): void
    {
        $controller = new class
        {
            public function filter(\Illuminate\Http\Request $request)
            {
                $status = $request->query('status', 'all');
                $category = $request->query('category');
            }
        };

        $method = new \ReflectionMethod($controller, 'filter');
        $result = $this->analyzer->analyze($method);

        $this->assertCount(2, $result['parameters']);

        $this->assertEquals('status', $result['parameters'][0]['name']);
        $this->assertEquals('string', $result['parameters'][0]['type']);
        $this->assertEquals('all', $result['parameters'][0]['default']);

        $this->assertEquals('category', $result['parameters'][1]['name']);
        $this->assertEquals('string', $result['parameters'][1]['type']);
    }

    public function test_detects_typed_methods(): void
    {
        $controller = new class
        {
            public function settings(\Illuminate\Http\Request $request)
            {
                $showAll = $request->boolean('show_all');
                $limit = $request->integer('limit', 100);
                $threshold = $request->float('threshold', 0.5);
            }
        };

        $method = new \ReflectionMethod($controller, 'settings');
        $result = $this->analyzer->analyze($method);

        $this->assertCount(3, $result['parameters']);

        $this->assertEquals('show_all', $result['parameters'][0]['name']);
        $this->assertEquals('boolean', $result['parameters'][0]['type']);

        $this->assertEquals('limit', $result['parameters'][1]['name']);
        $this->assertEquals('integer', $result['parameters'][1]['type']);
        $this->assertEquals(100, $result['parameters'][1]['default']);

        $this->assertEquals('threshold', $result['parameters'][2]['name']);
        $this->assertEquals('number', $result['parameters'][2]['type']);
        $this->assertEquals(0.5, $result['parameters'][2]['default']);
    }

    public function test_detects_has_and_filled_methods(): void
    {
        $controller = new class
        {
            public function check(\Illuminate\Http\Request $request)
            {
                if ($request->has('required_param')) {
                    $value = $request->input('required_param');
                }

                if ($request->filled('optional_param')) {
                    $value2 = $request->get('optional_param');
                }
            }
        };

        $method = new \ReflectionMethod($controller, 'check');
        $result = $this->analyzer->analyze($method);

        $this->assertCount(2, $result['parameters']);

        $this->assertEquals('required_param', $result['parameters'][0]['name']);
        $this->assertTrue($result['parameters'][0]['context']['has_check']);

        $this->assertEquals('optional_param', $result['parameters'][1]['name']);
        $this->assertTrue($result['parameters'][1]['context']['filled_check']);
    }

    public function test_detects_magic_access(): void
    {
        $controller = new class
        {
            public function magic(\Illuminate\Http\Request $request)
            {
                $userId = $request->user_id;
                $sortBy = $request->sort_by ?? 'created_at';
            }
        };

        $method = new \ReflectionMethod($controller, 'magic');
        $result = $this->analyzer->analyze($method);

        $this->assertCount(2, $result['parameters']);

        $this->assertEquals('user_id', $result['parameters'][0]['name']);
        $this->assertEquals('string', $result['parameters'][0]['type']);

        $this->assertEquals('sort_by', $result['parameters'][1]['name']);
        $this->assertEquals('string', $result['parameters'][1]['type']);
        $this->assertEquals('created_at', $result['parameters'][1]['default']);
    }

    public function test_merge_with_validation(): void
    {
        $queryParams = [
            'parameters' => [
                [
                    'name' => 'search',
                    'type' => 'string',
                    'required' => false,
                    'default' => null,
                ],
                [
                    'name' => 'page',
                    'type' => 'string', // Will be overridden by validation
                    'required' => false,
                    'default' => 1,
                ],
            ],
        ];

        $validationRules = [
            'search' => ['string', 'max:255'],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'between:10,100'], // Additional param from validation
        ];

        $result = $this->analyzer->mergeWithValidation($queryParams, $validationRules);

        $this->assertCount(3, $result['parameters']);

        // Check that validation rules take precedence
        $pageParam = collect($result['parameters'])->firstWhere('name', 'page');
        $this->assertEquals('integer', $pageParam['type']);
        $this->assertEquals(['integer', 'min:1'], $pageParam['validation_rules']);

        // Check that new parameter from validation is added
        $perPageParam = collect($result['parameters'])->firstWhere('name', 'per_page');
        $this->assertNotNull($perPageParam);
        $this->assertEquals('integer', $perPageParam['type']);
    }

    public function test_detects_enum_from_in_array(): void
    {
        $controller = new class
        {
            public function sort(\Illuminate\Http\Request $request)
            {
                $sort = $request->input('sort', 'relevance');
                if (! in_array($sort, ['relevance', 'date', 'popularity'])) {
                    $sort = 'relevance';
                }
            }
        };

        $method = new \ReflectionMethod($controller, 'sort');
        $result = $this->analyzer->analyze($method);

        $this->assertCount(1, $result['parameters']);
        $this->assertEquals('sort', $result['parameters'][0]['name']);
        $this->assertEquals('relevance', $result['parameters'][0]['default']);
        $this->assertEquals(['relevance', 'date', 'popularity'], $result['parameters'][0]['enum']);
    }

    public function test_detects_array_type_from_context(): void
    {
        $controller = new class
        {
            public function bulk(\Illuminate\Http\Request $request)
            {
                $ids = $request->input('ids', []);
                foreach ($ids as $id) {
                    // Process each ID
                }
            }
        };

        $method = new \ReflectionMethod($controller, 'bulk');
        $result = $this->analyzer->analyze($method);

        $this->assertCount(1, $result['parameters']);
        $this->assertEquals('ids', $result['parameters'][0]['name']);
        $this->assertEquals('array', $result['parameters'][0]['type']);
        $this->assertEquals([], $result['parameters'][0]['default']);
    }

    public function test_handles_static_request_calls(): void
    {
        $controller = new class
        {
            public function static()
            {
                $search = \Illuminate\Http\Request::input('search');
                $category = \Request::get('category', 'all');
            }
        };

        $method = new \ReflectionMethod($controller, 'static');
        $result = $this->analyzer->analyze($method);

        $this->assertCount(2, $result['parameters']);

        $this->assertEquals('search', $result['parameters'][0]['name']);
        $this->assertEquals('string', $result['parameters'][0]['type']);

        $this->assertEquals('category', $result['parameters'][1]['name']);
        $this->assertEquals('all', $result['parameters'][1]['default']);
    }

    public function test_handles_complex_usage_patterns(): void
    {
        $method = new \ReflectionMethod(FilteredController::class, 'index');
        $result = $this->analyzer->analyze($method);

        $expectedParams = ['search', 'category', 'status', 'per_page', 'sort_by', 'order'];
        $actualParams = array_column($result['parameters'], 'name');

        foreach ($expectedParams as $param) {
            $this->assertContains($param, $actualParams);
        }

        // Check specific parameter details
        $perPage = collect($result['parameters'])->firstWhere('name', 'per_page');
        $this->assertEquals('integer', $perPage['type']);
        $this->assertEquals(15, $perPage['default']);

        $order = collect($result['parameters'])->firstWhere('name', 'order');
        $this->assertEquals('desc', $order['default']);
    }

    public function test_generates_descriptions_from_parameter_names(): void
    {
        $controller = new class
        {
            public function descriptive(\Illuminate\Http\Request $request)
            {
                $user_id = $request->integer('user_id');
                $sort_by = $request->input('sort_by', 'name');
                $include_deleted = $request->boolean('include_deleted');
            }
        };

        $method = new \ReflectionMethod($controller, 'descriptive');
        $result = $this->analyzer->analyze($method);

        $userId = collect($result['parameters'])->firstWhere('name', 'user_id');
        $this->assertEquals('User ID', $userId['description']);

        $sortBy = collect($result['parameters'])->firstWhere('name', 'sort_by');
        $this->assertEquals('Sort By', $sortBy['description']);

        $includeDeleted = collect($result['parameters'])->firstWhere('name', 'include_deleted');
        $this->assertEquals('Include Deleted', $includeDeleted['description']);
    }
}
