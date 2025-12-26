<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\AST\Visitors;

use LaravelSpectrum\Analyzers\AST\Visitors\ArrayReturnExtractorVisitor;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArrayReturnExtractorVisitorTest extends TestCase
{
    private ArrayReturnExtractorVisitor $visitor;

    private NodeTraverser $traverser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visitor = new ArrayReturnExtractorVisitor(new PrettyPrinter\Standard);
        $this->traverser = new NodeTraverser;
        $this->traverser->addVisitor($this->visitor);
    }

    private function parseAndTraverse(string $code): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $stmts = $parser->parse($code);
        $this->traverser->traverse($stmts);

        return $this->visitor->getArray();
    }

    #[Test]
    public function it_extracts_simple_string_array(): void
    {
        $code = '<?php return ["name" => "test", "email" => "test@example.com"];';

        $result = $this->parseAndTraverse($code);

        $this->assertEquals([
            'name' => 'test',
            'email' => 'test@example.com',
        ], $result);
    }

    #[Test]
    public function it_extracts_integer_keys_and_values(): void
    {
        $code = '<?php return ["count" => 42, "limit" => 100];';

        $result = $this->parseAndTraverse($code);

        $this->assertEquals([
            'count' => 42,
            'limit' => 100,
        ], $result);
    }

    #[Test]
    public function it_extracts_nested_arrays(): void
    {
        $code = '<?php return ["user" => ["id" => 1, "name" => "John"]];';

        $result = $this->parseAndTraverse($code);

        $this->assertEquals([
            'user' => [
                'id' => 1,
                'name' => 'John',
            ],
        ], $result);
    }

    #[Test]
    public function it_handles_items_without_keys(): void
    {
        // Items without keys should be skipped
        $code = '<?php return ["first", "key" => "value", "second"];';

        $result = $this->parseAndTraverse($code);

        // Only the keyed item should be extracted
        $this->assertEquals(['key' => 'value'], $result);
    }

    #[Test]
    public function it_handles_null_items(): void
    {
        // Empty array with proper structure
        $code = '<?php return ["key" => "value"];';

        $result = $this->parseAndTraverse($code);

        $this->assertEquals(['key' => 'value'], $result);
    }

    #[Test]
    public function it_handles_expression_values(): void
    {
        // Variable expressions should be pretty-printed
        $code = '<?php return ["name" => $user->name];';

        $result = $this->parseAndTraverse($code);

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('$user->name', $result['name']);
    }

    #[Test]
    public function it_handles_method_call_expressions(): void
    {
        $code = '<?php return ["created_at" => $this->created_at->format("Y-m-d")];';

        $result = $this->parseAndTraverse($code);

        $this->assertArrayHasKey('created_at', $result);
        $this->assertStringContainsString('format', $result['created_at']);
    }

    #[Test]
    public function it_handles_concat_expressions(): void
    {
        $code = '<?php return ["full_name" => $first . " " . $last];';

        $result = $this->parseAndTraverse($code);

        $this->assertArrayHasKey('full_name', $result);
        // Should contain the concatenation expression
        $this->assertStringContainsString('$first', $result['full_name']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_return_statement(): void
    {
        $code = '<?php $x = 1;';

        $result = $this->parseAndTraverse($code);

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_returns_empty_array_when_return_is_not_array(): void
    {
        $code = '<?php return "not an array";';

        $result = $this->parseAndTraverse($code);

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_returns_null_from_enter_node_for_non_return_nodes(): void
    {
        $node = new Node\Stmt\Expression(new Node\Scalar\String_('test'));

        $result = $this->visitor->enterNode($node);

        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_ternary_expression_values(): void
    {
        $code = '<?php return ["status" => $active ? "active" : "inactive"];';

        $result = $this->parseAndTraverse($code);

        $this->assertArrayHasKey('status', $result);
        // Ternary should be pretty-printed
        $this->assertStringContainsString('?', $result['status']);
    }

    #[Test]
    public function it_handles_function_call_values(): void
    {
        $code = '<?php return ["hash" => md5($value)];';

        $result = $this->parseAndTraverse($code);

        $this->assertArrayHasKey('hash', $result);
        $this->assertStringContainsString('md5', $result['hash']);
    }

    #[Test]
    public function it_handles_deeply_nested_arrays(): void
    {
        $code = '<?php return ["level1" => ["level2" => ["level3" => "deep"]]];';

        $result = $this->parseAndTraverse($code);

        $this->assertEquals([
            'level1' => [
                'level2' => [
                    'level3' => 'deep',
                ],
            ],
        ], $result);
    }

    #[Test]
    public function it_handles_mixed_value_types(): void
    {
        $code = '<?php return ["string" => "text", "int" => 42, "nested" => ["a" => 1]];';

        $result = $this->parseAndTraverse($code);

        $this->assertEquals('text', $result['string']);
        $this->assertEquals(42, $result['int']);
        $this->assertEquals(['a' => 1], $result['nested']);
    }

    #[Test]
    public function it_handles_numeric_string_keys(): void
    {
        $code = '<?php return ["0" => "zero", "1" => "one"];';

        $result = $this->parseAndTraverse($code);

        $this->assertEquals([
            '0' => 'zero',
            '1' => 'one',
        ], $result);
    }
}
