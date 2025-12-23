<?php

namespace LaravelSpectrum\Analyzers;

use Illuminate\Support\Facades\Log;
use LaravelSpectrum\Analyzers\Support\FormatInferrer;
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

    public function __construct(TypeInference $typeInference, DocumentationCache $cache, ?EnumAnalyzer $enumAnalyzer = null, ?FileUploadAnalyzer $fileUploadAnalyzer = null, ?ErrorCollector $errorCollector = null, ?RuleRequirementAnalyzer $ruleRequirementAnalyzer = null, ?FormatInferrer $formatInferrer = null, ?ValidationDescriptionGenerator $descriptionGenerator = null)
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
            $useStatements = $this->extractUseStatements($ast);

            // クラスノードを探す
            $classNode = $this->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                return [];
            }

            // rules()メソッドを解析
            $rules = $this->extractRules($classNode);

            // attributes()メソッドを解析
            $attributes = $this->extractAttributes($classNode);

            // Get namespace for enum resolution
            $namespace = $reflection->getNamespaceName();

            // パラメータ情報を生成
            return $this->generateParameters($rules, $attributes, $namespace, $useStatements);

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
                $useStatements = $this->extractUseStatements($ast);

                // Find class node
                $classNode = $this->findClassNode($ast, $reflection->getShortName());
                if (! $classNode) {
                    return [
                        'parameters' => [],
                        'conditional_rules' => ['rules_sets' => [], 'merged_rules' => []],
                    ];
                }

                // Extract conditional rules
                $conditionalRules = $this->extractConditionalRules($classNode);

                // Generate parameters from conditional rules
                $attributes = $this->extractAttributes($classNode);
                $messages = $this->extractMessages($classNode);

                if (! empty($conditionalRules['rules_sets'])) {
                    $parameters = $this->generateParametersFromConditionalRules(
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
                $rules = $this->extractRules($classNode);
                $parameters = $this->generateParameters($rules, $attributes, $reflection->getNamespaceName(), $useStatements);

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
            $classNode = $this->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                return [];
            }

            // rules()メソッドを解析
            $rules = $this->extractRules($classNode);

            // attributes()メソッドを解析
            $attributes = $this->extractAttributes($classNode);

            // messages()メソッドを解析
            $messages = $this->extractMessages($classNode);

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
     * messages()メソッドから検証メッセージを抽出
     */
    protected function extractMessages(Node\Stmt\Class_ $class): array
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
                    // If rules() method fails, return empty
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
            return [];
        }
    }

    /**
     * クラスノードを探す
     */
    protected function findClassNode(array $ast, string $className): ?Node\Stmt\Class_
    {
        $visitor = new AST\Visitors\ClassFindingVisitor($className);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getClassNode();
    }

    /**
     * rules()メソッドから検証ルールを抽出
     */
    protected function extractRules(Node\Stmt\Class_ $class): array
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
     * attributes()メソッドから属性を抽出
     */
    protected function extractAttributes(Node\Stmt\Class_ $class): array
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
     * メソッドノードを探す
     */
    protected function findMethodNode(Node\Stmt\Class_ $class, string $methodName): ?Node\Stmt\ClassMethod
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
     * Extract use statements from AST
     */
    protected function extractUseStatements(array $ast): array
    {
        $visitor = new AST\Visitors\UseStatementExtractorVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getUseStatements();
    }

    /**
     * パラメータ情報を生成
     */
    protected function generateParameters(array $rules, array $attributes = [], ?string $namespace = null, array $useStatements = []): array
    {
        $parameters = [];

        // Analyze file upload fields
        $fileFields = $this->fileUploadAnalyzer->analyzeRules($rules);

        foreach ($rules as $field => $rule) {
            // 特殊なフィールド（_noticeなど）はスキップ
            if (str_starts_with($field, '_')) {
                continue;
            }

            $ruleArray = is_array($rule) ? $rule : explode('|', $rule);

            // Check if this is a file upload field
            if (isset($fileFields[$field])) {
                $fileInfo = $fileFields[$field];
                $parameter = [
                    'name' => $field,
                    'in' => 'body',
                    'required' => $this->ruleRequirementAnalyzer->isRequired($ruleArray),
                    'type' => 'file',
                    'format' => 'binary',
                    'file_info' => $fileInfo,
                    'description' => $this->descriptionGenerator->generateFileDescriptionWithAttribute($field, $fileInfo, $attributes[$field] ?? null),
                    'validation' => $ruleArray,
                ];

                $parameters[] = $parameter;

                continue;
            }

            // Check for enum rules
            $enumInfo = null;
            foreach ($ruleArray as $singleRule) {
                $enumResult = $this->enumAnalyzer->analyzeValidationRule($singleRule, $namespace, $useStatements);
                if ($enumResult) {
                    $enumInfo = $enumResult;
                    break;
                }
            }

            $parameter = [
                'name' => $field,
                'in' => 'body',
                'required' => $this->ruleRequirementAnalyzer->isRequired($ruleArray),
                'type' => $this->typeInference->inferFromRules($ruleArray),
                'description' => $attributes[$field] ?? $this->descriptionGenerator->generateDescription($field, $ruleArray, $namespace, $useStatements),
                'example' => $this->typeInference->generateExample($field, $ruleArray),
                'validation' => $ruleArray,
            ];

            // Add format for various field types
            $format = $this->formatInferrer->inferFormat($ruleArray);
            if ($format) {
                $parameter['format'] = $format;
            }

            // Add conditional rule information
            if ($this->ruleRequirementAnalyzer->hasConditionalRequired($ruleArray)) {
                $parameter['conditional_required'] = true;
                $parameter['conditional_rules'] = $this->ruleRequirementAnalyzer->extractConditionalRuleDetails($ruleArray);
            }

            // Add enum information if found
            if ($enumInfo) {
                $parameter['enum'] = $enumInfo;
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * Generate parameters from runtime rules (when using reflection on anonymous classes)
     */
    protected function generateParametersFromRuntimeRules(array $rules, array $attributes = [], ?string $namespace = null): array
    {
        // This method handles rules that contain actual objects (not AST strings)
        // It's used when we extract rules via reflection instead of AST parsing
        return $this->generateParameters($rules, $attributes, $namespace);
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
                        $classNode = $this->findAnonymousClassNode($ast);
                        if ($classNode) {
                            $rules = $this->extractRules($classNode);
                            $attributes = $this->extractAttributes($classNode);
                            $namespace = $reflection->getNamespaceName();
                            $useStatements = $this->extractUseStatements($ast);

                            return $this->generateParameters($rules, $attributes, $namespace, $useStatements);
                        }
                    }
                } catch (Error $e) {
                    // Fall back to instance-based extraction
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
                    // If rules() method fails, return empty
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
            return [];
        }
    }

    /**
     * Find anonymous class node in AST
     */
    protected function findAnonymousClassNode(array $ast): ?Node\Stmt\Class_
    {
        // Use a visitor to find anonymous class nodes
        $visitor = new AST\Visitors\AnonymousClassFindingVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getClassNode();
    }

    /**
     * Extract conditional rules from rules() method
     */
    protected function extractConditionalRules(Node\Stmt\Class_ $class): array
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

    /**
     * Generate parameters from conditional rules
     */
    protected function generateParametersFromConditionalRules(array $conditionalRules, array $attributes = [], string $namespace = '', array $useStatements = []): array
    {
        $parameters = [];
        $processedFields = [];

        // Process all rule sets
        foreach ($conditionalRules['rules_sets'] as $ruleSet) {
            foreach ($ruleSet['rules'] as $field => $rule) {
                if (! isset($processedFields[$field])) {
                    $processedFields[$field] = [
                        'name' => $field,
                        'in' => 'body',
                        'rules_by_condition' => [],
                    ];
                }

                $processedFields[$field]['rules_by_condition'][] = [
                    'conditions' => $ruleSet['conditions'],
                    'rules' => is_array($rule) ? $rule : explode('|', $rule),
                ];
            }
        }

        // Generate parameters with merged rules for default type info
        foreach ($conditionalRules['merged_rules'] as $field => $mergedRules) {
            // Check for enum rules
            $enumInfo = null;
            foreach ($mergedRules as $singleRule) {
                $enumResult = $this->enumAnalyzer->analyzeValidationRule($singleRule);
                if ($enumResult) {
                    $enumInfo = $enumResult;
                    break;
                }
            }

            $parameter = [
                'name' => $field,
                'in' => 'body',
                'required' => $this->ruleRequirementAnalyzer->isRequiredInAnyCondition($processedFields[$field]['rules_by_condition']),
                'type' => $this->typeInference->inferFromRules($mergedRules),
                'description' => $attributes[$field] ?? $this->descriptionGenerator->generateConditionalDescription($field, $processedFields[$field]),
                'conditional_rules' => $processedFields[$field]['rules_by_condition'],
                'validation' => $mergedRules,
                'example' => $this->typeInference->generateExample($field, $mergedRules),
            ];

            // Add enum information if found
            if ($enumInfo) {
                $parameter['enum'] = $enumInfo;
            }

            $parameters[] = $parameter;
        }

        return $parameters;
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
                        $classNode = $this->findAnonymousClassNode($ast);
                        if ($classNode) {
                            $conditionalRules = $this->extractConditionalRules($classNode);

                            if (! empty($conditionalRules['rules_sets'])) {
                                $attributes = $this->extractAttributes($classNode);
                                $parameters = $this->generateParametersFromConditionalRules($conditionalRules, $attributes);

                                return [
                                    'parameters' => $parameters,
                                    'conditional_rules' => $conditionalRules,
                                ];
                            }
                        }
                    }
                } catch (Error $e) {
                    // Fall back to reflection-based extraction
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
