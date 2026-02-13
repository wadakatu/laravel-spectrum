<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class UseStatementExtractorVisitor extends NodeVisitorAbstract
{
    /** @var array<string, string> */
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

    /**
     * @return array<string, string>
     */
    public function getUseStatements(): array
    {
        return $this->useStatements;
    }
}
