<?php

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\Analyzers\AST;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter;

/**
 * Extracts information from FormRequest AST nodes.
 *
 * Handles both parsing PHP files into AST and extracting specific information
 * like validation rules, attributes, and messages from FormRequest classes.
 *
 * Delegates common AST operations to AstHelper while providing FormRequest-specific
 * extraction methods.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 */
class FormRequestAstExtractor
{
    protected AstHelper $astHelper;

    /**
     * Create a new FormRequestAstExtractor instance.
     *
     * @param  AstHelper  $astHelper  AstHelper instance for common AST operations
     * @param  PrettyPrinter\Standard  $printer  The PHP-Parser pretty printer for code output
     */
    public function __construct(
        AstHelper $astHelper,
        protected PrettyPrinter\Standard $printer
    ) {
        $this->astHelper = $astHelper;
    }

    /**
     * Parse a PHP file and return its AST.
     *
     * @param  string  $filePath  The path to the PHP file to parse
     * @return array<Node\Stmt>|null The parsed AST nodes or null if file not found, read fails, or parse error occurs
     */
    public function parseFile(string $filePath): ?array
    {
        return $this->astHelper->parseFile($filePath);
    }

    /**
     * Parse PHP code string and return its AST.
     *
     * @param  string  $code  The PHP code to parse
     * @param  string|null  $sourceContext  Optional context for error messages (e.g., file path)
     * @return array<Node\Stmt>|null The parsed AST nodes or null if parse error occurs
     */
    public function parseCode(string $code, ?string $sourceContext = null): ?array
    {
        return $this->astHelper->parseCode($code, $sourceContext);
    }

    /**
     * Find a class node by name in the AST.
     *
     * @param  array<Node\Stmt>  $ast  The parsed AST nodes from PhpParser
     * @param  string  $className  The name of the class to find
     * @return Node\Stmt\Class_|null The found class node or null if not found
     */
    public function findClassNode(array $ast, string $className): ?Node\Stmt\Class_
    {
        return $this->astHelper->findClassNode($ast, $className);
    }

    /**
     * Find a method node by name in a class.
     *
     * @param  Node\Stmt\Class_  $class  The class node to search within
     * @param  string  $methodName  The name of the method to find
     * @return Node\Stmt\ClassMethod|null The found method node or null if not found
     */
    public function findMethodNode(Node\Stmt\Class_ $class, string $methodName): ?Node\Stmt\ClassMethod
    {
        return $this->astHelper->findMethodNode($class, $methodName);
    }

    /**
     * Find an anonymous class node in the AST.
     *
     * @param  array<Node\Stmt>  $ast  The parsed AST nodes from PhpParser
     * @return Node\Stmt\Class_|null The found anonymous class node or null if not found
     */
    public function findAnonymousClassNode(array $ast): ?Node\Stmt\Class_
    {
        return $this->astHelper->findAnonymousClassNode($ast);
    }

    /**
     * Extract validation rules from the rules() method.
     *
     * @param  Node\Stmt\Class_  $class  The FormRequest class node
     * @return array<string, mixed> Extracted validation rules as field => rules mapping
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
     *
     * @param  Node\Stmt\Class_  $class  The FormRequest class node
     * @return array<string, string> Extracted attributes as field => label mapping
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
     *
     * @param  Node\Stmt\Class_  $class  The FormRequest class node
     * @return array<string, string> Custom validation messages as rule.field => message mapping
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
        return $this->astHelper->extractUseStatements($ast);
    }

    /**
     * Extract conditional rules from the rules() method.
     *
     * @param  Node\Stmt\Class_  $class  The FormRequest class node
     * @return array{rules_sets: array<mixed>, merged_rules: array<string, mixed>} Extracted conditional rule sets
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
