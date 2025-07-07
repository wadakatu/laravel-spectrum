<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

class ArrayReturnExtractorVisitor extends NodeVisitorAbstract
{
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

    private function extractArray(Node\Expr\Array_ $array): array
    {
        $result = [];

        foreach ($array->items as $item) {
            $key = $this->evaluateExpression($item->key);
            $value = $this->evaluateExpression($item->value);

            if ($key !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function evaluateExpression(Node $expr)
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

    public function getArray(): array
    {
        return $this->array;
    }
}
