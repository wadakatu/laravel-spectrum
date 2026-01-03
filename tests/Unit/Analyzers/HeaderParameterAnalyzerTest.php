<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\HeaderParameterAnalyzer;
use LaravelSpectrum\DTO\HeaderParameterInfo;
use LaravelSpectrum\Tests\Fixtures\Controllers\HeaderTestController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

class HeaderParameterAnalyzerTest extends TestCase
{
    private HeaderParameterAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = $this->app->make(HeaderParameterAnalyzer::class);
    }

    #[Test]
    public function it_returns_empty_array_for_method_without_headers(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withHeaderAndQuery');

        // This method has both header and query parameter
        // Should return only headers
        $result = $this->analyzer->analyzeToResult($method);

        $this->assertCount(1, $result);
        $this->assertEquals('X-Api-Key', $result[0]->name);
    }

    #[Test]
    public function it_returns_header_parameter_info_for_single_header(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withSingleHeader');

        $result = $this->analyzer->analyzeToResult($method);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(HeaderParameterInfo::class, $result[0]);
        $this->assertEquals('Idempotency-Key', $result[0]->name);
        $this->assertEquals('string', $result[0]->type);
        $this->assertFalse($result[0]->required);
    }

    #[Test]
    public function it_marks_header_as_not_required_when_has_default(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withHeaderDefault');

        $result = $this->analyzer->analyzeToResult($method);

        $this->assertCount(1, $result);
        $this->assertEquals('X-Request-Id', $result[0]->name);
        $this->assertFalse($result[0]->required);
        $this->assertEquals('generated-id', $result[0]->default);
    }

    #[Test]
    public function it_detects_has_header_check(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withHasHeader');

        $result = $this->analyzer->analyzeToResult($method);

        $this->assertCount(1, $result);
        $this->assertEquals('X-Custom-Auth', $result[0]->name);
        $this->assertFalse($result[0]->required);
    }

    #[Test]
    public function it_detects_bearer_token(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withBearerToken');

        $result = $this->analyzer->analyzeToResult($method);

        $this->assertCount(1, $result);
        $this->assertEquals('Authorization', $result[0]->name);
        $this->assertTrue($result[0]->isBearerToken);
    }

    #[Test]
    public function it_detects_multiple_headers(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withMultipleHeaders');

        $result = $this->analyzer->analyzeToResult($method);

        $this->assertCount(3, $result);

        $names = array_map(fn (HeaderParameterInfo $h) => $h->name, $result);
        $this->assertContains('X-Tenant-Id', $names);
        $this->assertContains('X-Correlation-Id', $names);
        $this->assertContains('Accept-Language', $names);
    }

    #[Test]
    public function it_returns_array_format_with_analyze_method(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withSingleHeader');

        $result = $this->analyzer->analyze($method);

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]);
        $this->assertEquals('Idempotency-Key', $result[0]['name']);
        $this->assertEquals('header', $result[0]['in']);
    }
}
