<?php

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\Support\ErrorCollector;
use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Analyzes anonymous FormRequest classes using reflection and AST parsing.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 * Handles cases where the FormRequest class is anonymous or when the file path is not available.
 */
class AnonymousClassAnalyzer
{
    protected Parser $parser;

    public function __construct(
        protected FormRequestAstExtractor $astExtractor,
        protected ParameterBuilder $parameterBuilder,
        protected ?ErrorCollector $errorCollector = null,
        ?Parser $parser = null
    ) {
        $this->parser = $parser ?? (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * Analyze an anonymous FormRequest class using AST parsing with reflection fallback.
     *
     * First attempts AST-based parsing of the anonymous class source code.
     * Falls back to reflection-based extraction if AST parsing fails.
     *
     * @param  \ReflectionClass  $reflection  The reflection of the anonymous class
     * @return array<array> The built parameters
     */
    public function analyze(\ReflectionClass $reflection): array
    {
        try {
            // For AST parsing of anonymous classes, we need to get the source code
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
            $this->errorCollector?->addWarning(
                'AnonymousClassAnalyzer',
                "Failed to analyze anonymous FormRequest (unable to create instance or invoke methods): {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $reflection->getFileName() ?: 'unknown',
                    'error_type' => 'anonymous_reflection_analysis_error',
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
     * @param  \ReflectionClass  $reflection  The reflection of the anonymous class
     * @return array{rules: array, attributes: array, messages: array} The extracted details
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
            $this->errorCollector?->addWarning(
                'AnonymousClassAnalyzer',
                "Failed to extract rules/attributes/messages from anonymous FormRequest using reflection: {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $reflection->getFileName() ?: 'unknown',
                    'error_type' => 'anonymous_reflection_details_error',
                ]
            );

            return ['rules' => [], 'attributes' => [], 'messages' => []];
        }
    }

    /**
     * Analyze an anonymous FormRequest class with conditional rules support.
     *
     * First attempts AST-based parsing to extract conditional rules.
     * Falls back to standard analysis if AST parsing fails.
     *
     * @param  \ReflectionClass  $reflection  The reflection of the anonymous class
     * @return array{parameters: array, conditional_rules: array} The analysis result
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
            $this->errorCollector?->addWarning(
                'AnonymousClassAnalyzer',
                "Failed to analyze anonymous FormRequest with conditional rules (unable to create instance or invoke methods): {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $reflection->getFileName() ?: 'unknown',
                    'error_type' => 'anonymous_conditional_reflection_error',
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
     * @return array|null Returns the parameters if successful, null if AST parsing fails
     */
    protected function tryAstBasedAnalysis(\ReflectionClass $reflection, string $filePath): ?array
    {
        $classCode = $this->extractAnonymousClassCode($reflection, $filePath);

        try {
            $ast = $this->parser->parse($classCode);
            if ($ast) {
                $classNode = $this->astExtractor->findAnonymousClassNode($ast);
                if ($classNode) {
                    $rules = $this->astExtractor->extractRules($classNode);
                    $attributes = $this->astExtractor->extractAttributes($classNode);
                    $namespace = $reflection->getNamespaceName();
                    $useStatements = $this->astExtractor->extractUseStatements($ast);

                    return $this->parameterBuilder->buildFromRules($rules, $attributes, $namespace, $useStatements);
                }
            }
        } catch (Error $e) {
            $this->errorCollector?->addWarning(
                'AnonymousClassAnalyzer',
                "Failed to parse anonymous class AST, falling back to reflection: {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $filePath,
                    'error_type' => 'anonymous_ast_parse_error',
                ]
            );
        }

        return null;
    }

    /**
     * Try to analyze conditional rules using AST parsing.
     *
     * @return array|null Returns the result if successful, null if AST parsing fails
     */
    protected function tryConditionalRulesAstAnalysis(\ReflectionClass $reflection, string $filePath): ?array
    {
        $classCode = $this->extractAnonymousClassCode($reflection, $filePath);

        try {
            $ast = $this->parser->parse($classCode);
            if ($ast) {
                $classNode = $this->astExtractor->findAnonymousClassNode($ast);
                if ($classNode) {
                    $conditionalRules = $this->astExtractor->extractConditionalRules($classNode);

                    if (! empty($conditionalRules['rules_sets'])) {
                        $attributes = $this->astExtractor->extractAttributes($classNode);
                        $parameters = $this->parameterBuilder->buildFromConditionalRules($conditionalRules, $attributes);

                        return [
                            'parameters' => $parameters,
                            'conditional_rules' => $conditionalRules,
                        ];
                    }
                }
            }
        } catch (Error $e) {
            $this->errorCollector?->addWarning(
                'AnonymousClassAnalyzer',
                "Failed to parse anonymous class AST for conditional rules, falling back to reflection: {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $filePath,
                    'error_type' => 'anonymous_conditional_ast_parse_error',
                ]
            );
        }

        return null;
    }

    /**
     * Extract the anonymous class source code from the file.
     */
    protected function extractAnonymousClassCode(\ReflectionClass $reflection, string $filePath): string
    {
        $code = file_get_contents($filePath);
        $startLine = $reflection->getStartLine() - 1;
        $endLine = $reflection->getEndLine();

        $lines = explode("\n", $code);
        $classCode = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));

        if (! str_contains($classCode, '<?php')) {
            $classCode = "<?php\n".$classCode;
        }

        return $classCode;
    }

    /**
     * Extract parameters using reflection-based extraction.
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
     * @param  bool  $criticalForAnalysis  If true, failure returns null to signal early exit
     * @return array|null Returns the method result, empty array default, or null on critical failure
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
                $this->errorCollector?->addWarning(
                    'AnonymousClassAnalyzer',
                    "Failed to invoke {$methodName}() method on anonymous FormRequest: {$e->getMessage()}",
                    [
                        'class_name' => $reflection->getName(),
                        'file_path' => $reflection->getFileName() ?: 'unknown',
                        'method' => $methodName,
                        'error_type' => 'anonymous_method_invocation_error',
                    ]
                );

                return null;
            }

            return $default;
        }
    }
}
