<?php

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\Support\ErrorCollector;
use PhpParser\Error;
use PhpParser\Parser;

/**
 * Analyzes anonymous FormRequest classes using reflection and AST parsing.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 * Handles cases where the FormRequest class is anonymous or when the file path is not available.
 *
 * Analysis Strategy:
 * 1. Attempt AST-based parsing when source file is available (most accurate)
 * 2. Fall back to reflection-based extraction if AST parsing fails
 *
 * Main public methods:
 * - analyze(): Extract parameters with AST fallback to reflection
 * - analyzeDetails(): Extract raw rules, attributes, and messages
 * - analyzeWithConditionalRules(): Handle conditional validation rules
 *
 * @see FormRequestAnalyzer The main analyzer that delegates anonymous class handling to this class
 */
class AnonymousClassAnalyzer
{
    protected Parser $parser;

    protected ErrorCollector $errorCollector;

    /**
     * @param  Parser  $parser  PHP-Parser instance for AST parsing
     * @param  FormRequestAstExtractor  $astExtractor  AST extractor for parsing PHP code
     * @param  ParameterBuilder  $parameterBuilder  Builder for creating parameter definitions
     * @param  ErrorCollector|null  $errorCollector  Collector for warnings/errors (created if not provided)
     */
    public function __construct(
        Parser $parser,
        protected FormRequestAstExtractor $astExtractor,
        protected ParameterBuilder $parameterBuilder,
        ?ErrorCollector $errorCollector = null
    ) {
        $this->parser = $parser;
        $this->errorCollector = $errorCollector ?? new ErrorCollector;
    }

    /**
     * Analyze an anonymous FormRequest class using AST parsing with reflection fallback.
     *
     * First attempts AST-based parsing of the anonymous class source code.
     * Falls back to reflection-based extraction if AST parsing fails.
     *
     * @param  \ReflectionClass<object>  $reflection  The reflection of the anonymous class
     * @return array<int, array<string, mixed>> The built parameters
     */
    public function analyze(\ReflectionClass $reflection): array
    {
        try {
            // Attempt AST-based parsing when source file is available
            $filePath = $reflection->getFileName();
            if ($filePath && file_exists($filePath)) {
                $result = $this->tryAstBasedAnalysis($reflection, $filePath);
                if ($result !== null) {
                    return $result;
                }
            }

            // Fallback to reflection-based extraction
            return $this->extractParametersUsingReflection($reflection);

        } catch (\Exception $e) {
            $this->errorCollector->addError(
                'AnonymousClassAnalyzer',
                "Failed to analyze anonymous FormRequest (unable to create instance or invoke methods): {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $reflection->getFileName() ?: 'unknown',
                    'error_type' => 'anonymous_reflection_analysis_error',
                    'exception_class' => get_class($e),
                ]
            );

            return [];
        }
    }

    /**
     * Analyze an anonymous FormRequest class to extract detailed information.
     *
     * Uses pure reflection to extract rules, attributes, and messages.
     * Returns raw data without building parameters.
     *
     * @param  \ReflectionClass<object>  $reflection  The reflection of the anonymous class
     * @return array{rules: array<string, mixed>, attributes: array<string, string>, messages: array<string, string>} The extracted details
     */
    public function analyzeDetails(\ReflectionClass $reflection): array
    {
        try {
            $instance = $this->createMockInstance($reflection);

            $rules = $this->invokeMethodSafely($reflection, $instance, 'rules', []);
            if ($rules === null) {
                return ['rules' => [], 'attributes' => [], 'messages' => []];
            }

            $attributes = $this->invokeMethodSafely($reflection, $instance, 'attributes', [], false);
            $messages = $this->invokeMethodSafely($reflection, $instance, 'messages', [], false);

            return [
                'rules' => $rules,
                'attributes' => $attributes ?? [],
                'messages' => $messages ?? [],
            ];

        } catch (\Exception $e) {
            $this->errorCollector->addError(
                'AnonymousClassAnalyzer',
                "Failed to extract rules/attributes/messages from anonymous FormRequest using reflection: {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $reflection->getFileName() ?: 'unknown',
                    'error_type' => 'anonymous_reflection_details_error',
                    'exception_class' => get_class($e),
                ]
            );

            return ['rules' => [], 'attributes' => [], 'messages' => []];
        }
    }

    /**
     * Analyze an anonymous FormRequest class with conditional rules support.
     *
     * First attempts AST-based parsing to extract conditional rules.
     * Falls back to standard analysis if AST parsing fails or no conditional rules found.
     *
     * @param  \ReflectionClass<object>  $reflection  The reflection of the anonymous class
     * @return array{parameters: array<int, array<string, mixed>>, conditional_rules: array{rules_sets: array<mixed>, merged_rules: array<string, mixed>}} The analysis result
     */
    public function analyzeWithConditionalRules(\ReflectionClass $reflection): array
    {
        try {
            $filePath = $reflection->getFileName();
            if ($filePath && file_exists($filePath)) {
                $result = $this->tryConditionalRulesAstAnalysis($reflection, $filePath);
                if ($result !== null) {
                    return $result;
                }

                // Log that conditional rules AST analysis did not produce results
                $this->errorCollector->addWarning(
                    'AnonymousClassAnalyzer',
                    'Conditional rules AST analysis returned no results, using standard analysis',
                    [
                        'file_path' => $filePath,
                        'class_name' => $reflection->getName(),
                        'error_type' => 'anonymous_conditional_rules_fallback',
                    ]
                );
            }

            // Fallback to standard analysis
            $result = $this->analyze($reflection);

            return [
                'parameters' => $result,
                'conditional_rules' => [
                    'rules_sets' => [],
                    'merged_rules' => [],
                ],
            ];

        } catch (\Exception $e) {
            $this->errorCollector->addError(
                'AnonymousClassAnalyzer',
                "Failed to analyze anonymous FormRequest with conditional rules (unable to create instance or invoke methods): {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $reflection->getFileName() ?: 'unknown',
                    'error_type' => 'anonymous_conditional_reflection_error',
                    'exception_class' => get_class($e),
                ]
            );

            return [
                'parameters' => [],
                'conditional_rules' => [
                    'rules_sets' => [],
                    'merged_rules' => [],
                ],
            ];
        }
    }

    /**
     * Try to analyze the anonymous class using AST parsing.
     *
     * @param  \ReflectionClass<object>  $reflection  The reflection of the anonymous class
     * @param  string  $filePath  Path to the file containing the class
     * @return array<int, array<string, mixed>>|null Returns the parameters if successful, null if AST parsing fails
     */
    protected function tryAstBasedAnalysis(\ReflectionClass $reflection, string $filePath): ?array
    {
        $classCode = $this->extractAnonymousClassCode($reflection, $filePath);
        if ($classCode === null) {
            return null;
        }

        try {
            $ast = $this->parser->parse($classCode);
            if (! $ast) {
                $this->errorCollector->addWarning(
                    'AnonymousClassAnalyzer',
                    'Parser returned null AST, falling back to reflection',
                    [
                        'file_path' => $filePath,
                        'class_name' => $reflection->getName(),
                        'error_type' => 'anonymous_ast_null_result',
                    ]
                );

                return null;
            }

            $classNode = $this->astExtractor->findAnonymousClassNode($ast);
            if (! $classNode) {
                $this->errorCollector->addWarning(
                    'AnonymousClassAnalyzer',
                    'Anonymous class node not found in AST, falling back to reflection',
                    [
                        'file_path' => $filePath,
                        'class_name' => $reflection->getName(),
                        'error_type' => 'anonymous_class_node_not_found',
                    ]
                );

                return null;
            }

            $rules = $this->astExtractor->extractRules($classNode);
            $attributes = $this->astExtractor->extractAttributes($classNode);
            $namespace = $reflection->getNamespaceName();
            $useStatements = $this->astExtractor->extractUseStatements($ast);

            return $this->parameterBuilder->buildFromRules($rules, $attributes, $namespace, $useStatements);
        } catch (Error $e) {
            $this->errorCollector->addWarning(
                'AnonymousClassAnalyzer',
                "Failed to parse anonymous class AST, falling back to reflection: {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $filePath,
                    'error_type' => 'anonymous_ast_parse_error',
                    'exception_class' => get_class($e),
                ]
            );
        }

        return null;
    }

    /**
     * Try to analyze conditional rules using AST parsing.
     *
     * @param  \ReflectionClass<object>  $reflection  The reflection of the anonymous class
     * @param  string  $filePath  Path to the file containing the class
     * @return array{parameters: array<int, array<string, mixed>>, conditional_rules: array{rules_sets: array<mixed>, merged_rules: array<string, mixed>}}|null Returns the result if successful, null if AST parsing fails
     */
    protected function tryConditionalRulesAstAnalysis(\ReflectionClass $reflection, string $filePath): ?array
    {
        $classCode = $this->extractAnonymousClassCode($reflection, $filePath);
        if ($classCode === null) {
            return null;
        }

        try {
            $ast = $this->parser->parse($classCode);
            if (! $ast) {
                $this->errorCollector->addWarning(
                    'AnonymousClassAnalyzer',
                    'Parser returned null AST for conditional rules, falling back to standard analysis',
                    [
                        'file_path' => $filePath,
                        'class_name' => $reflection->getName(),
                        'error_type' => 'anonymous_conditional_ast_null_result',
                    ]
                );

                return null;
            }

            $classNode = $this->astExtractor->findAnonymousClassNode($ast);
            if (! $classNode) {
                $this->errorCollector->addWarning(
                    'AnonymousClassAnalyzer',
                    'Anonymous class node not found for conditional rules, falling back to standard analysis',
                    [
                        'file_path' => $filePath,
                        'class_name' => $reflection->getName(),
                        'error_type' => 'anonymous_conditional_class_node_not_found',
                    ]
                );

                return null;
            }

            $conditionalRules = $this->astExtractor->extractConditionalRules($classNode);

            if (! empty($conditionalRules['rules_sets'])) {
                $attributes = $this->astExtractor->extractAttributes($classNode);
                $parameters = $this->parameterBuilder->buildFromConditionalRules($conditionalRules, $attributes);

                return [
                    'parameters' => $parameters,
                    'conditional_rules' => $conditionalRules,
                ];
            }

            // No conditional rules found - will fall back to standard analysis
            return null;
        } catch (Error $e) {
            $this->errorCollector->addWarning(
                'AnonymousClassAnalyzer',
                "Failed to parse anonymous class AST for conditional rules, falling back to reflection: {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $filePath,
                    'error_type' => 'anonymous_conditional_ast_parse_error',
                    'exception_class' => get_class($e),
                ]
            );
        }

        return null;
    }

    /**
     * Extract the anonymous class source code from the file.
     *
     * @param  \ReflectionClass<object>  $reflection  The reflection of the anonymous class
     * @param  string  $filePath  Path to the file containing the class
     * @return string|null PHP code wrapped with <?php tag for parsing, or null on failure
     */
    protected function extractAnonymousClassCode(\ReflectionClass $reflection, string $filePath): ?string
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            $this->errorCollector->addError(
                'AnonymousClassAnalyzer',
                'Failed to read file contents for anonymous class analysis',
                [
                    'file_path' => $filePath,
                    'class_name' => $reflection->getName(),
                    'error_type' => 'anonymous_file_read_error',
                ]
            );

            return null;
        }

        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if ($startLine === false || $endLine === false) {
            $this->errorCollector->addWarning(
                'AnonymousClassAnalyzer',
                'Line information not available for anonymous class',
                [
                    'file_path' => $filePath,
                    'class_name' => $reflection->getName(),
                    'error_type' => 'anonymous_line_info_unavailable',
                ]
            );

            return null;
        }

        $lines = explode("\n", $code);
        $classCode = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        if (! str_contains($classCode, '<?php')) {
            $classCode = "<?php\n".$classCode;
        }

        return $classCode;
    }

    /**
     * Extract parameters using reflection-based extraction.
     *
     * Falls back to this approach when AST parsing is not possible.
     *
     * @param  \ReflectionClass<object>  $reflection  The reflection of the anonymous class
     * @return array<int, array<string, mixed>> The built parameters or empty array on failure
     *
     * @throws \ReflectionException If instance creation fails
     */
    protected function extractParametersUsingReflection(\ReflectionClass $reflection): array
    {
        $instance = $this->createMockInstance($reflection);

        $rules = $this->invokeMethodSafely($reflection, $instance, 'rules', []);
        if ($rules === null) {
            return [];
        }

        $attributes = $this->invokeMethodSafely($reflection, $instance, 'attributes', [], false);
        $namespace = $reflection->getNamespaceName();

        return $this->parameterBuilder->buildFromRules($rules, $attributes ?? [], $namespace);
    }

    /**
     * Create a mock instance of the FormRequest for reflection-based extraction.
     *
     * Initializes the internal Request property to prevent errors during method invocation.
     *
     * WARNING: This creates an instance without constructor and injects an empty
     * Request object. FormRequest classes that depend on request data in their
     * rules() method may return unexpected results.
     *
     * @param  \ReflectionClass<object>  $reflection  The reflection of the class to instantiate
     * @return object The created instance with mock Request initialized
     *
     * @throws \ReflectionException If instance creation fails
     */
    protected function createMockInstance(\ReflectionClass $reflection): object
    {
        $instance = $reflection->newInstanceWithoutConstructor();

        if ($reflection->hasProperty('request')) {
            $requestProp = $reflection->getProperty('request');
            $requestProp->setAccessible(true);
            $mockRequest = new \Illuminate\Http\Request;
            $requestProp->setValue($instance, $mockRequest);
        }

        return $instance;
    }

    /**
     * Safely invoke a method on an instance using reflection.
     *
     * @param  \ReflectionClass<object>  $reflection  The reflection class to check for method existence
     * @param  object  $instance  The instance to invoke the method on
     * @param  string  $methodName  The name of the method to invoke
     * @param  array<string, mixed>  $default  Default value to return if method doesn't exist or returns falsy value
     * @param  bool  $criticalForAnalysis  If true, failure returns null to signal early exit
     * @return array<string, mixed>|null Returns the method result, empty array default, or null on critical failure
     */
    protected function invokeMethodSafely(
        \ReflectionClass $reflection,
        object $instance,
        string $methodName,
        array $default = [],
        bool $criticalForAnalysis = true
    ): ?array {
        if (! $reflection->hasMethod($methodName)) {
            return $default;
        }

        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        try {
            return $method->invoke($instance) ?: $default;
        } catch (\Exception $e) {
            if ($criticalForAnalysis) {
                $this->errorCollector->addError(
                    'AnonymousClassAnalyzer',
                    "Failed to invoke {$methodName}() method on anonymous FormRequest: {$e->getMessage()}",
                    [
                        'class_name' => $reflection->getName(),
                        'file_path' => $reflection->getFileName() ?: 'unknown',
                        'method' => $methodName,
                        'error_type' => 'anonymous_method_invocation_error',
                        'exception_class' => get_class($e),
                    ]
                );

                return null;
            }

            // Log non-critical failures as warnings for debugging
            $this->errorCollector->addWarning(
                'AnonymousClassAnalyzer',
                "Non-critical method {$methodName}() failed, using default value: {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $reflection->getFileName() ?: 'unknown',
                    'method' => $methodName,
                    'error_type' => 'anonymous_non_critical_method_failure',
                    'exception_class' => get_class($e),
                ]
            );

            return $default;
        }
    }
}
