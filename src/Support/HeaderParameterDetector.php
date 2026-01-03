<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use LaravelSpectrum\Analyzers\Support\AstNodeValueExtractor;
use LaravelSpectrum\DTO\DetectedHeaderParameter;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use ReflectionMethod;

/**
 * Detects header parameter usage in controller methods via AST analysis.
 */
class HeaderParameterDetector
{
    /** @var array<int, DetectedHeaderParameter> */
    private array $detectedParams = [];

    private Parser $parser;

    private AstNodeValueExtractor $valueExtractor;

    public function __construct(Parser $parser, ?AstNodeValueExtractor $valueExtractor = null)
    {
        $this->parser = $parser;
        $this->valueExtractor = $valueExtractor ?? new AstNodeValueExtractor;
    }

    /**
     * Parse method and detect header calls.
     *
     * @return array<Node>|null
     */
    public function parseMethod(ReflectionMethod $method): ?array
    {
        try {
            $fileName = $method->getFileName();
            $startLine = $method->getStartLine() - 1;
            $endLine = $method->getEndLine();
            $length = $endLine - $startLine;

            if (! $fileName) {
                return null;
            }

            $source = file($fileName);
            if ($source === false) {
                return null;
            }

            $methodSource = implode('', array_slice($source, $startLine, $length));

            $code = "<?php\nclass TempClass {\n".$methodSource."\n}";

            $ast = $this->parser->parse($code);
            if (! $ast) {
                return null;
            }

            foreach ($ast as $node) {
                if ($node instanceof Node\Stmt\Class_) {
                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof Node\Stmt\ClassMethod &&
                            $stmt->name->toString() === $method->getName()) {
                            return [$stmt];
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            // Silently return null on parsing errors (consistent with QueryParameterDetector pattern)
            // The calling analyzer can handle the null case appropriately
            report($e);

            return null;
        }
    }

    /**
     * Detect header method calls from AST.
     *
     * @param  array<Node>|null  $ast
     * @return array<int, DetectedHeaderParameter>
     */
    public function detectHeaderCalls(?array $ast): array
    {
        if ($ast === null) {
            return [];
        }

        $this->detectedParams = [];

        $detector = $this;
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new class($detector) extends NodeVisitorAbstract
        {
            private HeaderParameterDetector $detector;

            public function __construct(HeaderParameterDetector $detector)
            {
                $this->detector = $detector;
            }

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof MethodCall &&
                    $node->var instanceof Variable &&
                    $node->var->name === 'request') {
                    $this->detector->processMethodCall($node);
                }

                return null;
            }
        });

        $traverser->traverse($ast);

        return $this->consolidateParameters();
    }

    /**
     * Process method call on $request.
     */
    public function processMethodCall(MethodCall $node): void
    {
        $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if (! $methodName || ! $this->isHeaderMethod($methodName)) {
            return;
        }

        // Special case for bearerToken() - no arguments needed
        if ($methodName === 'bearerToken') {
            $this->detectedParams[] = DetectedHeaderParameter::create(
                name: 'Authorization',
                method: 'bearerToken',
                context: ['is_bearer_token' => true]
            );

            return;
        }

        $args = $node->args;
        if (empty($args)) {
            return;
        }

        $headerName = $this->valueExtractor->extractStringValue($args[0]->value);
        if (! $headerName) {
            return;
        }

        $context = [];

        if ($methodName === 'hasHeader') {
            $context['hasHeader_check'] = true;
        }

        $this->detectedParams[] = DetectedHeaderParameter::create(
            name: $headerName,
            method: $methodName,
            default: isset($args[1]) ? $this->valueExtractor->extractValue($args[1]->value) : null,
            context: $context
        );
    }

    /**
     * Check if method name is a header-related method.
     */
    private function isHeaderMethod(string $method): bool
    {
        return in_array($method, [
            'header',
            'hasHeader',
            'bearerToken',
        ], true);
    }

    /**
     * Consolidate and deduplicate detected parameters.
     *
     * @return array<int, DetectedHeaderParameter>
     */
    private function consolidateParameters(): array
    {
        $consolidated = [];
        $seen = [];

        foreach ($this->detectedParams as $param) {
            $key = $param->name;
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $consolidated[] = $param;
            }
        }

        return $consolidated;
    }
}
