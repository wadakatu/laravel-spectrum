<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ResponseStructureVisitor extends NodeVisitorAbstract
{
    private array $structure = [];

    private array $variables = [];

    public function enterNode(Node $node)
    {
        // 変数の割り当てを追跡
        if ($node instanceof Node\Expr\Assign) {
            if ($node->var instanceof Node\Expr\Variable) {
                $varName = $node->var->name;
                if (is_string($varName)) {
                    $this->variables[$varName] = $node->expr;
                }
            }
        }

        // メソッド呼び出しを追跡
        if ($node instanceof Node\Expr\MethodCall) {
            $this->analyzeMethodCall($node);
        }

        // 配列構造を追跡
        if ($node instanceof Node\Expr\Array_) {
            $this->analyzeArray($node);
        }

        return null;
    }

    private function analyzeMethodCall(Node\Expr\MethodCall $node): void
    {
        if (! $node->name instanceof Node\Identifier) {
            return;
        }

        $methodName = $node->name->toString();

        // response()->json()パターン
        if ($methodName === 'json' && $node->var instanceof Node\Expr\FuncCall) {
            $funcCall = $node->var;
            if ($funcCall->name instanceof Node\Name && $funcCall->name->toString() === 'response') {
                $this->structure['type'] = 'response_json';
                if (isset($node->args[0])) {
                    $this->structure['data'] = $this->extractValue($node->args[0]->value);
                }
            }
        }

        // コレクション操作
        if (in_array($methodName, ['map', 'filter', 'pluck', 'only', 'except'])) {
            $this->structure['collection_operations'][] = [
                'method' => $methodName,
                'args' => $this->extractArguments($node->args),
            ];
        }
    }

    private function analyzeArray(Node\Expr\Array_ $node): void
    {
        $arrayStructure = [];
        foreach ($node->items as $item) {
            if ($item && $item->key) {
                $key = $this->extractValue($item->key);
                $value = $this->extractValue($item->value);
                if ($key !== null) {
                    $arrayStructure[$key] = $value;
                }
            }
        }
        $this->structure['array_structures'][] = $arrayStructure;
    }

    private function extractValue($node)
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }
        if ($node instanceof Node\Scalar\Int_) {
            return $node->value;
        }
        if ($node instanceof Node\Scalar\Float_) {
            return $node->value;
        }
        if ($node instanceof Node\Expr\ConstFetch) {
            return $node->name->toLowerString();
        }
        if ($node instanceof Node\Expr\Variable && isset($this->variables[$node->name])) {
            return $this->extractValue($this->variables[$node->name]);
        }

        return null;
    }

    private function extractArguments(array $args): array
    {
        $result = [];
        foreach ($args as $arg) {
            if ($arg instanceof Node\Arg) {
                $result[] = $this->extractValue($arg->value);
            }
        }

        return $result;
    }

    public function getStructure(): array
    {
        return $this->structure;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }
}
