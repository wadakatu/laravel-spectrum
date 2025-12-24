<?php

namespace Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\Support\AstNodeValueExtractor;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AstNodeValueExtractorTest extends TestCase
{
    private AstNodeValueExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new AstNodeValueExtractor;
    }

    #[Test]
    public function it_extracts_string_value(): void
    {
        $node = new String_('hello');

        $this->assertSame('hello', $this->extractor->extractValue($node));
        $this->assertSame('hello', $this->extractor->extractStringValue($node));
    }

    #[Test]
    public function it_extracts_integer_value(): void
    {
        $node = new LNumber(42);

        $this->assertSame(42, $this->extractor->extractValue($node));
        $this->assertSame(42, $this->extractor->extractIntValue($node));
    }

    #[Test]
    public function it_extracts_float_value(): void
    {
        $node = new DNumber(3.14);

        $this->assertSame(3.14, $this->extractor->extractValue($node));
        $this->assertSame(3.14, $this->extractor->extractFloatValue($node));
    }

    #[Test]
    public function it_extracts_true_constant(): void
    {
        $node = new ConstFetch(new Name('true'));

        $this->assertTrue($this->extractor->extractValue($node));
    }

    #[Test]
    public function it_extracts_false_constant(): void
    {
        $node = new ConstFetch(new Name('false'));

        $this->assertFalse($this->extractor->extractValue($node));
    }

    #[Test]
    public function it_extracts_null_constant(): void
    {
        $node = new ConstFetch(new Name('null'));

        $this->assertNull($this->extractor->extractValue($node));
    }

    #[Test]
    public function it_extracts_array_values(): void
    {
        $node = new Array_([
            new ArrayItem(new String_('a')),
            new ArrayItem(new LNumber(1)),
            new ArrayItem(new DNumber(2.5)),
        ]);

        $result = $this->extractor->extractArrayValues($node);

        $this->assertSame(['a', 1, 2.5], $result);
    }

    #[Test]
    public function it_extracts_key_value_array(): void
    {
        $node = new Array_([
            new ArrayItem(new String_('value1'), new String_('key1')),
            new ArrayItem(new LNumber(42), new String_('key2')),
        ]);

        $result = $this->extractor->extractKeyValueArray($node);

        $this->assertSame(['key1' => 'value1', 'key2' => 42], $result);
    }

    #[Test]
    public function it_returns_null_for_null_input(): void
    {
        $this->assertNull($this->extractor->extractValue(null));
        $this->assertNull($this->extractor->extractStringValue(null));
        $this->assertNull($this->extractor->extractIntValue(null));
        $this->assertNull($this->extractor->extractFloatValue(null));
    }

    #[Test]
    public function it_returns_null_for_unsupported_node_types(): void
    {
        $node = new Variable('foo');

        $this->assertNull($this->extractor->extractValue($node));
        $this->assertNull($this->extractor->extractStringValue($node));
        $this->assertNull($this->extractor->extractIntValue($node));
        $this->assertNull($this->extractor->extractFloatValue($node));
    }

    #[Test]
    public function it_returns_null_for_non_array_node_in_extract_array_values(): void
    {
        $node = new String_('not an array');

        $this->assertNull($this->extractor->extractArrayValues($node));
    }

    #[Test]
    public function it_returns_empty_array_for_non_array_node_in_extract_key_value_array(): void
    {
        $node = new String_('not an array');

        $this->assertSame([], $this->extractor->extractKeyValueArray($node));
    }

    #[Test]
    public function it_skips_null_items_in_array(): void
    {
        $node = new Array_([
            new ArrayItem(new String_('a')),
            null,
            new ArrayItem(new String_('b')),
        ]);

        $result = $this->extractor->extractArrayValues($node);

        $this->assertSame(['a', 'b'], $result);
    }

    #[Test]
    public function it_skips_items_without_keys_in_key_value_array(): void
    {
        $node = new Array_([
            new ArrayItem(new String_('value1'), new String_('key1')),
            new ArrayItem(new String_('value2')), // No key
        ]);

        $result = $this->extractor->extractKeyValueArray($node);

        $this->assertSame(['key1' => 'value1'], $result);
    }

    #[Test]
    public function it_handles_nested_array_in_extract_value(): void
    {
        $nestedArray = new Array_([
            new ArrayItem(new String_('nested')),
        ]);
        $node = new Array_([
            new ArrayItem(new String_('outer')),
            new ArrayItem($nestedArray),
        ]);

        $result = $this->extractor->extractValue($node);

        $this->assertSame(['outer', ['nested']], $result);
    }

    #[Test]
    public function it_handles_case_insensitive_constants(): void
    {
        $trueUpper = new ConstFetch(new Name('TRUE'));
        $falseMixed = new ConstFetch(new Name('False'));
        $nullLower = new ConstFetch(new Name('null'));

        $this->assertTrue($this->extractor->extractValue($trueUpper));
        $this->assertFalse($this->extractor->extractValue($falseMixed));
        $this->assertNull($this->extractor->extractValue($nullLower));
    }

    #[Test]
    public function extract_string_value_returns_null_for_non_string(): void
    {
        $this->assertNull($this->extractor->extractStringValue(new LNumber(42)));
    }

    #[Test]
    public function extract_int_value_returns_null_for_non_integer(): void
    {
        $this->assertNull($this->extractor->extractIntValue(new String_('hello')));
    }

    #[Test]
    public function extract_float_value_returns_null_for_non_float(): void
    {
        $this->assertNull($this->extractor->extractFloatValue(new String_('hello')));
    }
}
