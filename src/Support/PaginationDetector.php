<?php

namespace LaravelSpectrum\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;

class PaginationDetector
{
    /**
     * Detect pagination method calls in AST
     *
     * @return array Array of pagination info ['type' => string, 'model' => string, 'resource' => string|null]
     */
    public function detectPaginationCalls(array $ast): array
    {
        $visitor = new \LaravelSpectrum\Analyzers\AST\Visitors\PaginationCallVisitor($this);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getPaginationCalls();
    }

    /**
     * Get pagination type from method name
     */
    public function getPaginationType(string $methodName): string
    {
        return match ($methodName) {
            'paginate' => 'length_aware',
            'simplePaginate' => 'simple',
            'cursorPaginate' => 'cursor',
            default => 'unknown'
        };
    }

    /**
     * Extract model from method call
     *
     * @param  Node  $node
     */
    public function extractModelFromMethodCall($node): ?string
    {
        if ($node instanceof StaticCall) {
            // Direct static call: User::paginate()
            if ($node->class instanceof Name) {
                return $node->class->getLast();
            }
        }

        if ($node instanceof MethodCall) {
            // Method chain: find the root
            $current = $node;

            while ($current->var instanceof MethodCall) {
                // Check if this is a table() call
                if ($current->var->name instanceof Identifier && $current->var->name->name === 'table') {
                    // Check if the var is DB static call
                    if ($current->var->var instanceof StaticCall &&
                        $current->var->var->class instanceof Name &&
                        $current->var->var->class->getLast() === 'DB') {

                        if (count($current->var->args) > 0 &&
                            $current->var->args[0]->value instanceof Node\Scalar\String_) {
                            return $current->var->args[0]->value->value;
                        }
                    }
                }
                $current = $current->var;
            }

            // Check if it's a static call at the root
            if ($current->var instanceof StaticCall) {
                // Check if it's Model::where()->paginate() pattern
                $model = $this->extractModelFromStaticCall($current->var);
                if ($model && $model !== 'DB') {
                    return $model;
                }

                // Check if it's DB::table()
                if ($current->var->class instanceof Name &&
                    $current->var->class->getLast() === 'DB' &&
                    $current->var->name instanceof Identifier &&
                    $current->var->name->name === 'table') {

                    if (count($current->var->args) > 0 &&
                        $current->var->args[0]->value instanceof Node\Scalar\String_) {
                        return $current->var->args[0]->value->value;
                    }
                }
            }

            // Check if it's a relation: $user->posts()
            if ($current->var instanceof Variable &&
                $current->name instanceof Identifier) {
                return $current->name->name;
            }
        }

        return null;
    }

    /**
     * Extract model from static call
     */
    private function extractModelFromStaticCall(StaticCall $node): ?string
    {
        if ($node->class instanceof Name) {
            return $node->class->getLast();
        }

        return null;
    }
}
