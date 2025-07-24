<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ReturnStatementVisitor extends NodeVisitorAbstract
{
    private array $returnStatements = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Return_) {
            $this->returnStatements[] = $node;
        }

        return null;
    }

    public function getReturnStatements(): array
    {
        return $this->returnStatements;
    }
}
