<?php

namespace LaravelSpectrum\Support;

use LaravelSpectrum\Analyzers\Support\AstNodeValueExtractor;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;

class QueryParameterDetector
{
    private array $detectedParams = [];

    private array $variableAssignments = [];

    private Parser $parser;

    private AstNodeValueExtractor $valueExtractor;

    public function __construct(Parser $parser, ?AstNodeValueExtractor $valueExtractor = null)
    {
        $this->parser = $parser;
        $this->valueExtractor = $valueExtractor ?? new AstNodeValueExtractor;
    }

    /**
     * Parse method and detect request calls
     */
    public function parseMethod(\ReflectionMethod $method): ?array
    {
        try {
            // Get method source code directly
            $fileName = $method->getFileName();
            $startLine = $method->getStartLine() - 1;
            $endLine = $method->getEndLine();
            $length = $endLine - $startLine;

            if (! $fileName) {
                return null;
            }

            $source = file($fileName);
            $methodSource = implode('', array_slice($source, $startLine, $length));

            // Wrap in a class for parsing
            $code = "<?php\nclass TempClass {\n".$methodSource."\n}";

            $ast = $this->parser->parse($code);
            if (! $ast) {
                return null;
            }

            // Find the method node in our temporary class
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
            return null;
        }
    }

    /**
     * Detect Request method calls from AST
     */
    public function detectRequestCalls(array $ast): array
    {
        $this->detectedParams = [];
        $this->variableAssignments = [];

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new \LaravelSpectrum\Analyzers\AST\Visitors\QueryParameterVisitor($this));

        $traverser->traverse($ast);

        return $this->consolidateParameters();
    }

    /**
     * Process method call on $request
     */
    public function processMethodCall(MethodCall $node): void
    {
        $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if (! $methodName || ! $this->isRequestMethod($methodName)) {
            return;
        }

        $args = $node->args;
        if (empty($args)) {
            return;
        }

        $paramName = $this->valueExtractor->extractStringValue($args[0]->value);
        if (! $paramName) {
            return;
        }

        $param = [
            'name' => $paramName,
            'method' => $methodName,
            'default' => isset($args[1]) ? $this->valueExtractor->extractValue($args[1]->value) : null,
            'context' => [],
        ];

        // Special handling for has() and filled()
        if (in_array($methodName, ['has', 'filled'])) {
            $param['context'][$methodName.'_check'] = true;
        }

        $this->detectedParams[] = $param;
    }

    /**
     * Process static call to Request
     */
    public function processStaticCall(StaticCall $node): void
    {
        $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if (! $methodName || ! $this->isRequestMethod($methodName)) {
            return;
        }

        $args = $node->args;
        if (empty($args)) {
            return;
        }

        $paramName = $this->valueExtractor->extractStringValue($args[0]->value);
        if (! $paramName) {
            return;
        }

        $this->detectedParams[] = [
            'name' => $paramName,
            'method' => $methodName,
            'default' => isset($args[1]) ? $this->valueExtractor->extractValue($args[1]->value) : null,
            'context' => [],
        ];
    }

    /**
     * Process magic property access
     */
    public function processMagicAccess(PropertyFetch $node): void
    {
        $propName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if (! $propName) {
            return;
        }

        $this->detectedParams[] = [
            'name' => $propName,
            'method' => 'magic',
            'default' => null,
            'context' => [],
        ];
    }

    /**
     * Process null coalescing with request property
     */
    public function processCoalesceWithRequest(Node\Expr\BinaryOp\Coalesce $node): void
    {
        if ($node->left instanceof PropertyFetch &&
            $node->left->name instanceof Node\Identifier) {

            $propName = $node->left->name->toString();
            $default = $this->valueExtractor->extractValue($node->right);

            // Check if we already have this parameter
            $found = false;
            foreach ($this->detectedParams as &$param) {
                if ($param['name'] === $propName && $param['method'] === 'magic') {
                    $param['default'] = $default;
                    $found = true;
                    break;
                }
            }

            // If not found, add it
            if (! $found) {
                $this->detectedParams[] = [
                    'name' => $propName,
                    'method' => 'magic',
                    'default' => $default,
                    'context' => [],
                ];
            }
        }
    }

    /**
     * Process in_array calls for enum detection
     */
    public function processInArrayCall(FuncCall $node): void
    {
        if (count($node->args) < 2) {
            return;
        }

        // Check if first argument references a request parameter
        $needle = $node->args[0]->value;
        $paramName = null;

        if ($needle instanceof MethodCall &&
            $needle->var instanceof Variable &&
            $needle->var->name === 'request') {
            $paramName = $this->valueExtractor->extractStringValue($needle->args[0]->value ?? null);
        } elseif ($needle instanceof Variable) {
            // Try to trace back the variable to a request call
            $paramName = $this->traceVariableToRequest($needle->name);
        }

        if (! $paramName) {
            return;
        }

        // Extract enum values from array
        $enumValues = $this->valueExtractor->extractArrayValues($node->args[1]->value);

        if ($enumValues) {
            $this->updateParameterContext($paramName, ['enum_values' => $enumValues]);
        }
    }

    /**
     * Analyze conditional context for parameter usage
     */
    public function analyzeConditionalContext(Node $node): void
    {
        if ($node instanceof If_) {
            // Check if condition involves request has/filled
            if ($node->cond instanceof MethodCall &&
                $node->cond->var instanceof Variable &&
                $node->cond->var->name === 'request') {
                $method = $node->cond->name instanceof Node\Identifier ? $node->cond->name->toString() : null;
                if (in_array($method, ['has', 'filled']) && ! empty($node->cond->args)) {
                    $paramName = $this->valueExtractor->extractStringValue($node->cond->args[0]->value);
                    if ($paramName) {
                        $this->updateParameterContext($paramName, [$method.'_check' => true]);
                    }
                }
            }
        } elseif ($node instanceof Switch_ && $node->cond instanceof Variable) {
            // Check if switch is on a request parameter
            $varName = $node->cond->name;
            $paramName = $this->traceVariableToRequest($varName);

            if ($paramName) {
                // Get existing enum values if any
                $existingEnumValues = [];
                foreach ($this->detectedParams as $param) {
                    if ($param['name'] === $paramName && isset($param['context']['enum_values'])) {
                        $existingEnumValues = $param['context']['enum_values'];
                        break;
                    }
                }

                $enumValues = $existingEnumValues;
                foreach ($node->cases as $case) {
                    if ($case->cond) {
                        $value = $this->valueExtractor->extractValue($case->cond);
                        if ($value !== null && ! in_array($value, $enumValues)) {
                            $enumValues[] = $value;
                        }
                    }
                }
                if (! empty($enumValues)) {
                    $this->updateParameterContext($paramName, ['enum_values' => $enumValues]);
                }
            }
        } elseif ($node instanceof Match_ && $node->cond instanceof Variable) {
            // Check if match is on a request parameter
            $varName = $node->cond->name;
            $paramName = $this->traceVariableToRequest($varName);

            if ($paramName) {
                // Get existing enum values if any
                $existingEnumValues = [];
                foreach ($this->detectedParams as $param) {
                    if ($param['name'] === $paramName && isset($param['context']['enum_values'])) {
                        $existingEnumValues = $param['context']['enum_values'];
                        break;
                    }
                }

                $enumValues = $existingEnumValues;
                foreach ($node->arms as $arm) {
                    if ($arm->conds !== null) {
                        foreach ($arm->conds as $cond) {
                            $value = $this->valueExtractor->extractValue($cond);
                            if ($value !== null && ! in_array($value, $enumValues)) {
                                $enumValues[] = $value;
                            }
                        }
                    }
                }
                if (! empty($enumValues)) {
                    $this->updateParameterContext($paramName, ['enum_values' => $enumValues]);
                }
            }
        }
    }

    /**
     * Check if method name is a Request method we're interested in
     */
    private function isRequestMethod(string $method): bool
    {
        return in_array($method, [
            'input', 'query', 'get', 'post',
            'has', 'filled', 'missing',
            'boolean', 'bool',
            'integer', 'int',
            'float', 'double',
            'string', 'array', 'date',
            'only', 'except', 'all',
        ]);
    }

    /**
     * Track variable assignments
     */
    public function trackVariableAssignment(Node\Expr\Assign $node): void
    {
        if (! ($node->var instanceof Variable)) {
            return;
        }

        $varName = $node->var->name;

        // Check if assignment is from a request method
        if ($node->expr instanceof MethodCall &&
            $node->expr->var instanceof Variable &&
            $node->expr->var->name === 'request') {

            $methodName = $node->expr->name instanceof Node\Identifier ? $node->expr->name->toString() : null;
            if ($methodName && $this->isRequestMethod($methodName) && ! empty($node->expr->args)) {
                $paramName = $this->valueExtractor->extractStringValue($node->expr->args[0]->value);
                if ($paramName) {
                    $this->variableAssignments[$varName] = $paramName;
                }
            }
        }

        // Check if assignment is from a request property (magic access)
        if ($node->expr instanceof PropertyFetch &&
            $node->expr->var instanceof Variable &&
            $node->expr->var->name === 'request' &&
            $node->expr->name instanceof Node\Identifier) {

            $this->variableAssignments[$varName] = $node->expr->name->toString();
        }
    }

    /**
     * Trace variable back to request call
     */
    private function traceVariableToRequest(string $varName): ?string
    {
        // Check our tracked assignments
        if (isset($this->variableAssignments[$varName])) {
            return $this->variableAssignments[$varName];
        }

        return null;
    }

    /**
     * Update parameter context
     */
    private function updateParameterContext(string $paramName, array $context): void
    {
        foreach ($this->detectedParams as &$param) {
            if ($param['name'] === $paramName) {
                $param['context'] = array_merge($param['context'], $context);
            }
        }
    }

    /**
     * Consolidate detected parameters
     */
    private function consolidateParameters(): array
    {
        $consolidated = [];
        $seen = [];

        foreach ($this->detectedParams as $param) {
            $key = $param['name'];

            if (! isset($seen[$key])) {
                $seen[$key] = $param;
            } else {
                // Merge contexts and keep most specific type
                $seen[$key]['context'] = array_merge($seen[$key]['context'], $param['context']);

                // Prefer typed methods over generic ones
                if ($this->isTypedMethod($param['method']) && ! $this->isTypedMethod($seen[$key]['method'])) {
                    $seen[$key]['method'] = $param['method'];
                }

                // Keep default value if one exists
                if ($param['default'] !== null && $seen[$key]['default'] === null) {
                    $seen[$key]['default'] = $param['default'];
                }
            }
        }

        return array_values($seen);
    }

    /**
     * Check if method is a typed method (returns specific type)
     */
    private function isTypedMethod(string $method): bool
    {
        return in_array($method, ['boolean', 'bool', 'integer', 'int', 'float', 'double', 'string', 'array', 'date']);
    }
}
