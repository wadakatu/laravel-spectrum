<?php

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\Analyzers\AST;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter;

/**
 * Extracts information from FormRequest AST nodes.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 */
class FormRequestAstExtractor
{
    public function __construct(
        protected PrettyPrinter\Standard $printer
    ) {}

    /**
     * Find a class node by name in the AST.
     *
     * @param  array<Node\Stmt>  $ast  The parsed AST nodes from PhpParser
     * @param  string  $className  The name of the class to find
     * @return Node\Stmt\Class_|null The found class node or null if not found
     */
    public function findClassNode(array $ast, string $className): ?Node\Stmt\Class_
    {
        $visitor = new AST\Visitors\ClassFindingVisitor($className);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getClassNode();
    }

    /**
     * Find a method node by name in a class.
     */
    public function findMethodNode(Node\Stmt\Class_ $class, string $methodName): ?Node\Stmt\ClassMethod
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod &&
                $stmt->name->toString() === $methodName) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Find an anonymous class node in the AST.
     *
     * @param  array<Node\Stmt>  $ast  The parsed AST nodes from PhpParser
     * @return Node\Stmt\Class_|null The found anonymous class node or null if not found
     */
    public function findAnonymousClassNode(array $ast): ?Node\Stmt\Class_
    {
        $visitor = new AST\Visitors\AnonymousClassFindingVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getClassNode();
    }

    /**
     * Extract validation rules from the rules() method.
     */
    public function extractRules(Node\Stmt\Class_ $class): array
    {
        $rulesMethod = $this->findMethodNode($class, 'rules');
        if (! $rulesMethod) {
            return [];
        }

        $visitor = new AST\Visitors\RulesExtractorVisitor($this->printer);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$rulesMethod]);

        return $visitor->getRules();
    }

    /**
     * Extract attributes from the attributes() method.
     */
    public function extractAttributes(Node\Stmt\Class_ $class): array
    {
        $attributesMethod = $this->findMethodNode($class, 'attributes');
        if (! $attributesMethod) {
            return [];
        }

        $visitor = new AST\Visitors\ArrayReturnExtractorVisitor($this->printer);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$attributesMethod]);

        return $visitor->getArray();
    }

    /**
     * Extract validation messages from the messages() method.
     */
    public function extractMessages(Node\Stmt\Class_ $class): array
    {
        $messagesMethod = $this->findMethodNode($class, 'messages');
        if (! $messagesMethod) {
            return [];
        }

        $visitor = new AST\Visitors\ArrayReturnExtractorVisitor($this->printer);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$messagesMethod]);

        return $visitor->getArray();
    }

    /**
     * Extract use statements from the AST.
     *
     * @param  array<Node\Stmt>  $ast  The parsed AST nodes from PhpParser
     * @return array<string, string> Map of short class names to fully qualified class names
     */
    public function extractUseStatements(array $ast): array
    {
        $visitor = new AST\Visitors\UseStatementExtractorVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getUseStatements();
    }

    /**
     * Extract conditional rules from the rules() method.
     */
    public function extractConditionalRules(Node\Stmt\Class_ $class): array
    {
        $rulesMethod = $this->findMethodNode($class, 'rules');
        if (! $rulesMethod) {
            return ['rules_sets' => [], 'merged_rules' => []];
        }

        $visitor = new AST\Visitors\ConditionalRulesExtractorVisitor($this->printer);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$rulesMethod]);

        return $visitor->getRuleSets();
    }
}
