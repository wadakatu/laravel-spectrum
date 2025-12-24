<?php

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\Analyzers\AST;
use LaravelSpectrum\Support\ErrorCollector;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Provides common AST parsing and traversal utilities.
 *
 * This helper class centralizes AST operations used across multiple analyzers,
 * including file parsing, class/method/property node finding, and statement extraction.
 *
 * Used by FormRequestAstExtractor, ResourceAnalyzer, ControllerAnalyzer, and FractalTransformerAnalyzer.
 */
class AstHelper
{
    protected readonly Parser $parser;

    protected readonly ErrorCollector $errorCollector;

    /**
     * Create a new AstHelper instance.
     *
     * @param  Parser|null  $parser  Optional custom parser instance (defaults to newest supported version)
     * @param  ErrorCollector|null  $errorCollector  Optional error collector for logging parse failures
     */
    public function __construct(
        ?Parser $parser = null,
        ?ErrorCollector $errorCollector = null
    ) {
        $this->parser = $parser ?? (new ParserFactory)->createForNewestSupportedVersion();
        $this->errorCollector = $errorCollector ?? new ErrorCollector;
    }

    /**
     * Parse a PHP file and return its AST.
     *
     * @param  string  $filePath  The path to the PHP file to parse
     * @return array<Node\Stmt>|null The parsed AST nodes or null if file not found, read fails, or parse error occurs
     */
    public function parseFile(string $filePath): ?array
    {
        if (! file_exists($filePath)) {
            $this->errorCollector->addWarning(
                self::class,
                "File does not exist: {$filePath}",
                ['file_path' => $filePath, 'error_type' => 'file_not_found']
            );

            return null;
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            $this->errorCollector->addError(
                self::class,
                "Failed to read file: {$filePath}",
                ['file_path' => $filePath, 'error_type' => 'file_read_error']
            );

            return null;
        }

        return $this->parseCode($code, $filePath);
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
        try {
            return $this->parser->parse($code);
        } catch (Error $e) {
            $context = $sourceContext ? "file: {$sourceContext}" : 'code string';
            $metadata = [
                'parse_error' => $e->getMessage(),
                'error_type' => 'parse_error',
            ];

            // Include file_path when sourceContext is provided for traceability
            if ($sourceContext !== null) {
                $metadata['file_path'] = $sourceContext;
            }

            $this->errorCollector->addError(
                self::class,
                "Failed to parse PHP {$context}",
                $metadata
            );

            return null;
        }
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
        $visitor = new AST\Visitors\ClassFindingVisitor($className);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getClassNode();
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
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod &&
                $stmt->name->toString() === $methodName) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Find a property node by name in a class.
     *
     * @param  Node\Stmt\Class_  $class  The class node to search within
     * @param  string  $propertyName  The name of the property to find
     * @return Node\Stmt\Property|null The found property node or null if not found
     */
    public function findPropertyNode(Node\Stmt\Class_ $class, string $propertyName): ?Node\Stmt\Property
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop->name->toString() === $propertyName) {
                        return $stmt;
                    }
                }
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
}
