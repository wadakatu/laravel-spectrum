<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Analyzers\Support\AnonymousClassAnalyzer;
use LaravelSpectrum\Analyzers\Support\FormRequestAstExtractor;
use LaravelSpectrum\Analyzers\Support\ParameterBuilder;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Contracts\Analyzers\ClassAnalyzer;
use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\DTO\ConditionalRuleSet;
use LaravelSpectrum\DTO\ParameterDefinition;
use LaravelSpectrum\DTO\ValidationAnalysisResult;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\HasErrorCollection;

/**
 * Analyzes Laravel FormRequest classes to extract validation rules and parameters.
 *
 * Delegates AST parsing and extraction to FormRequestAstExtractor and builds
 * OpenAPI-compatible parameter schemas from the extracted information.
 *
 * Supports:
 * - Standard FormRequest classes with rules() method
 * - Anonymous FormRequest classes (via AnonymousClassAnalyzer)
 * - Conditional validation rules
 * - Custom attributes and messages
 */
class FormRequestAnalyzer implements ClassAnalyzer, HasErrors
{
    use HasErrorCollection;

    protected DocumentationCache $cache;

    protected ParameterBuilder $parameterBuilder;

    protected FormRequestAstExtractor $astExtractor;

    protected AnonymousClassAnalyzer $anonymousClassAnalyzer;

    public function __construct(
        DocumentationCache $cache,
        ParameterBuilder $parameterBuilder,
        FormRequestAstExtractor $astExtractor,
        AnonymousClassAnalyzer $anonymousClassAnalyzer,
        ?ErrorCollector $errorCollector = null
    ) {
        $this->initializeErrorCollector($errorCollector);
        $this->cache = $cache;
        $this->parameterBuilder = $parameterBuilder;
        $this->astExtractor = $astExtractor;
        $this->anonymousClassAnalyzer = $anonymousClassAnalyzer;
    }

    /**
     * Prepare analysis context for a FormRequest class.
     *
     * This helper method consolidates the common setup logic used by multiple analysis methods.
     * It validates the class, creates reflection, checks for anonymous classes, and parses the AST.
     *
     * @param  string  $requestClass  The fully qualified FormRequest class name
     * @return array{
     *     type: 'skip'|'anonymous'|'ready',
     *     reflection?: \ReflectionClass<\Illuminate\Foundation\Http\FormRequest>,
     *     ast?: array<\PhpParser\Node\Stmt>,
     *     classNode?: \PhpParser\Node\Stmt\Class_,
     *     useStatements?: array<string, string>,
     *     namespace?: string
     * } Returns an array indicating the analysis state:
     *   - 'skip': Class doesn't exist, is not a FormRequest subclass, AST parsing failed, or class node not found in AST
     *   - 'anonymous': Class is anonymous, file path unavailable, or file does not exist (includes 'reflection')
     *   - 'ready': Full analysis context ready (includes all fields)
     */
    protected function prepareAnalysisContext(string $requestClass): array
    {
        if (! class_exists($requestClass)) {
            return ['type' => 'skip'];
        }

        $reflection = new \ReflectionClass($requestClass);

        if (! $reflection->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class)) {
            return ['type' => 'skip'];
        }

        $filePath = $reflection->getFileName();
        if (! $filePath || ! file_exists($filePath) || $reflection->isAnonymous()) {
            return [
                'type' => 'anonymous',
                'reflection' => $reflection,
            ];
        }

        $ast = $this->astExtractor->parseFile($filePath);
        if (! $ast) {
            return ['type' => 'skip'];
        }

        $classNode = $this->astExtractor->findClassNode($ast, $reflection->getShortName());
        if (! $classNode) {
            $this->logWarning(
                "Class node not found in AST for {$reflection->getName()}",
                AnalyzerErrorType::ClassNodeNotFound,
                [
                    'class' => $reflection->getName(),
                    'short_name' => $reflection->getShortName(),
                    'file' => $filePath,
                ]
            );

            return ['type' => 'skip'];
        }

        return [
            'type' => 'ready',
            'reflection' => $reflection,
            'ast' => $ast,
            'classNode' => $classNode,
            'useStatements' => $this->astExtractor->extractUseStatements($ast),
            'namespace' => $reflection->getNamespaceName(),
        ];
    }

    /**
     * FormRequestクラスを解析してパラメータ情報を抽出
     */
    public function analyze(string $requestClass): array
    {
        return $this->convertParametersToArrays($this->analyzeToResult($requestClass));
    }

    /**
     * FormRequestクラスを解析してParameterDefinition DTOの配列を返す
     *
     * @return array<ParameterDefinition>
     */
    public function analyzeToResult(string $requestClass): array
    {
        try {
            // Cache stores arrays, so we need to convert back to DTOs
            $cached = $this->cache->rememberFormRequest($requestClass, function () use ($requestClass) {
                return $this->convertParametersToArrays($this->performAnalysisToResult($requestClass));
            });

            return array_map(
                fn (array $data) => ParameterDefinition::fromArray($data),
                $cached
            );
        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::AnalysisError, [
                'class' => $requestClass,
            ]);

            return [];
        }
    }

    /**
     * 実際の解析処理（配列を返す - 後方互換性のため）
     */
    protected function performAnalysis(string $requestClass): array
    {
        return $this->convertParametersToArrays($this->performAnalysisToResult($requestClass));
    }

    /**
     * 実際の解析処理（ParameterDefinition DTOの配列を返す）
     *
     * @return array<ParameterDefinition>
     */
    protected function performAnalysisToResult(string $requestClass): array
    {
        try {
            $context = $this->prepareAnalysisContext($requestClass);

            if ($context['type'] === 'skip') {
                return [];
            }

            if ($context['type'] === 'anonymous') {
                // AnonymousClassAnalyzer returns arrays, convert to DTOs
                $arrays = $this->anonymousClassAnalyzer->analyze($context['reflection']);

                return array_map(
                    fn (array $data) => ParameterDefinition::fromArray($data),
                    $arrays
                );
            }

            // Extract rules and attributes from AST
            $rules = $this->astExtractor->extractRules($context['classNode']);
            $attributes = $this->astExtractor->extractAttributes($context['classNode']);

            // Build parameters (returns ParameterDefinition DTOs)
            return $this->parameterBuilder->buildFromRules(
                $rules,
                $attributes,
                $context['namespace'],
                $context['useStatements']
            );

        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::AnalysisError, [
                'class' => $requestClass,
            ]);

            return [];
        }
    }

    /**
     * Analyze with conditional rules detection (returns array for backward compatibility).
     *
     * @return array<string, mixed>
     */
    public function analyzeWithConditionalRules(string $requestClass): array
    {
        return $this->analyzeWithConditionalRulesToResult($requestClass)->toArray();
    }

    /**
     * Analyze with conditional rules detection and return structured result.
     */
    public function analyzeWithConditionalRulesToResult(string $requestClass): ValidationAnalysisResult
    {
        try {
            // Cache stores arrays, so we need to convert back to DTO
            $cached = $this->cache->rememberFormRequest($requestClass.':conditional', function () use ($requestClass) {
                return $this->performConditionalAnalysis($requestClass)->toArray();
            });

            return ValidationAnalysisResult::fromArray($cached);
        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::ConditionalAnalysisError, [
                'class' => $requestClass,
            ]);

            return ValidationAnalysisResult::empty();
        }
    }

    /**
     * Perform the actual conditional rules analysis.
     */
    protected function performConditionalAnalysis(string $requestClass): ValidationAnalysisResult
    {
        try {
            $context = $this->prepareAnalysisContext($requestClass);

            if ($context['type'] === 'skip') {
                return ValidationAnalysisResult::empty();
            }

            if ($context['type'] === 'anonymous') {
                // Anonymous class analyzer returns array, convert to DTO
                return ValidationAnalysisResult::fromArray(
                    $this->anonymousClassAnalyzer->analyzeWithConditionalRules($context['reflection'])
                );
            }

            // Extract conditional rules
            $conditionalRulesArray = $this->astExtractor->extractConditionalRules($context['classNode']);
            $attributes = $this->astExtractor->extractAttributes($context['classNode']);
            $messages = $this->astExtractor->extractMessages($context['classNode']);

            if (! empty($conditionalRulesArray['rules_sets'])) {
                $parameters = $this->parameterBuilder->buildFromConditionalRules(
                    $conditionalRulesArray,
                    $attributes,
                    $context['namespace'],
                    $context['useStatements']
                );

                return new ValidationAnalysisResult(
                    parameters: $this->convertParametersToArrays($parameters),
                    conditionalRules: ConditionalRuleSet::fromArray($conditionalRulesArray),
                    attributes: $attributes,
                    messages: $messages,
                );
            }

            // Fallback to regular analysis
            $rules = $this->astExtractor->extractRules($context['classNode']);
            $parameters = $this->parameterBuilder->buildFromRules(
                $rules,
                $attributes,
                $context['namespace'],
                $context['useStatements']
            );

            return new ValidationAnalysisResult(
                parameters: $this->convertParametersToArrays($parameters),
                conditionalRules: ConditionalRuleSet::empty(),
                attributes: $attributes,
                messages: $messages,
            );

        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::ConditionalAnalysisError, [
                'class' => $requestClass,
            ]);

            return ValidationAnalysisResult::empty();
        }
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
        try {
            $context = $this->prepareAnalysisContext($requestClass);

            if ($context['type'] === 'skip') {
                return [];
            }

            if ($context['type'] === 'anonymous') {
                return $this->anonymousClassAnalyzer->analyzeDetails($context['reflection']);
            }

            // Extract rules, attributes, and messages from AST
            return [
                'rules' => $this->astExtractor->extractRules($context['classNode']),
                'attributes' => $this->astExtractor->extractAttributes($context['classNode']),
                'messages' => $this->astExtractor->extractMessages($context['classNode']),
            ];

        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::AnalysisError, [
                'class' => $requestClass,
            ]);

            return [];
        }
    }

    /**
     * Convert ParameterDefinition DTOs to arrays for backward compatibility.
     *
     * Handles both DTO instances and legacy arrays (from mocks in tests).
     *
     * @param  array<ParameterDefinition|array<string, mixed>>  $parameters
     * @return array<array<string, mixed>>
     */
    protected function convertParametersToArrays(array $parameters): array
    {
        return array_map(
            fn (ParameterDefinition|array $param) => $param instanceof ParameterDefinition
                ? $param->toArray()
                : $param,
            $parameters
        );
    }
}
