<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\PaginationAnalyzer;
use LaravelSpectrum\DTO\PaginationInfo;
use LaravelSpectrum\Support\PaginationDetector;
use LaravelSpectrum\Tests\Fixtures\Controllers\PaginatedController;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PaginationAnalyzerTest extends TestCase
{
    private PaginationAnalyzer $analyzer;

    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->analyzer = new PaginationAnalyzer($this->parser, new PaginationDetector);
    }

    public function test_detects_basic_paginate_method(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'lengthAwarePagination');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
        $this->assertEquals('User', $result->model);
        $this->assertNull($result->resource);
    }

    public function test_detects_simple_paginate_method(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'simplePagination');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('simplePaginate', $result->type);
        $this->assertEquals('Post', $result->model);
    }

    public function test_detects_cursor_paginate_method(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'cursorPagination');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('cursorPaginate', $result->type);
        $this->assertEquals('Comment', $result->model);
    }

    public function test_detects_pagination_with_resource(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withResource');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
        $this->assertEquals('User', $result->model);
        $this->assertEquals('UserResource', $result->resource);
    }

    public function test_detects_pagination_with_query_builder(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withQueryBuilder');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
        $this->assertEquals('User', $result->model);
    }

    public function test_detects_pagination_with_custom_per_page(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withCustomPerPage');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
        $this->assertEquals('User', $result->model);
    }

    public function test_detects_pagination_from_return_type(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withReturnType');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
    }

    public function test_detects_simple_paginator_return_type(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withSimplePaginatorReturnType');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('simplePaginate', $result->type);
    }

    public function test_detects_cursor_paginator_return_type(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'withCursorPaginatorReturnType');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('cursorPaginate', $result->type);
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

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
        $this->assertEquals('User', $result->model);
    }

    public function test_detects_relation_pagination(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'relationPagination');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
        $this->assertEquals('posts', $result->model);
    }

    #[Test]
    public function it_returns_null_for_internal_method(): void
    {
        // Internal PHP methods have no file
        $method = new ReflectionMethod(\DateTime::class, 'format');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_no_return_type(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'lengthAwarePagination');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_unknown_return_type(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'nonPaginated');
        $result = $this->analyzer->analyzeReturnType($method);

        // nonPaginated has no return type declared
        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_nullable_return_type(): void
    {
        $method = new ReflectionMethod(PaginationReturnTypeFixture::class, 'nullableLengthAware');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
    }

    #[Test]
    public function it_handles_concrete_length_aware_paginator(): void
    {
        $method = new ReflectionMethod(PaginationReturnTypeFixture::class, 'concreteLengthAware');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
    }

    #[Test]
    public function it_handles_concrete_simple_paginator(): void
    {
        $method = new ReflectionMethod(PaginationReturnTypeFixture::class, 'concreteSimple');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('simplePaginate', $result->type);
    }

    #[Test]
    public function it_handles_concrete_cursor_paginator(): void
    {
        $method = new ReflectionMethod(PaginationReturnTypeFixture::class, 'concreteCursor');
        $result = $this->analyzer->analyzeReturnType($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('cursorPaginate', $result->type);
    }

    #[Test]
    public function it_handles_parser_error(): void
    {
        // Create analyzer with a mock parser that throws an error
        $mockParser = $this->createMock(Parser::class);
        $mockParser->method('parse')->willThrowException(new \PhpParser\Error('Parse error'));

        $analyzer = new PaginationAnalyzer($mockParser, new PaginationDetector);
        $method = new ReflectionMethod(PaginatedController::class, 'lengthAwarePagination');

        $result = $analyzer->analyzeMethod($method);
        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_parser_returning_null(): void
    {
        $mockParser = $this->createMock(Parser::class);
        $mockParser->method('parse')->willReturn(null);

        $analyzer = new PaginationAnalyzer($mockParser, new PaginationDetector);
        $method = new ReflectionMethod(PaginatedController::class, 'lengthAwarePagination');

        $result = $analyzer->analyzeMethod($method);
        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_empty_pagination_result(): void
    {
        // A method with no pagination calls
        $mockDetector = $this->createMock(PaginationDetector::class);
        $mockDetector->method('detectPaginationCalls')->willReturn([]);

        $analyzer = new PaginationAnalyzer($this->parser, $mockDetector);
        $method = new ReflectionMethod(PaginatedController::class, 'nonPaginated');

        $result = $analyzer->analyzeMethod($method);
        $this->assertNull($result);
    }

    #[Test]
    public function it_detects_custom_pagination_response(): void
    {
        $method = new ReflectionMethod(PaginatedController::class, 'customPaginationResponse');
        $result = $this->analyzer->analyzeMethod($method);

        $this->assertInstanceOf(PaginationInfo::class, $result);
        $this->assertEquals('paginate', $result->type);
        $this->assertEquals('User', $result->model);
    }
}

/**
 * Fixture class for testing various return type scenarios
 */
class PaginationReturnTypeFixture
{
    public function nullableLengthAware(): ?\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return null;
    }

    public function concreteLengthAware(): \Illuminate\Pagination\LengthAwarePaginator
    {
        throw new \RuntimeException('Not implemented');
    }

    public function concreteSimple(): \Illuminate\Pagination\Paginator
    {
        throw new \RuntimeException('Not implemented');
    }

    public function concreteCursor(): \Illuminate\Pagination\CursorPaginator
    {
        throw new \RuntimeException('Not implemented');
    }
}
