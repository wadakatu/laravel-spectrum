<?php

namespace LaravelSpectrum\Analyzers;

use Illuminate\Support\Facades\Log;
use LaravelSpectrum\Analyzers\Support\FormatInferrer;
use LaravelSpectrum\Analyzers\Support\FormRequestAstExtractor;
use LaravelSpectrum\Analyzers\Support\ParameterBuilder;
use LaravelSpectrum\Analyzers\Support\RuleRequirementAnalyzer;
use LaravelSpectrum\Analyzers\Support\ValidationDescriptionGenerator;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\TypeInference;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

class FormRequestAnalyzer
{
    protected TypeInference $typeInference;

    protected Parser $parser;

    protected NodeTraverser $traverser;

    protected PrettyPrinter\Standard $printer;

    protected DocumentationCache $cache;

    protected EnumAnalyzer $enumAnalyzer;

    protected FileUploadAnalyzer $fileUploadAnalyzer;

    protected ?ErrorCollector $errorCollector = null;

    protected RuleRequirementAnalyzer $ruleRequirementAnalyzer;

    protected FormatInferrer $formatInferrer;

    protected ValidationDescriptionGenerator $descriptionGenerator;

    protected ParameterBuilder $parameterBuilder;

    protected FormRequestAstExtractor $astExtractor;

    public function __construct(TypeInference $typeInference, DocumentationCache $cache, ?EnumAnalyzer $enumAnalyzer = null, ?FileUploadAnalyzer $fileUploadAnalyzer = null, ?ErrorCollector $errorCollector = null, ?RuleRequirementAnalyzer $ruleRequirementAnalyzer = null, ?FormatInferrer $formatInferrer = null, ?ValidationDescriptionGenerator $descriptionGenerator = null, ?ParameterBuilder $parameterBuilder = null, ?FormRequestAstExtractor $astExtractor = null)
    {
        $this->typeInference = $typeInference;
        $this->cache = $cache;
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser;
        $this->printer = new PrettyPrinter\Standard;
        $this->enumAnalyzer = $enumAnalyzer ?? new EnumAnalyzer;
        $this->fileUploadAnalyzer = $fileUploadAnalyzer ?? new FileUploadAnalyzer;
        $this->errorCollector = $errorCollector;
        $this->ruleRequirementAnalyzer = $ruleRequirementAnalyzer ?? new RuleRequirementAnalyzer;
        $this->formatInferrer = $formatInferrer ?? new FormatInferrer;
        $this->descriptionGenerator = $descriptionGenerator ?? new ValidationDescriptionGenerator($this->enumAnalyzer);
        $this->astExtractor = $astExtractor ?? new FormRequestAstExtractor($this->printer);
        $this->parameterBuilder = $parameterBuilder ?? new ParameterBuilder(
            $this->typeInference,
            $this->ruleRequirementAnalyzer,
            $this->formatInferrer,
            $this->descriptionGenerator,
            $this->enumAnalyzer,
            $this->fileUploadAnalyzer,
            $this->errorCollector
        );
    }

    /**
     * FormRequestクラスを解析してパラメータ情報を抽出
     */
    public function analyze(string $requestClass): array
    {
        try {
            return $this->cache->rememberFormRequest($requestClass, function () use ($requestClass) {
                return $this->performAnalysis($requestClass);
            });
        } catch (\Exception $e) {
            $this->errorCollector?->addError(
                'FormRequestAnalyzer',
                "Failed to analyze {$requestClass}: {$e->getMessage()}",
                [
                    'class' => $requestClass,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            // エラーが発生しても空の配列を返して処理を継続
            return [];
        }
    }

    /**
     * 実際の解析処理
     */
    protected function performAnalysis(string $requestClass): array
    {
        if (! class_exists($requestClass)) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($requestClass);

            // FormRequestを継承していない場合はスキップ
            if (! $reflection->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class)) {
                return [];
            }

            $filePath = $reflection->getFileName();
            if (! $filePath || ! file_exists($filePath) || $reflection->isAnonymous()) {
                // For anonymous classes or when file path is not available, use reflection-based fallback
                return $this->analyzeWithReflection($reflection);
            }

            // ファイルをパース
            $code = file_get_contents($filePath);
            $ast = $this->parser->parse($code);

            if (! $ast) {
                return [];
            }

            // Extract use statements
            $useStatements = $this->astExtractor->extractUseStatements($ast);

            // クラスノードを探す
            $classNode = $this->astExtractor->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                return [];
            }

            // rules()メソッドを解析
            $rules = $this->astExtractor->extractRules($classNode);

            // attributes()メソッドを解析
            $attributes = $this->astExtractor->extractAttributes($classNode);

            // Get namespace for enum resolution
            $namespace = $reflection->getNamespaceName();

            // パラメータ情報を生成
            return $this->parameterBuilder->buildFromRules($rules, $attributes, $namespace, $useStatements);

        } catch (Error $parseError) {
            $this->errorCollector?->addError(
                'FormRequestAnalyzer',
                "Failed to parse FormRequest {$requestClass}: {$parseError->getMessage()}",
                [
                    'class' => $requestClass,
                    'error_type' => 'parse_error',
                ]
            );

            Log::warning("Failed to parse FormRequest: {$requestClass}", [
                'error' => $parseError->getMessage(),
            ]);

            return [];
        } catch (\Exception $e) {
            $this->errorCollector?->addError(
                'FormRequestAnalyzer',
                "Failed to analyze FormRequest {$requestClass}: {$e->getMessage()}",
                [
                    'class' => $requestClass,
                    'error_type' => 'analysis_error',
                ]
            );

            Log::warning("Failed to analyze FormRequest: {$requestClass}", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Analyze with conditional rules detection
     */
    public function analyzeWithConditionalRules(string $requestClass): array
    {
        return $this->cache->rememberFormRequest($requestClass.':conditional', function () use ($requestClass) {
            try {
                $reflection = new \ReflectionClass($requestClass);

                if (! $reflection->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class)) {
                    return [
                        'parameters' => [],
                        'conditional_rules' => ['rules_sets' => [], 'merged_rules' => []],
                    ];
                }

                $filePath = $reflection->getFileName();
                if (! $filePath || ! file_exists($filePath)) {
                    // For classes without file path, return empty conditional rules
                    return [
                        'parameters' => $this->analyzeWithReflection($reflection),
                        'conditional_rules' => ['rules_sets' => [], 'merged_rules' => []],
                        'attributes' => [],
                        'messages' => [],
                    ];
                }

                $code = file_get_contents($filePath);
                $ast = $this->parser->parse($code);

                if (! $ast) {
                    return [
                        'parameters' => [],
                        'conditional_rules' => ['rules_sets' => [], 'merged_rules' => []],
                    ];
                }

                // Extract use statements
                $useStatements = $this->astExtractor->extractUseStatements($ast);

                // Find class node
                $classNode = $this->astExtractor->findClassNode($ast, $reflection->getShortName());
                if (! $classNode) {
                    return [
                        'parameters' => [],
                        'conditional_rules' => ['rules_sets' => [], 'merged_rules' => []],
                    ];
                }

                // Extract conditional rules
                $conditionalRules = $this->astExtractor->extractConditionalRules($classNode);

                // Generate parameters from conditional rules
                $attributes = $this->astExtractor->extractAttributes($classNode);
                $messages = $this->astExtractor->extractMessages($classNode);

                if (! empty($conditionalRules['rules_sets'])) {
                    $parameters = $this->parameterBuilder->buildFromConditionalRules(
                        $conditionalRules,
                        $attributes,
                        $reflection->getNamespaceName(),
                        $useStatements
                    );

                    return [
                        'parameters' => $parameters,
                        'conditional_rules' => $conditionalRules,
                        'attributes' => $attributes,
                        'messages' => $messages,
                    ];
                }

                // Fallback to regular analysis
                $rules = $this->astExtractor->extractRules($classNode);
                $parameters = $this->parameterBuilder->buildFromRules($rules, $attributes, $reflection->getNamespaceName(), $useStatements);

                return [
                    'parameters' => $parameters,
                    'conditional_rules' => ['rules_sets' => [], 'merged_rules' => []],
                    'attributes' => $attributes,
                    'messages' => $messages,
                ];

            } catch (\Exception $e) {
                $this->errorCollector?->addError(
                    'FormRequestAnalyzer',
                    "Failed to analyze FormRequest with conditions {$requestClass}: {$e->getMessage()}",
                    [
                        'class' => $requestClass,
                        'error_type' => 'conditional_analysis_error',
                    ]
                );

                \Illuminate\Support\Facades\Log::warning("Failed to analyze FormRequest with conditions: {$requestClass}", [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'parameters' => [],
                    'conditional_rules' => ['rules_sets' => [], 'merged_rules' => []],
                ];
            }
        });
    }

    /**
     * FormRequestクラスを解析して詳細情報を取得
     */
    public function analyzeWithDetails(string $requestClass): array
    {
        return $this->cache->rememberFormRequest($requestClass, function () use ($requestClass) {
            return $this->performAnalysisWithDetails($requestClass);
        });
    }

    /**
     * 実際の詳細解析処理
     */
    protected function performAnalysisWithDetails(string $requestClass): array
    {
        if (! class_exists($requestClass)) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($requestClass);

            // FormRequestを継承していない場合はスキップ
            if (! $reflection->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class)) {
                return [];
            }

            $filePath = $reflection->getFileName();
            if (! $filePath || ! file_exists($filePath) || $reflection->isAnonymous()) {
                // For anonymous classes or when file path is not available, use reflection-based fallback
                return $this->analyzeWithDetailsUsingReflection($reflection);
            }

            // ファイルをパース
            $code = file_get_contents($filePath);
            $ast = $this->parser->parse($code);

            if (! $ast) {
                return [];
            }

            // クラスノードを探す
            $classNode = $this->astExtractor->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                return [];
            }

            // rules()メソッドを解析
            $rules = $this->astExtractor->extractRules($classNode);

            // attributes()メソッドを解析
            $attributes = $this->astExtractor->extractAttributes($classNode);

            // messages()メソッドを解析
            $messages = $this->astExtractor->extractMessages($classNode);

            return [
                'rules' => $rules,
                'attributes' => $attributes,
                'messages' => $messages,
            ];

        } catch (\Exception $e) {
            $this->errorCollector?->addError(
                'FormRequestAnalyzer',
                "Failed to analyze FormRequest with details {$requestClass}: {$e->getMessage()}",
                [
                    'class' => $requestClass,
                    'error_type' => 'details_analysis_error',
                ]
            );

            Log::warning("Failed to analyze FormRequest with details: {$requestClass}", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Reflection-based analysis with details for anonymous classes
     */
    protected function analyzeWithDetailsUsingReflection(\ReflectionClass $reflection): array
    {
        try {
            // Fallback: try to create instance with mock request
            $instance = $reflection->newInstanceWithoutConstructor();

            // Initialize request property to avoid errors
            if ($reflection->hasProperty('request')) {
                $requestProp = $reflection->getProperty('request');
                $requestProp->setAccessible(true);
                $mockRequest = new \Illuminate\Http\Request;
                $requestProp->setValue($instance, $mockRequest);
            }

            // Extract rules using reflection
            $rules = [];
            if ($reflection->hasMethod('rules')) {
                $method = $reflection->getMethod('rules');
                $method->setAccessible(true);
                try {
                    $rules = $method->invoke($instance) ?: [];
                } catch (\Exception $e) {
                    $this->errorCollector?->addWarning(
                        'FormRequestAnalyzer',
                        "Failed to invoke rules() method on anonymous FormRequest: {$e->getMessage()}",
                        [
                            'class_name' => $reflection->getName(),
                            'file_path' => $reflection->getFileName() ?: 'unknown',
                            'method' => 'rules',
                            'error_type' => 'anonymous_rules_invocation_error',
                        ]
                    );

                    return [];
                }
            }

            // Extract attributes using reflection
            $attributes = [];
            if ($reflection->hasMethod('attributes')) {
                $method = $reflection->getMethod('attributes');
                $method->setAccessible(true);
                $attributes = $method->invoke($instance) ?: [];
            }

            // Extract messages using reflection
            $messages = [];
            if ($reflection->hasMethod('messages')) {
                $method = $reflection->getMethod('messages');
                $method->setAccessible(true);
                $messages = $method->invoke($instance) ?: [];
            }

            return [
                'rules' => $rules,
                'attributes' => $attributes,
                'messages' => $messages,
            ];

        } catch (\Exception $e) {
            $this->errorCollector?->addWarning(
                'FormRequestAnalyzer',
                "Failed to extract rules/attributes/messages from anonymous FormRequest using reflection: {$e->getMessage()}",
                [
                    'class_name' => $reflection->getName(),
                    'file_path' => $reflection->getFileName() ?: 'unknown',
                    'error_type' => 'anonymous_reflection_details_error',
                ]
            );

            return [];
        }
    }

    /**
     * Generate parameters from runtime rules (when using reflection on anonymous classes)
     */
    protected function generateParametersFromRuntimeRules(array $rules, array $attributes = [], ?string $namespace = null): array
    {
        // This method handles rules that contain actual objects (not AST strings)
        // It's used when we extract rules via reflection instead of AST parsing
        return $this->parameterBuilder->buildFromRules($rules, $attributes, $namespace);
    }

    /**
     * Reflection-based analysis for anonymous classes
     */
    protected function analyzeWithReflection(\ReflectionClass $reflection): array
    {
        try {
            // For AST parsing of anonymous classes, we need to get the source code
            $filePath = $reflection->getFileName();
            if ($filePath && file_exists($filePath)) {
                // Read the file and find the anonymous class definition
                $code = file_get_contents($filePath);
                $startLine = $reflection->getStartLine() - 1;
                $endLine = $reflection->getEndLine();

                // Extract just the class definition
                $lines = explode("\n", $code);
                $classCode = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));

                // Wrap in <?php tag for parsing
                if (! str_contains($classCode, '<?php')) {
                    $classCode = "<?php\n".$classCode;
                }

                try {
                    $ast = $this->parser->parse($classCode);
                    if ($ast) {
                        // Find the class node (anonymous class)
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
                    // Fall back to instance-based extraction
                    $this->errorCollector?->addWarning(
                        'FormRequestAnalyzer',
                        "Failed to parse anonymous class AST, falling back to reflection: {$e->getMessage()}",
                        [
                            'class_name' => $reflection->getName(),
                            'file_path' => $filePath,
                            'error_type' => 'anonymous_ast_parse_error',
                        ]
                    );
                }
            }

            // Fallback: try to create instance with mock request
            $instance = $reflection->newInstanceWithoutConstructor();

            // Initialize request property to avoid errors
            if ($reflection->hasProperty('request')) {
                $requestProp = $reflection->getProperty('request');
                $requestProp->setAccessible(true);
                $mockRequest = new \Illuminate\Http\Request;
                $requestProp->setValue($instance, $mockRequest);
            }

            // Extract rules using reflection - this will get actual objects, not AST strings
            $rules = [];
            if ($reflection->hasMethod('rules')) {
                $method = $reflection->getMethod('rules');
                $method->setAccessible(true);
                try {
                    $rules = $method->invoke($instance) ?: [];
                } catch (\Exception $e) {
                    $this->errorCollector?->addWarning(
                        'FormRequestAnalyzer',
                        "Failed to invoke rules() method during reflection analysis: {$e->getMessage()}",
                        [
                            'class_name' => $reflection->getName(),
                            'file_path' => $reflection->getFileName() ?: 'unknown',
                            'method' => 'rules',
                            'error_type' => 'anonymous_reflection_rules_invocation_error',
                        ]
                    );

                    return [];
                }
            }

            // Extract attributes using reflection
            $attributes = [];
            if ($reflection->hasMethod('attributes')) {
                $method = $reflection->getMethod('attributes');
                $method->setAccessible(true);
                $attributes = $method->invoke($instance) ?: [];
            }

            $namespace = $reflection->getNamespaceName();

            // When using reflection, we get actual rule objects, not AST strings
            // So we need to pass these objects directly to generateParameters
            return $this->generateParametersFromRuntimeRules($rules, $attributes, $namespace);

        } catch (\Exception $e) {
            $this->errorCollector?->addWarning(
                'FormRequestAnalyzer',
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
     * Analyze anonymous class with conditional rules support
     */
    protected function analyzeAnonymousClassWithConditionalRules(\ReflectionClass $reflection): array
    {
        try {
            // For AST parsing of anonymous classes
            $filePath = $reflection->getFileName();
            if ($filePath && file_exists($filePath)) {
                $code = file_get_contents($filePath);
                $startLine = $reflection->getStartLine() - 1;
                $endLine = $reflection->getEndLine();

                $lines = explode("\n", $code);
                $classCode = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));

                if (! str_contains($classCode, '<?php')) {
                    $classCode = "<?php\n".$classCode;
                }

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
                    // Fall back to reflection-based extraction
                    $this->errorCollector?->addWarning(
                        'FormRequestAnalyzer',
                        "Failed to parse anonymous class AST for conditional rules, falling back to reflection: {$e->getMessage()}",
                        [
                            'class_name' => $reflection->getName(),
                            'file_path' => $filePath,
                            'error_type' => 'anonymous_conditional_ast_parse_error',
                        ]
                    );
                }
            }

            // Fallback to reflection-based extraction
            $result = $this->analyzeWithReflection($reflection);

            return [
                'parameters' => $result,
                'conditional_rules' => [
                    'rules_sets' => [],
                    'merged_rules' => [],
                ],
            ];

        } catch (\Exception $e) {
            $this->errorCollector?->addWarning(
                'FormRequestAnalyzer',
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
}
