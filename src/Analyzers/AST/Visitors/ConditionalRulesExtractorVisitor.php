<?php

namespace LaravelPrism\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

class ConditionalRulesExtractorVisitor extends NodeVisitorAbstract
{
    private array $ruleSets = [];

    private array $currentPath = [];

    private PrettyPrinter\Standard $printer;

    private bool $foundReturn = false;

    public function __construct(PrettyPrinter\Standard $printer)
    {
        $this->printer = $printer;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\If_) {
            $this->processIfStatement($node);

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Stmt\Return_ && $node->expr instanceof Node\Expr\Array_) {
            $this->addRuleSet($node->expr);
            $this->foundReturn = true;
        }

        return null;
    }

    private function processIfStatement(Node\Stmt\If_ $ifNode): void
    {
        // Analyze condition
        $condition = $this->analyzeCondition($ifNode->cond);

        // Process if block
        $this->currentPath[] = $condition;
        $this->traverseStatements($ifNode->stmts);
        array_pop($this->currentPath);

        // Process elseif blocks
        foreach ($ifNode->elseifs as $elseif) {
            $elseifCondition = $this->analyzeCondition($elseif->cond);
            $this->currentPath[] = $elseifCondition;
            $this->traverseStatements($elseif->stmts);
            array_pop($this->currentPath);
        }

        // Process else block
        if ($ifNode->else !== null) {
            $negatedConditions = [];

            // Negate the main if condition
            $negatedConditions[] = $this->negateCondition($condition);

            // Also negate all elseif conditions
            foreach ($ifNode->elseifs as $elseif) {
                $elseifCondition = $this->analyzeCondition($elseif->cond);
                $negatedConditions[] = $this->negateCondition($elseifCondition);
            }

            // For else block, we need to ensure none of the previous conditions matched
            $this->currentPath[] = [
                'type' => 'else',
                'negated_conditions' => $negatedConditions,
                'expression' => 'else',
            ];

            $this->traverseStatements($ifNode->else->stmts);
            array_pop($this->currentPath);
        }
    }

    private function analyzeCondition(Node\Expr $condition): array
    {
        // $this->isMethod('POST') pattern
        if ($condition instanceof Node\Expr\MethodCall &&
            $condition->var instanceof Node\Expr\Variable &&
            $condition->var->name === 'this' &&
            $condition->name instanceof Node\Identifier &&
            $condition->name->toString() === 'isMethod') {

            $method = $this->extractStringArgument($condition->args[0] ?? null);

            return [
                'type' => 'http_method',
                'method' => $method,
                'expression' => $this->printer->prettyPrintExpr($condition),
            ];
        }

        // $this->user()->isAdmin() pattern
        if ($condition instanceof Node\Expr\MethodCall &&
            $condition->var instanceof Node\Expr\MethodCall &&
            $condition->var->var instanceof Node\Expr\Variable &&
            $condition->var->var->name === 'this') {

            return [
                'type' => 'user_method',
                'chain' => $this->printer->prettyPrintExpr($condition),
                'expression' => $this->printer->prettyPrintExpr($condition),
            ];
        }

        // request()->isMethod('POST') pattern
        if ($condition instanceof Node\Expr\MethodCall &&
            $condition->var instanceof Node\Expr\FuncCall &&
            $condition->var->name instanceof Node\Name &&
            $condition->var->name->toString() === 'request' &&
            $condition->name instanceof Node\Identifier &&
            $condition->name->toString() === 'isMethod') {

            $method = $this->extractStringArgument($condition->args[0] ?? null);

            return [
                'type' => 'http_method',
                'method' => $method,
                'expression' => $this->printer->prettyPrintExpr($condition),
            ];
        }

        // Other conditions
        return [
            'type' => 'custom',
            'expression' => $this->printer->prettyPrintExpr($condition),
        ];
    }

    private function extractStringArgument(?Node\Arg $arg): ?string
    {
        if (! $arg || ! $arg->value instanceof Node\Scalar\String_) {
            return null;
        }

        return $arg->value->value;
    }

    private function negateCondition(array $condition): array
    {
        $negated = $condition;
        $negated['negated'] = true;

        if ($condition['type'] === 'http_method') {
            $negated['description'] = 'NOT '.($condition['method'] ?? 'unknown');
        }

        return $negated;
    }

    private function traverseStatements(array $statements): void
    {
        $traverser = new NodeTraverser;
        $traverser->addVisitor($this);
        $traverser->traverse($statements);
    }

    private function addRuleSet(Node\Expr\Array_ $rules): void
    {
        // Only add rule set if we're inside a conditional path or if no conditions exist
        $this->ruleSets[] = [
            'conditions' => $this->currentPath,
            'rules' => $this->extractArrayRules($rules),
            'probability' => $this->calculateProbability(),
        ];
    }

    private function extractArrayRules(Node\Expr\Array_ $array): array
    {
        $rules = [];

        foreach ($array->items as $item) {
            if (! isset($item->key)) {
                continue;
            }

            $key = $this->evaluateKey($item->key);
            $value = $this->evaluateValue($item->value);

            if ($key !== null && $value !== null) {
                $rules[$key] = $value;
            }
        }

        return $rules;
    }

    private function evaluateKey(Node $expr): ?string
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Scalar\LNumber) {
            return (string) $expr->value;
        }

        // Handle other expressions
        if ($expr instanceof Node\Expr) {
            return $this->printer->prettyPrintExpr($expr);
        }

        return null;
    }

    private function evaluateValue(Node $expr)
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Expr\Array_) {
            $result = [];
            foreach ($expr->items as $item) {
                $value = $this->evaluateValue($item->value);
                if ($value !== null) {
                    $result[] = $value;
                }
            }

            return $result;
        }

        // Handle concatenation (e.g., 'required|string')
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            $left = $this->evaluateValue($expr->left);
            $right = $this->evaluateValue($expr->right);

            if (is_string($left) && is_string($right)) {
                return $left.$right;
            }
        }

        // Handle static calls (e.g., Rule::unique())
        if ($expr instanceof Node\Expr\StaticCall) {
            return $this->printer->prettyPrintExpr($expr);
        }

        // Handle other expressions
        if ($expr instanceof Node\Expr) {
            return $this->printer->prettyPrintExpr($expr);
        }

        return '';
    }

    private function calculateProbability(): float
    {
        // Simple probability calculation based on condition depth
        return 1.0 / pow(2, count($this->currentPath));
    }

    public function getRuleSets(): array
    {
        // If no conditional rules were found, return empty structure
        if (empty($this->ruleSets)) {
            return [
                'rules_sets' => [],
                'merged_rules' => [],
            ];
        }

        return [
            'rules_sets' => $this->ruleSets,
            'merged_rules' => $this->mergeAllRules(),
        ];
    }

    private function mergeAllRules(): array
    {
        $merged = [];

        foreach ($this->ruleSets as $set) {
            foreach ($set['rules'] as $field => $rules) {
                if (! isset($merged[$field])) {
                    $merged[$field] = [];
                }

                if (is_string($rules)) {
                    $rules = explode('|', $rules);
                }

                if (is_array($rules)) {
                    $merged[$field] = array_unique(array_merge($merged[$field], $rules));
                }
            }
        }

        return $merged;
    }

    /**
     * Check if conditional rules were found
     */
    public function hasConditionalRules(): bool
    {
        return $this->foundReturn && ! empty($this->currentPath);
    }
}
