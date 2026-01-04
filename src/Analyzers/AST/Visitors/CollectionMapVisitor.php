<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class CollectionMapVisitor extends NodeVisitorAbstract
{
    /**
     * The return expression node from the last return statement found.
     * Note: If multiple return statements exist, only the last one is captured.
     */
    private ?Node\Expr $returnStructure = null;

    /**
     * Reset state before traversing a new AST.
     *
     * @param  array<Node>  $nodes  The nodes to be traversed
     * @return array<Node>|null
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->returnStructure = null;

        return null;
    }

    /**
     * Process each node during traversal.
     *
     * @param  Node  $node  The current node being visited
     * @return int|null Visitor return value (null to continue traversal)
     */
    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\Return_ && $node->expr) {
            $this->returnStructure = $node->expr;
        }

        return null;
    }

    /**
     * Get the return expression from the traversed closure.
     *
     * @return Node\Expr|null The expression node from the last return statement, or null if none found
     */
    public function getReturnStructure(): ?Node\Expr
    {
        return $this->returnStructure;
    }
}
