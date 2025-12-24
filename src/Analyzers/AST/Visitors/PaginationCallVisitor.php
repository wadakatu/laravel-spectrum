<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use LaravelSpectrum\Support\PaginationDetector;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor to detect pagination method calls in AST.
 * Identifies patterns like Model::paginate(), Resource::collection(), and query builder pagination.
 */
class PaginationCallVisitor extends NodeVisitorAbstract
{
    /**
     * Detected pagination calls.
     *
     * @var array<int, array{type: string, model: string, resource: ?string, source?: string}>
     */
    private array $paginationCalls = [];

    private PaginationDetector $detector;

    /**
     * Create a new pagination call visitor.
     *
     * @param  PaginationDetector  $detector  The detector used to extract model information
     */
    public function __construct(PaginationDetector $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Reset state before traversing a new AST.
     *
     * @param  array<Node>  $nodes  The nodes to be traversed
     * @return null
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->paginationCalls = [];

        return null;
    }

    /**
     * Process each node during traversal to detect pagination calls.
     *
     * @param  Node  $node  The current node being visited
     * @return int|null Visitor return value (null to continue traversal)
     */
    public function enterNode(Node $node): ?int
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

    /**
     * Get all detected pagination calls.
     *
     * @return array<int, array{type: string, model: string, resource: ?string, source?: string}>
     */
    public function getPaginationCalls(): array
    {
        return $this->paginationCalls;
    }

    /**
     * Check if node is a pagination method call and extract info.
     *
     * @param  MethodCall|StaticCall  $node  The node to check
     * @return array{type: string, model: string, resource: null, source?: string}|null
     */
    private function checkPaginationCall(MethodCall|StaticCall $node): ?array
    {

        $methodName = null;
        if ($node->name instanceof Identifier) {
            $methodName = $node->name->name;
        }

        $paginationMethods = ['paginate', 'simplePaginate', 'cursorPaginate'];
        if (! $methodName || ! in_array($methodName, $paginationMethods)) {
            return null;
        }

        $model = $this->detector->extractModelFromMethodCall($node);

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

    /**
     * Get class name from node.
     *
     * @param  Node  $node  The node to extract class name from
     * @return string|null The class name or null if not extractable
     */
    private function getClassName(Node $node): ?string
    {
        if ($node instanceof Name) {
            return $node->getLast();
        }

        return null;
    }

    /**
     * Check if node represents a query builder pattern.
     *
     * @param  Node  $node  The node to check
     * @return bool True if node is a query builder pattern
     */
    private function isQueryBuilder(Node $node): bool
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
}
