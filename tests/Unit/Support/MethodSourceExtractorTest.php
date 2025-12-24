<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\MethodSourceExtractor;
use LaravelSpectrum\Tests\TestCase;
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
        $this->assertStringContainsString("return 'hello';", $source);
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
        $this->assertStringContainsString("return 'hello';", $body);
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
        $this->assertStringContainsString("return 'string or int';", $body);
    }

    public function test_extract_source_returns_empty_for_internal_method(): void
    {
        // Test with a PHP internal method (no source file)
        $method = new ReflectionMethod(\DateTime::class, 'format');

        $source = $this->extractor->extractSource($method);

        $this->assertSame('', $source);
    }

    public function test_extract_body_returns_empty_for_internal_method(): void
    {
        // Test with a PHP internal method (no source file)
        $method = new ReflectionMethod(\ArrayObject::class, 'count');

        $body = $this->extractor->extractBody($method);

        $this->assertSame('', $body);
    }

    public function test_extract_source_handles_static_method(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'staticMethod');

        $source = $this->extractor->extractSource($method);

        $this->assertStringContainsString('public static function staticMethod()', $source);
        $this->assertStringContainsString("return 'static';", $source);
    }

    public function test_extract_body_handles_static_method(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'staticMethod');

        $body = $this->extractor->extractBody($method);

        $this->assertStringNotContainsString('public static function staticMethod()', $body);
        $this->assertStringContainsString("return 'static';", $body);
    }

    public function test_extract_source_handles_empty_body_method(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'emptyMethod');

        $source = $this->extractor->extractSource($method);

        $this->assertStringContainsString('public function emptyMethod(): void', $source);
    }

    public function test_extract_body_handles_empty_body_method(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'emptyMethod');

        $body = $this->extractor->extractBody($method);

        // Empty body should just contain whitespace or be empty
        $this->assertStringNotContainsString('public function emptyMethod()', $body);
    }

    public function test_extract_body_handles_method_with_default_parameter_values(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'methodWithDefaultParams');

        $body = $this->extractor->extractBody($method);

        $this->assertStringNotContainsString('public function methodWithDefaultParams', $body);
        $this->assertStringContainsString('return $name', $body);
    }

    public function test_extract_source_handles_method_with_default_parameter_values(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'methodWithDefaultParams');

        $source = $this->extractor->extractSource($method);

        $this->assertStringContainsString("'default'", $source);
        $this->assertStringContainsString('= 0', $source);
    }

    public function test_extract_body_handles_method_with_typed_namespace_parameter(): void
    {
        $method = new ReflectionMethod(SampleClassForExtraction::class, 'methodWithTypedParam');

        $body = $this->extractor->extractBody($method);

        $this->assertStringNotContainsString('public function methodWithTypedParam', $body);
        $this->assertStringContainsString('return $object', $body);
    }
}

/**
 * Sample class used for testing MethodSourceExtractor.
 */
class SampleClassForExtraction
{
    public function simpleMethod()
    {
        return 'hello';
    }

    public function methodWithParams(string $name, int $age)
    {
        return $name.' is '.$age;
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
        return 'string or int';
    }

    public static function staticMethod(): string
    {
        return 'static';
    }

    public function emptyMethod(): void {}

    public function methodWithDefaultParams(string $name = 'default', int $count = 0): string
    {
        return $name.': '.$count;
    }

    public function methodWithTypedParam(\stdClass $object): mixed
    {
        return $object;
    }
}
