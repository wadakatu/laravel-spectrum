<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\HeaderParameterDetector;
use LaravelSpectrum\Tests\Fixtures\Controllers\HeaderTestController;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

class HeaderParameterDetectorTest extends TestCase
{
    private HeaderParameterDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->detector = new HeaderParameterDetector($parser);
    }

    #[Test]
    public function it_detects_single_header_usage(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withSingleHeader');
        $ast = $this->detector->parseMethod($method);
        $detected = $this->detector->detectHeaderCalls($ast);

        $this->assertCount(1, $detected);
        $this->assertEquals('Idempotency-Key', $detected[0]->name);
        $this->assertEquals('header', $detected[0]->method);
        $this->assertNull($detected[0]->default);
    }

    #[Test]
    public function it_detects_header_with_default_value(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withHeaderDefault');
        $ast = $this->detector->parseMethod($method);
        $detected = $this->detector->detectHeaderCalls($ast);

        $this->assertCount(1, $detected);
        $this->assertEquals('X-Request-Id', $detected[0]->name);
        $this->assertEquals('header', $detected[0]->method);
        $this->assertEquals('generated-id', $detected[0]->default);
    }

    #[Test]
    public function it_detects_has_header_check(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withHasHeader');
        $ast = $this->detector->parseMethod($method);
        $detected = $this->detector->detectHeaderCalls($ast);

        $this->assertCount(1, $detected);
        $this->assertEquals('X-Custom-Auth', $detected[0]->name);
        $this->assertEquals('hasHeader', $detected[0]->method);
        $this->assertTrue($detected[0]->isHasHeaderCheck());
    }

    #[Test]
    public function it_detects_bearer_token(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withBearerToken');
        $ast = $this->detector->parseMethod($method);
        $detected = $this->detector->detectHeaderCalls($ast);

        $this->assertCount(1, $detected);
        $this->assertEquals('Authorization', $detected[0]->name);
        $this->assertEquals('bearerToken', $detected[0]->method);
        $this->assertTrue($detected[0]->isBearerToken());
    }

    #[Test]
    public function it_detects_multiple_headers(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withMultipleHeaders');
        $ast = $this->detector->parseMethod($method);
        $detected = $this->detector->detectHeaderCalls($ast);

        $this->assertCount(3, $detected);

        $names = array_map(fn ($d) => $d->name, $detected);
        $this->assertContains('X-Tenant-Id', $names);
        $this->assertContains('X-Correlation-Id', $names);
        $this->assertContains('Accept-Language', $names);
    }

    #[Test]
    public function it_does_not_detect_query_parameters(): void
    {
        // This test ensures header detector only detects headers, not query params
        $method = new ReflectionMethod(HeaderTestController::class, 'withHeaderAndQuery');
        $ast = $this->detector->parseMethod($method);
        $detected = $this->detector->detectHeaderCalls($ast);

        // Should only detect X-Api-Key header, not user_id input
        $this->assertCount(1, $detected);
        $this->assertEquals('X-Api-Key', $detected[0]->name);
    }

    #[Test]
    public function it_returns_empty_array_when_ast_is_null(): void
    {
        $detected = $this->detector->detectHeaderCalls(null);

        $this->assertIsArray($detected);
        $this->assertEmpty($detected);
    }

    #[Test]
    public function it_returns_empty_array_for_method_without_headers(): void
    {
        $method = new ReflectionMethod(HeaderTestController::class, 'withNoHeaders');
        $ast = $this->detector->parseMethod($method);
        $detected = $this->detector->detectHeaderCalls($ast);

        $this->assertIsArray($detected);
        $this->assertEmpty($detected);
    }
}
