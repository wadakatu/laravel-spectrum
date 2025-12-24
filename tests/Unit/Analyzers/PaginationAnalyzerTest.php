<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\PaginationAnalyzer;
use LaravelSpectrum\Support\PaginationDetector;
use LaravelSpectrum\Tests\Fixtures\Controllers\PaginatedController;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PaginationAnalyzerTest extends TestCase
{
    private PaginationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->analyzer = new PaginationAnalyzer($parser, new PaginationDetector);
    }

    public function test_detects_basic_paginate_method(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'lengthAwarePagination');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNotNull($result);
        $this->assertEquals('paginate', $result['type']);
        $this->assertEquals('User', $result['model']);
        $this->assertNull($result['resource'] ?? null);
    }

    public function test_detects_simple_paginate_method(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'simplePagination');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNotNull($result);
        $this->assertEquals('simplePaginate', $result['type']);
        $this->assertEquals('Post', $result['model']);
    }

    public function test_detects_cursor_paginate_method(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'cursorPagination');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNotNull($result);
        $this->assertEquals('cursorPaginate', $result['type']);
        $this->assertEquals('Comment', $result['model']);
    }

    public function test_detects_pagination_with_resource(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withResource');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNotNull($result);
        $this->assertEquals('paginate', $result['type']);
        $this->assertEquals('User', $result['model']);
        $this->assertEquals('UserResource', $result['resource']);
    }

    public function test_detects_pagination_with_query_builder(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withQueryBuilder');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNotNull($result);
        $this->assertEquals('paginate', $result['type']);
        $this->assertEquals('User', $result['model']);
    }

    public function test_detects_pagination_with_custom_per_page(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withCustomPerPage');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNotNull($result);
        $this->assertEquals('paginate', $result['type']);
        $this->assertEquals('User', $result['model']);
    }

    public function test_detects_pagination_from_return_type(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withReturnType');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertNotNull($result);
        $this->assertEquals('paginate', $result['type']);
    }

    public function test_detects_simple_paginator_return_type(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withSimplePaginatorReturnType');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertNotNull($result);
        $this->assertEquals('simplePaginate', $result['type']);
    }

    public function test_detects_cursor_paginator_return_type(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withCursorPaginatorReturnType');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertNotNull($result);
        $this->assertEquals('cursorPaginate', $result['type']);
    }

    public function test_returns_null_for_non_paginated_methods(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'nonPaginated');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNull($result);
    }

    public function test_detects_conditional_pagination(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'conditionalPagination');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNotNull($result);
        $this->assertEquals('paginate', $result['type']);
        $this->assertEquals('User', $result['model']);
    }

    public function test_detects_relation_pagination(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'relationPagination');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNotNull($result);
        $this->assertEquals('paginate', $result['type']);
        $this->assertEquals('posts', $result['model']);
    }
}
