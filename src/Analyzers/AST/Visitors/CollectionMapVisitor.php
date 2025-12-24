<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor to extract return structure from closure expressions.
 * Used for analyzing collection map operations.
 */
class CollectionMapVisitor extends NodeVisitorAbstract
{
    public ?Node $returnStructure = null;

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\Return_ && $node->expr) {
            $this->returnStructure = $node->expr;
        }

        return null;
    }

    public function getReturnStructure(): ?Node
    {
        return $this->returnStructure;
    }
}
