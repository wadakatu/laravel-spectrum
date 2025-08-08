<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class AnonymousClassFindingVisitor extends NodeVisitorAbstract
{
    private ?Node\Stmt\Class_ $classNode = null;

    private bool $extendsFormRequest = false;

    public function enterNode(Node $node)
    {
        // Look for anonymous class nodes in various contexts
        if ($node instanceof Node\Expr\New_ &&
            $node->class instanceof Node\Stmt\Class_) {
            $this->classNode = $node->class;

            // Check if it extends FormRequest
            if ($node->class->extends) {
                $extendedClass = $node->class->extends->toString();
                if (str_ends_with($extendedClass, 'FormRequest')) {
                    $this->extendsFormRequest = true;
                }
            }

            return NodeTraverser::STOP_TRAVERSAL;
        }

        return null;
    }

    public function getClassNode(): ?Node\Stmt\Class_
    {
        return $this->classNode;
    }

    public function extendsFormRequest(): bool
    {
        return $this->extendsFormRequest;
    }
}
