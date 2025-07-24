<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class ClassFindingVisitor extends NodeVisitorAbstract
{
    private string $className;

    private ?Node\Stmt\Class_ $classNode = null;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\Class_) {
            // For named classes
            if ($node->name && $node->name->toString() === $this->className) {
                $this->classNode = $node;

                return NodeTraverser::STOP_TRAVERSAL;
            }

            // For anonymous classes (when className contains 'class@anonymous')
            if (! $node->name && strpos($this->className, 'class@anonymous') !== false) {
                // Check if this anonymous class extends FormRequest
                if ($node->extends && $node->extends->toString() === 'FormRequest') {
                    $this->classNode = $node;

                    return NodeTraverser::STOP_TRAVERSAL;
                }
            }
        }

        return null;
    }

    public function getClassNode(): ?Node\Stmt\Class_
    {
        return $this->classNode;
    }
}
