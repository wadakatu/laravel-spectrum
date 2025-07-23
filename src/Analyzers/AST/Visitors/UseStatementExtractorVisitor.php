<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class UseStatementExtractorVisitor extends NodeVisitorAbstract
{
    private array $useStatements = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
                $this->useStatements[$alias] = $use->name->toString();
            }
        }

        return null;
    }

    public function getUseStatements(): array
    {
        return $this->useStatements;
    }
}
