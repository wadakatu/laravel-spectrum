<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use Illuminate\Foundation\Http\FormRequest;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Contracts\Analyzers\MethodAnalyzer;
use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\DTO\ControllerInfo;
use LaravelSpectrum\DTO\EnumParameterInfo;
use LaravelSpectrum\DTO\FractalInfo;
use LaravelSpectrum\DTO\HeaderParameterInfo;
use LaravelSpectrum\DTO\InlineValidationInfo;
use LaravelSpectrum\DTO\PaginationInfo;
use LaravelSpectrum\DTO\QueryParameterInfo;
use LaravelSpectrum\DTO\ResourceDetectionResult;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\HasErrorCollection;
use LaravelSpectrum\Support\MethodSourceExtractor;
use PhpParser\Node;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

class ControllerAnalyzer implements HasErrors, MethodAnalyzer
{
    use HasErrorCollection;

    protected FormRequestAnalyzer $formRequestAnalyzer;

    protected InlineValidationAnalyzer $inlineValidationAnalyzer;

    protected PaginationAnalyzer $paginationAnalyzer;

    protected QueryParameterAnalyzer $queryParameterAnalyzer;

    protected HeaderParameterAnalyzer $headerParameterAnalyzer;

    protected EnumAnalyzer $enumAnalyzer;

    protected ResponseAnalyzer $responseAnalyzer;

    protected CallbackAnalyzer $callbackAnalyzer;

    protected AstHelper $astHelper;

    protected MethodSourceExtractor $methodSourceExtractor;

    public function __construct(
        FormRequestAnalyzer $formRequestAnalyzer,
        InlineValidationAnalyzer $inlineValidationAnalyzer,
        PaginationAnalyzer $paginationAnalyzer,
        QueryParameterAnalyzer $queryParameterAnalyzer,
        HeaderParameterAnalyzer $headerParameterAnalyzer,
        EnumAnalyzer $enumAnalyzer,
        ResponseAnalyzer $responseAnalyzer,
        CallbackAnalyzer $callbackAnalyzer,
        AstHelper $astHelper,
        ?MethodSourceExtractor $methodSourceExtractor = null,
        ?ErrorCollector $errorCollector = null
    ) {
        $this->initializeErrorCollector($errorCollector);
        $this->formRequestAnalyzer = $formRequestAnalyzer;
        $this->inlineValidationAnalyzer = $inlineValidationAnalyzer;
        $this->paginationAnalyzer = $paginationAnalyzer;
        $this->queryParameterAnalyzer = $queryParameterAnalyzer;
        $this->headerParameterAnalyzer = $headerParameterAnalyzer;
        $this->enumAnalyzer = $enumAnalyzer;
        $this->responseAnalyzer = $responseAnalyzer;
        $this->callbackAnalyzer = $callbackAnalyzer;
        $this->astHelper = $astHelper;
        $this->methodSourceExtractor = $methodSourceExtractor ?? new MethodSourceExtractor;
    }

    /**
     * コントローラーメソッドを解析してFormRequestとResourceを抽出
     *
     * @return array<string, mixed> 後方互換性のための配列形式
     */
    public function analyze(string $controller, string $method): array
    {
        // 後方互換性: 存在しないクラス/メソッドの場合は空配列を返す
        if (! class_exists($controller)) {
            return [];
        }

        $reflection = new ReflectionClass($controller);
        if (! $reflection->hasMethod($method)) {
            return [];
        }

        return $this->analyzeToResult($controller, $method)->toArray();
    }

    /**
     * コントローラーメソッドを解析してControllerInfoを返す
     */
    public function analyzeToResult(string $controller, string $method): ControllerInfo
    {
        if (! class_exists($controller)) {
            return ControllerInfo::empty();
        }

        $reflection = new ReflectionClass($controller);

        if (! $reflection->hasMethod($method)) {
            return ControllerInfo::empty();
        }

        $methodReflection = $reflection->getMethod($method);

        // パラメータからFormRequestとEnum型を検出
        $enumParameters = $this->analyzeEnumParametersToDto($methodReflection);
        $formRequest = $this->detectFormRequest($methodReflection, $controller, $method);

        // メソッドのASTを取得してインラインバリデーションを検出
        $inlineValidation = null;
        $methodNode = $this->getMethodNode($reflection, $method);
        if ($methodNode) {
            $inlineValidation = $this->inlineValidationAnalyzer->analyze($methodNode);
        }

        // メソッドのソースコードからResourceを検出
        $source = $this->methodSourceExtractor->extractSource($methodReflection);

        // First try to detect from return type (supports union types)
        $resourceDetection = $this->detectResourceFromReturnType($methodReflection);

        // Fall back to source code detection if return type detection failed
        if (! $resourceDetection->hasResource()) {
            $resourceDetection = $this->detectResource($source, $reflection);
        }

        // Fractal使用を検出
        $fractal = $this->detectFractalUsageToDto($source, $reflection);

        // Pagination使用を検出
        $pagination = $this->detectPaginationToDto($methodReflection);

        // Query Parameter使用を検出
        $queryParameters = $this->detectQueryParameters(
            $methodReflection,
            $formRequest,
            $inlineValidation,
            $controller,
            $method
        );

        // Header Parameter使用を検出
        $headerParameters = $this->detectHeaderParameters($methodReflection);

        // レスポンス解析
        $response = $this->responseAnalyzer->analyze($controller, $method);
        // Only include response if it's not unknown type
        if ($response->type->isUnknown()) {
            $response = null;
        }

        // @deprecated アノテーションを検出
        $deprecated = $this->detectDeprecated($methodReflection);

        // コールバック定義を検出
        $callbacks = $this->callbackAnalyzer->analyze($methodReflection);

        return new ControllerInfo(
            formRequest: $formRequest,
            inlineValidation: $inlineValidation,
            resource: $resourceDetection->resourceClass,
            resourceClasses: $resourceDetection->resourceClasses,
            returnsCollection: $resourceDetection->isCollection,
            fractal: $fractal,
            pagination: $pagination,
            queryParameters: $queryParameters,
            headerParameters: $headerParameters,
            enumParameters: $enumParameters,
            response: $response,
            deprecated: $deprecated,
            callbacks: $callbacks,
        );
    }

    /**
     * FormRequestクラスを検出
     */
    protected function detectFormRequest(ReflectionMethod $methodReflection, string $controller, string $method): ?string
    {
        foreach ($methodReflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            // Union/Intersection types are not yet supported for FormRequest detection
            if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
                $this->logWarning(
                    "Union/Intersection type parameters are not supported for FormRequest detection: {$parameter->getName()}",
                    AnalyzerErrorType::UnsupportedFeature,
                    [
                        'parameter' => $parameter->getName(),
                        'controller' => $controller,
                        'method' => $method,
                    ]
                );

                continue;
            }

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $className = $type->getName();
                if (class_exists($className) && is_subclass_of($className, FormRequest::class)) {
                    return $className;
                }
            }
        }

        return null;
    }

    /**
     * Resourceクラスを検出
     *
     * @param  \ReflectionClass<object>  $reflection
     */
    protected function detectResource(string $source, ReflectionClass $reflection): ResourceDetectionResult
    {
        if (preg_match('/(\w+Resource)::collection/', $source, $matches)) {
            $resourceClass = $this->resolveClassName($matches[1], $reflection);
            if ($resourceClass && class_exists($resourceClass)) {
                return ResourceDetectionResult::collection($resourceClass);
            }
        } elseif (preg_match('/new\s+(\w+Resource)/', $source, $matches)) {
            $resourceClass = $this->resolveClassName($matches[1], $reflection);
            if ($resourceClass && class_exists($resourceClass)) {
                return ResourceDetectionResult::single($resourceClass);
            }
        }

        return ResourceDetectionResult::notFound();
    }

    /**
     * Detect resource classes from method return type hint.
     *
     * This method analyzes the return type declaration of a controller method
     * to detect Resource class(es), including union types like UserResource|PostResource.
     */
    protected function detectResourceFromReturnType(ReflectionMethod $methodReflection): ResourceDetectionResult
    {
        $returnType = $methodReflection->getReturnType();

        if ($returnType === null) {
            return ResourceDetectionResult::notFound();
        }

        // Handle union types (PHP 8+)
        if ($returnType instanceof ReflectionUnionType) {
            $resourceClasses = [];
            foreach ($returnType->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    $className = $type->getName();
                    // Check if class ends with 'Resource' (common Laravel convention)
                    if (class_exists($className) && str_ends_with($className, 'Resource')) {
                        $resourceClasses[] = $className;
                    }
                }
            }

            if (count($resourceClasses) > 1) {
                return ResourceDetectionResult::union($resourceClasses);
            } elseif (count($resourceClasses) === 1) {
                return ResourceDetectionResult::single($resourceClasses[0]);
            }

            return ResourceDetectionResult::notFound();
        }

        // Handle single named type
        if ($returnType instanceof ReflectionNamedType && ! $returnType->isBuiltin()) {
            $className = $returnType->getName();
            if (class_exists($className) && str_ends_with($className, 'Resource')) {
                return ResourceDetectionResult::single($className);
            }
        }

        return ResourceDetectionResult::notFound();
    }

    /**
     * Fractal使用を検出してDTOを返す
     *
     * @param  \ReflectionClass<object>  $reflection
     */
    protected function detectFractalUsageToDto(string $source, ReflectionClass $reflection): ?FractalInfo
    {
        // fractal()->item() パターン
        if (preg_match('/fractal\(\)->item\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $transformerClass = $this->resolveClassName($matches[1], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                return new FractalInfo(
                    transformer: $transformerClass,
                    isCollection: false,
                    type: 'item',
                    hasIncludes: strpos($source, 'parseIncludes') !== false,
                );
            }
        }
        // fractal()->collection() パターン
        elseif (preg_match('/fractal\(\)->collection\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $transformerClass = $this->resolveClassName($matches[1], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                return new FractalInfo(
                    transformer: $transformerClass,
                    isCollection: true,
                    type: 'collection',
                    hasIncludes: strpos($source, 'parseIncludes') !== false,
                );
            }
        }
        // fractal()をチェーン呼び出しするパターン
        elseif (preg_match('/fractal\(\)\s*->\s*(item|collection)\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $type = $matches[1];
            $transformerClass = $this->resolveClassName($matches[2], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                return new FractalInfo(
                    transformer: $transformerClass,
                    isCollection: $type === 'collection',
                    type: $type,
                    hasIncludes: strpos($source, 'parseIncludes') !== false,
                );
            }
        }
        // Pattern: new Item(..., new Transformer) - direct League\Fractal\Resource\Item usage
        elseif (preg_match('/new\s+Item\s*\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $transformerClass = $this->resolveClassName($matches[1], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                return new FractalInfo(
                    transformer: $transformerClass,
                    isCollection: false,
                    type: 'item',
                    hasIncludes: strpos($source, 'parseIncludes') !== false,
                );
            }
        }
        // Pattern: new Collection(..., new Transformer) - direct League\Fractal\Resource\Collection usage
        elseif (preg_match('/new\s+Collection\s*\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $transformerClass = $this->resolveClassName($matches[1], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                return new FractalInfo(
                    transformer: $transformerClass,
                    isCollection: true,
                    type: 'collection',
                    hasIncludes: strpos($source, 'parseIncludes') !== false,
                );
            }
        }

        return null;
    }

    /**
     * Pagination使用を検出してDTOを返す
     */
    protected function detectPaginationToDto(ReflectionMethod $methodReflection): ?PaginationInfo
    {
        return $this->paginationAnalyzer->analyzeMethod($methodReflection);
    }

    /**
     * Query Parameterを検出
     *
     * @return array<int, QueryParameterInfo>
     */
    protected function detectQueryParameters(
        ReflectionMethod $methodReflection,
        ?string $formRequest,
        ?InlineValidationInfo $inlineValidation,
        string $controller,
        string $method
    ): array {
        $queryParamsResult = $this->queryParameterAnalyzer->analyzeToResult($methodReflection);
        if (! $queryParamsResult->hasParameters()) {
            return [];
        }

        // バリデーションルールとマージ
        $validationRules = [];

        // FormRequestからのバリデーションルール
        if ($formRequest) {
            try {
                $formRequestAnalysis = $this->formRequestAnalyzer->analyze($formRequest);
                if (isset($formRequestAnalysis['rules'])) {
                    $validationRules = $formRequestAnalysis['rules'];
                }
            } catch (\Exception $e) {
                $this->logWarning(
                    "Failed to analyze FormRequest {$formRequest}: {$e->getMessage()}",
                    AnalyzerErrorType::AnalysisError,
                    [
                        'formRequest' => $formRequest,
                        'controller' => $controller,
                        'method' => $method,
                    ]
                );
            }
        }

        // インラインバリデーションルール
        if ($inlineValidation !== null && $inlineValidation->hasRules()) {
            $validationRules = array_merge($validationRules, $inlineValidation->rules);
        }

        // バリデーションルールがある場合はマージ
        if (! empty($validationRules)) {
            $queryParamsResult = $this->queryParameterAnalyzer->mergeWithValidationToResult(
                $queryParamsResult,
                $validationRules
            );
        }

        return $queryParamsResult->parameters;
    }

    /**
     * Header Parameter使用を検出
     *
     * @return array<int, HeaderParameterInfo>
     */
    protected function detectHeaderParameters(ReflectionMethod $methodReflection): array
    {
        return $this->headerParameterAnalyzer->analyzeToResult($methodReflection);
    }

    /**
     * Enum型パラメータを解析してDTOの配列を返す
     *
     * @return array<int, EnumParameterInfo>
     */
    protected function analyzeEnumParametersToDto(ReflectionMethod $method): array
    {
        $enumParameters = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type || ! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();

            // FormRequestはスキップ
            if (is_subclass_of($className, FormRequest::class)) {
                continue;
            }

            // Enum型かチェック
            if (enum_exists($className)) {
                $enumInfo = $this->enumAnalyzer->extractEnumInfo($className);
                if ($enumInfo) {
                    $enumParameters[] = new EnumParameterInfo(
                        name: $parameter->getName(),
                        type: $enumInfo->getOpenApiType(),
                        enum: $enumInfo->values,
                        required: ! $type->allowsNull() && ! $parameter->isOptional(),
                        description: "Enum parameter of type {$className}",
                        in: 'path',
                        enumClass: $className,
                    );
                }
            }
        }

        return $enumParameters;
    }

    /**
     * クラス名を解決（use文を考慮）
     *
     * @param  \ReflectionClass<object>  $reflection
     */
    protected function resolveClassName(string $shortName, ReflectionClass $reflection): ?string
    {
        // 完全修飾名の場合
        if (strpos($shortName, '\\') !== false) {
            // 先頭の\を除去
            $shortName = ltrim($shortName, '\\');
            if (class_exists($shortName)) {
                return $shortName;
            }
        }

        // クラス名のみを取得
        $className = basename(str_replace('\\', '/', $shortName));

        // 同じ名前空間のクラスを試す
        $namespace = $reflection->getNamespaceName();
        $fullName = $namespace.'\\'.$className;
        if (class_exists($fullName)) {
            return $fullName;
        }

        // ASTからuse文を解決（alias/group use対応）
        $filename = $reflection->getFileName();
        if (is_string($filename)) {
            $ast = $this->astHelper->parseFile($filename);
            if ($ast !== null) {
                $useStatements = $this->astHelper->extractUseStatements($ast);
                if (isset($useStatements[$className]) && class_exists($useStatements[$className])) {
                    return $useStatements[$className];
                }
            }
        }

        // ファイルのuse文をチェック（簡易版フォールバック）
        if (! is_string($filename) || ! file_exists($filename)) {
            return null;
        }

        $content = file_get_contents($filename);
        if ($content === false) {
            return null;
        }

        if (preg_match('/use\s+([\w\\\\]+\\\\'.$className.')\s*(?:as\s+\w+)?\s*;/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * メソッドのASTノードを取得
     *
     * @param  \ReflectionClass<object>  $reflection
     */
    protected function getMethodNode(ReflectionClass $reflection, string $methodName): ?Node\Stmt\ClassMethod
    {
        try {
            $filename = $reflection->getFileName();
            if (! $filename) {
                return null;
            }

            $ast = $this->astHelper->parseFile($filename);
            if (! $ast) {
                return null;
            }

            // Handle anonymous classes by finding them by line number
            if ($reflection->isAnonymous()) {
                $startLine = $reflection->getStartLine();
                $classNode = $this->astHelper->findAnonymousClassNode($ast, $startLine);
            } else {
                // クラスノードを探す
                $classNode = $this->astHelper->findClassNode($ast, $reflection->getShortName());
            }

            if (! $classNode) {
                return null;
            }

            // メソッドノードを探す
            return $this->astHelper->findMethodNode($classNode, $methodName);
        } catch (\Exception $e) {
            $this->logWarning(
                "Failed to get method node for {$reflection->getName()}::{$methodName}: {$e->getMessage()}",
                AnalyzerErrorType::MethodNodeError,
                [
                    'class' => $reflection->getName(),
                    'method' => $methodName,
                ]
            );
        }

        return null;
    }

    /**
     * Detect if a method is marked as deprecated via PHPDoc @deprecated annotation.
     */
    protected function detectDeprecated(ReflectionMethod $method): bool
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return false;
        }

        // Check for @deprecated annotation (case-insensitive)
        return (bool) preg_match('/@deprecated\b/i', $docComment);
    }
}
