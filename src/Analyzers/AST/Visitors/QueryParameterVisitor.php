<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use LaravelSpectrum\Support\QueryParameterDetector;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor to detect request parameter access patterns in AST.
 * Identifies patterns like $request->input(), Request::get(), magic properties, and conditional usage.
 */
class QueryParameterVisitor extends NodeVisitorAbstract
{
    private QueryParameterDetector $detector;

    /**
     * Create a new query parameter visitor.
     *
     * @param  QueryParameterDetector  $detector  The detector used to process parameter access patterns
     */
    public function __construct(QueryParameterDetector $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Process each node during traversal to detect request parameter access.
     *
     * @param  Node  $node  The current node being visited
     * @return int|null Visitor return value (null to continue traversal)
     */
    public function enterNode(Node $node): ?int
    {
        // Track variable assignments
        if ($node instanceof Node\Expr\Assign) {
            $this->detector->trackVariableAssignment($node);
        }

        // Detect method calls on $request variable
        if ($node instanceof MethodCall &&
            $node->var instanceof Variable &&
            $node->var->name === 'request') {
            $this->detector->processMethodCall($node);
        }

        // Detect static calls to Request
        if ($node instanceof StaticCall &&
            $this->isRequestClass($node->class)) {
            $this->detector->processStaticCall($node);
        }

        // Detect property fetch (magic access)
        if ($node instanceof PropertyFetch &&
            $node->var instanceof Variable &&
            $node->var->name === 'request') {
            $this->detector->processMagicAccess($node);
        }

        // Detect null coalescing with request property
        if ($node instanceof Node\Expr\BinaryOp\Coalesce &&
            $node->left instanceof PropertyFetch &&
            $node->left->var instanceof Variable &&
            $node->left->var->name === 'request') {
            $this->detector->processCoalesceWithRequest($node);
        }

        // Track context for enum detection
        if ($node instanceof FuncCall &&
            $node->name instanceof Node\Name &&
            $node->name->toString() === 'in_array') {
            $this->detector->processInArrayCall($node);
        }

        // Track if/switch/match for context
        if ($node instanceof If_ || $node instanceof Switch_ || $node instanceof Match_) {
            $this->detector->analyzeConditionalContext($node);
        }

        return null;
    }

    /**
     * Check if a node represents the Request class.
     *
     * @param  Node  $node  The node to check
     * @return bool True if the node represents the Request class
     */
    private function isRequestClass(Node $node): bool
    {
        if ($node instanceof Node\Name) {
            $name = $node->toString();

            return in_array($name, ['Request', 'Illuminate\\Http\\Request', '\\Illuminate\\Http\\Request']);
        }

        return false;
    }
}
