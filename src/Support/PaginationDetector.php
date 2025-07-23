<?php

namespace LaravelSpectrum\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class PaginationDetector
{
    /**
     * Detect pagination method calls in AST
     *
     * @return array Array of pagination info ['type' => string, 'model' => string, 'resource' => string|null]
     */
    public function detectPaginationCalls(array $ast): array
    {
        $paginationCalls = [];

        $visitor = new class($paginationCalls) extends NodeVisitorAbstract
        {
            private array $paginationCalls;

            public function __construct(array &$paginationCalls)
            {
                $this->paginationCalls = &$paginationCalls;
            }

            public function enterNode(Node $node)
            {
                // Check for Resource::collection pattern
                if ($node instanceof StaticCall &&
                    $node->name instanceof Identifier &&
                    $node->name->name === 'collection' &&
                    count($node->args) > 0) {

                    $arg = $node->args[0]->value;

                    // Handle both direct pagination calls and nested calls
                    if ($arg instanceof MethodCall || $arg instanceof StaticCall) {
                        $paginationInfo = $this->checkPaginationCall($arg);

                        if ($paginationInfo) {
                            $paginationInfo['resource'] = $this->getClassName($node->class);
                            $this->paginationCalls[] = $paginationInfo;

                            return null; // Skip further processing
                        }
                    }
                }

                // Check for direct pagination calls
                if ($node instanceof MethodCall || $node instanceof StaticCall) {
                    $paginationInfo = $this->checkPaginationCall($node);

                    if ($paginationInfo) {
                        // Check if this pagination call is already wrapped in Resource::collection
                        $alreadyHandled = false;
                        foreach ($this->paginationCalls as $existing) {
                            if ($existing['type'] === $paginationInfo['type'] &&
                                $existing['model'] === $paginationInfo['model'] &&
                                isset($existing['resource'])) {
                                $alreadyHandled = true;
                                break;
                            }
                        }

                        if (! $alreadyHandled) {
                            $this->paginationCalls[] = $paginationInfo;
                        }
                    }
                }

                return null;
            }

            private function checkPaginationCall($node): ?array
            {
                $detector = new PaginationDetector;

                if (! ($node instanceof MethodCall || $node instanceof StaticCall)) {
                    return null;
                }

                $methodName = null;
                if ($node->name instanceof Identifier) {
                    $methodName = $node->name->name;
                }

                $paginationMethods = ['paginate', 'simplePaginate', 'cursorPaginate'];
                if (! $methodName || ! in_array($methodName, $paginationMethods)) {
                    return null;
                }

                $model = $detector->extractModelFromMethodCall($node);

                if (! $model) {
                    return null;
                }

                $result = [
                    'type' => $methodName,
                    'model' => $model,
                    'resource' => null,
                ];

                // Check if it's a relation or query builder
                if ($node instanceof MethodCall && $node->var instanceof Variable) {
                    $result['source'] = 'relation';
                } elseif ($this->isQueryBuilder($node)) {
                    $result['source'] = 'query_builder';
                }

                return $result;
            }

            private function getClassName($node): ?string
            {
                if ($node instanceof Name) {
                    return $node->getLast();
                }

                return null;
            }

            private function isQueryBuilder($node): bool
            {
                if (! $node instanceof MethodCall) {
                    return false;
                }

                $current = $node;
                while ($current->var instanceof MethodCall) {
                    $current = $current->var;
                }

                if ($current->var instanceof StaticCall &&
                    $current->var->class instanceof Name &&
                    $current->var->class->getLast() === 'DB' &&
                    $current->name instanceof Identifier &&
                    $current->name->name === 'table') {
                    return true;
                }

                return false;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $paginationCalls;
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
