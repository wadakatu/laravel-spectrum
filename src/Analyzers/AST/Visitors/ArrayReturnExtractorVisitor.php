<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

/**
 * @phpstan-type ArrayValue string|int|array<string|int, mixed>
 * @phpstan-type ExtractedArray array<string|int, ArrayValue>
 */
class ArrayReturnExtractorVisitor extends NodeVisitorAbstract
{
    /** @var ExtractedArray */
    private array $array = [];

    private PrettyPrinter\Standard $printer;

    public function __construct(PrettyPrinter\Standard $printer)
    {
        $this->printer = $printer;
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\Return_ &&
            $node->expr instanceof Node\Expr\Array_) {
            $this->array = $this->extractArray($node->expr);
        }

        return null;
    }

    /**
     * @return ExtractedArray
     */
    private function extractArray(Node\Expr\Array_ $array): array
    {
        $result = [];

        foreach ($array->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $key = $this->evaluateExpression($item->key);
            $value = $this->evaluateExpression($item->value);
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return ArrayValue
     */
    private function evaluateExpression(Node $expr): string|int|array
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Scalar\LNumber) {
            return $expr->value;
        }

        if ($expr instanceof Node\Expr\Array_) {
            return $this->extractArray($expr);
        }

        // その他の式は文字列として保存
        if ($expr instanceof Node\Expr) {
            return $this->printer->prettyPrintExpr($expr);
        }

        return '';
    }

    /**
     * @return ExtractedArray
     */
    public function getArray(): array
    {
        return $this->array;
    }
}
