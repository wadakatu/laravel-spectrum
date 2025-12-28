<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class AnonymousClassFindingVisitor extends NodeVisitorAbstract
{
    private ?Node\Stmt\Class_ $classNode = null;

    private bool $extendsFormRequest = false;

    private ?int $targetLine = null;

    /**
     * Create a new visitor instance.
     *
     * @param  int|null  $targetLine  If provided, only match anonymous class starting at this line
     */
    public function __construct(?int $targetLine = null)
    {
        $this->targetLine = $targetLine;
    }

    public function enterNode(Node $node)
    {
        // Look for anonymous class nodes in various contexts
        if ($node instanceof Node\Expr\New_ &&
            $node->class instanceof Node\Stmt\Class_) {

            // If target line is specified, only match if the class starts at that line
            if ($this->targetLine !== null) {
                $classStartLine = $node->class->getStartLine();
                if ($classStartLine !== $this->targetLine) {
                    return null; // Continue searching
                }
            }

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
