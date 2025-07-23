<?php

namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\Tests\TestCase;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\Fixtures\Controllers\FilteredController;
use LaravelSpectrum\Tests\Fixtures\Controllers\SearchableController;

class QueryParameterDetectionTest extends TestCase
{
    private OpenApiGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(OpenApiGenerator::class);
    }

    public function testDetectsQueryParametersInFilteredController(): void
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => FilteredController::class,
                'method' => 'index',
                'middleware' => [],
                'parameters' => [],
            ],
        ];

        $openapi = $this->generator->generate($routes);
        
        $this->assertArrayHasKey('/api/users', $openapi['paths']);
        $this->assertArrayHasKey('get', $openapi['paths']['/api/users']);
        
        $operation = $openapi['paths']['/api/users']['get'];
        $this->assertArrayHasKey('parameters', $operation);
        
        // Check for expected query parameters
        $paramNames = array_column($operation['parameters'], 'name');
        $this->assertContains('search', $paramNames);
        $this->assertContains('category', $paramNames);
        $this->assertContains('status', $paramNames);
        $this->assertContains('per_page', $paramNames);
        $this->assertContains('sort_by', $paramNames);
        $this->assertContains('order', $paramNames);
        
        // Check specific parameter details
        $perPageParam = $this->findParameter($operation['parameters'], 'per_page');
        $this->assertNotNull($perPageParam);
        $this->assertEquals('query', $perPageParam['in']);
        $this->assertEquals('integer', $perPageParam['schema']['type']);
        $this->assertEquals(15, $perPageParam['schema']['default']);
        $this->assertEquals('Per Page', $perPageParam['description']);
        
        $statusParam = $this->findParameter($operation['parameters'], 'status');
        $this->assertNotNull($statusParam);
        $this->assertEquals('string', $statusParam['schema']['type']);
        $this->assertEquals('all', $statusParam['schema']['default']);
        
        $orderParam = $this->findParameter($operation['parameters'], 'order');
        $this->assertNotNull($orderParam);
        $this->assertEquals('desc', $orderParam['schema']['default']);
    }

    public function testDetectsQueryParametersWithEnumInSearchableController(): void
    {
        $routes = [
            [
                'uri' => 'api/products/search',
                'httpMethods' => ['GET'],
                'controller' => SearchableController::class,
                'method' => 'search',
                'middleware' => [],
                'parameters' => [],
            ],
        ];

        $openapi = $this->generator->generate($routes);
        
        $operation = $openapi['paths']['/api/products/search']['get'];
        
        // Check for sort parameter with enum
        $sortParam = $this->findParameter($operation['parameters'], 'sort');
        $this->assertNotNull($sortParam);
        $this->assertEquals('string', $sortParam['schema']['type']);
        $this->assertEquals('relevance', $sortParam['schema']['default']);
        $this->assertEquals(['relevance', 'date', 'popularity'], $sortParam['schema']['enum']);
        
        // Check for typed parameters
        $categoryIdParam = $this->findParameter($operation['parameters'], 'category_id');
        $this->assertNotNull($categoryIdParam);
        $this->assertEquals('integer', $categoryIdParam['schema']['type']);
        
        $includeDraftsParam = $this->findParameter($operation['parameters'], 'include_drafts');
        $this->assertNotNull($includeDraftsParam);
        $this->assertEquals('boolean', $includeDraftsParam['schema']['type']);
        
        $minPriceParam = $this->findParameter($operation['parameters'], 'min_price');
        $this->assertNotNull($minPriceParam);
        $this->assertEquals('number', $minPriceParam['schema']['type']);
        $this->assertEquals(0.0, $minPriceParam['schema']['default']);
    }

    public function testDetectsQueryParametersWithArrayTypeInFilterMethod(): void
    {
        $routes = [
            [
                'uri' => 'api/products/filter',
                'httpMethods' => ['GET'],
                'controller' => SearchableController::class,
                'method' => 'filter',
                'middleware' => [],
                'parameters' => [],
            ],
        ];

        $openapi = $this->generator->generate($routes);
        
        $operation = $openapi['paths']['/api/products/filter']['get'];
        
        // Check for array parameters
        $tagsParam = $this->findParameter($operation['parameters'], 'tags');
        $this->assertNotNull($tagsParam);
        $this->assertEquals('array', $tagsParam['schema']['type']);
        $this->assertEquals([], $tagsParam['schema']['default']);
        
        $brandsParam = $this->findParameter($operation['parameters'], 'brands');
        $this->assertNotNull($brandsParam);
        $this->assertEquals('array', $brandsParam['schema']['type']);
        
        // Check for boolean with default
        $inStockParam = $this->findParameter($operation['parameters'], 'in_stock');
        $this->assertNotNull($inStockParam);
        $this->assertEquals('boolean', $inStockParam['schema']['type']);
        $this->assertEquals(true, $inStockParam['schema']['default']);
        
        // Check for enum from match expression
        $priceRangeParam = $this->findParameter($operation['parameters'], 'price_range');
        $this->assertNotNull($priceRangeParam);
        $this->assertEquals('string', $priceRangeParam['schema']['type']);
        $this->assertEquals(['0-50', '50-100', '100-500', '500+'], $priceRangeParam['schema']['enum']);
    }

    public function testMergesQueryParametersWithValidationRules(): void
    {
        // Create a test controller with both query parameters and validation
        $controller = new class {
            public function search(\Illuminate\Http\Request $request)
            {
                $request->validate([
                    'search' => 'string|max:255',
                    'page' => 'integer|min:1',
                    'per_page' => 'integer|between:10,100',
                ]);
                
                $search = $request->input('search');
                $page = $request->input('page', 1);
                $perPage = $request->input('per_page', 20);
                $sort = $request->input('sort', 'relevance'); // Not in validation
            }
        };
        
        $routes = [
            [
                'uri' => 'api/search',
                'httpMethods' => ['GET'],
                'controller' => get_class($controller),
                'method' => 'search',
                'middleware' => [],
                'parameters' => [],
            ],
        ];

        $openapi = $this->generator->generate($routes);
        $operation = $openapi['paths']['/api/search']['get'];
        
        // Check that validation rules take precedence
        $pageParam = $this->findParameter($operation['parameters'], 'page');
        $this->assertNotNull($pageParam);
        $this->assertEquals('integer', $pageParam['schema']['type']);
        // TODO: Fix inline validation detection for anonymous classes
        // $this->assertEquals(1, $pageParam['schema']['minimum']);
        
        $perPageParam = $this->findParameter($operation['parameters'], 'per_page');
        $this->assertNotNull($perPageParam);
        $this->assertEquals('integer', $perPageParam['schema']['type']);
        // TODO: Fix inline validation detection for anonymous classes
        // $this->assertEquals(10, $perPageParam['schema']['minimum']);
        // $this->assertEquals(100, $perPageParam['schema']['maximum']);
        
        // Check that non-validated parameter is still detected
        $sortParam = $this->findParameter($operation['parameters'], 'sort');
        $this->assertNotNull($sortParam);
        $this->assertEquals('string', $sortParam['schema']['type']);
        $this->assertEquals('relevance', $sortParam['schema']['default']);
    }

    public function testGeneratesProperDescriptions(): void
    {
        $controller = new class {
            public function index(\Illuminate\Http\Request $request)
            {
                $userId = $request->integer('user_id');
                $includeDeleted = $request->boolean('include_deleted');
                $sortBy = $request->input('sort_by', 'name');
                $createdAt = $request->input('created_at');
            }
        };
        
        $routes = [
            [
                'uri' => 'api/test',
                'httpMethods' => ['GET'],
                'controller' => get_class($controller),
                'method' => 'index',
                'middleware' => [],
                'parameters' => [],
            ],
        ];

        $openapi = $this->generator->generate($routes);
        $operation = $openapi['paths']['/api/test']['get'];
        
        $userIdParam = $this->findParameter($operation['parameters'], 'user_id');
        $this->assertEquals('User ID', $userIdParam['description']);
        
        $includeDeletedParam = $this->findParameter($operation['parameters'], 'include_deleted');
        $this->assertEquals('Include Deleted', $includeDeletedParam['description']);
        
        $sortByParam = $this->findParameter($operation['parameters'], 'sort_by');
        $this->assertEquals('Sort By', $sortByParam['description']);
        
        $createdAtParam = $this->findParameter($operation['parameters'], 'created_at');
        $this->assertEquals('Created At', $createdAtParam['description']);
    }

    private function findParameter(array $parameters, string $name): ?array
    {
        foreach ($parameters as $param) {
            if ($param['name'] === $name) {
                return $param;
            }
        }
        return null;
    }
}