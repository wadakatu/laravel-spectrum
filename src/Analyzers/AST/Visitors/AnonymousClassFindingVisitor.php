<?php

namespace LaravelPrism\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class AnonymousClassFindingVisitor extends NodeVisitorAbstract
{
    private ?Node\Stmt\Class_ $classNode = null;

    public function enterNode(Node $node)
    {
        // Look for anonymous class nodes in various contexts
        if ($node instanceof Node\Expr\New_ &&
            $node->class instanceof Node\Stmt\Class_) {
            $this->classNode = $node->class;

            return NodeTraverser::STOP_TRAVERSAL;
        }

        return null;
    }

    public function getClassNode(): ?Node\Stmt\Class_
    {
        return $this->classNode;
    }
}
