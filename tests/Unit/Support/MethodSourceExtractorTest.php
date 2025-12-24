<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\MethodSourceExtractor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MethodSourceExtractorTest extends TestCase
{
    private MethodSourceExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new MethodSourceExtractor;
    }

    public function test_extract_source_returns_full_method_source(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'simpleMethod');

        $source = $this->extractor->extractSource($method);

        $this->assertStringContainsString('public function simpleMethod()', $source);
        $this->assertStringContainsString('return "hello";', $source);
    }

    public function test_extract_source_includes_method_signature(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'methodWithParams');

        $source = $this->extractor->extractSource($method);

        $this->assertStringContainsString('public function methodWithParams(string $name, int $age)', $source);
        $this->assertStringContainsString('return $name', $source);
    }

    public function test_extract_source_handles_return_type(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'methodWithReturnType');

        $source = $this->extractor->extractSource($method);

        $this->assertStringContainsString('public function methodWithReturnType(): array', $source);
        $this->assertStringContainsString("return ['key' => 'value'];", $source);
    }

    public function test_extract_body_returns_only_method_body(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'simpleMethod');

        $body = $this->extractor->extractBody($method);

        $this->assertStringNotContainsString('public function simpleMethod()', $body);
        $this->assertStringContainsString('return "hello";', $body);
    }

    public function test_extract_body_with_complex_signature(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'methodWithReturnType');

        $body = $this->extractor->extractBody($method);

        $this->assertStringNotContainsString('public function methodWithReturnType()', $body);
        $this->assertStringContainsString("'key' => 'value'", $body);
    }

    public function test_extract_body_with_nullable_return_type(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'methodWithNullableReturn');

        $body = $this->extractor->extractBody($method);

        $this->assertStringNotContainsString('public function methodWithNullableReturn()', $body);
        $this->assertStringContainsString('return null;', $body);
    }

    public function test_extract_body_with_multiline_method(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'multilineMethod');

        $body = $this->extractor->extractBody($method);

        $this->assertStringContainsString('$first = 1;', $body);
        $this->assertStringContainsString('$second = 2;', $body);
        $this->assertStringContainsString('return $first + $second;', $body);
    }

    public function test_extract_source_handles_protected_method(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'protectedMethod');

        $source = $this->extractor->extractSource($method);

        $this->assertStringContainsString('protected function protectedMethod()', $source);
    }

    public function test_extract_source_handles_private_method(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'privateMethod');

        $source = $this->extractor->extractSource($method);

        $this->assertStringContainsString('private function privateMethod()', $source);
    }

    public function test_extract_body_with_union_return_type(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'methodWithUnionReturnType');

        $body = $this->extractor->extractBody($method);

        $this->assertStringNotContainsString('public function methodWithUnionReturnType()', $body);
        $this->assertStringContainsString('return "string or int";', $body);
    }
}

/**
 * Sample class used for testing MethodSourceExtractor.
 */
class SampleClassForExtraction
{
    public function simpleMethod()
    {
        return "hello";
    }

    public function methodWithParams(string $name, int $age)
    {
        return $name . ' is ' . $age;
    }

    public function methodWithReturnType(): array
    {
        return ['key' => 'value'];
    }

    public function methodWithNullableReturn(): ?string
    {
        return null;
    }

    public function multilineMethod(): int
    {
        $first = 1;
        $second = 2;

        return $first + $second;
    }

    protected function protectedMethod(): void
    {
        // protected
    }

    private function privateMethod(): void
    {
        // private
    }

    public function methodWithUnionReturnType(): string|int
    {
        return "string or int";
    }
}
