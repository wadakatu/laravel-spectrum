<?php

namespace LaravelSpectrum\Analyzers;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class ControllerAnalyzer
{
    protected FormRequestAnalyzer $formRequestAnalyzer;

    protected InlineValidationAnalyzer $inlineValidationAnalyzer;

    protected PaginationAnalyzer $paginationAnalyzer;

    protected QueryParameterAnalyzer $queryParameterAnalyzer;

    protected EnumAnalyzer $enumAnalyzer;

    protected ResponseAnalyzer $responseAnalyzer;

    protected Parser $parser;

    public function __construct(
        FormRequestAnalyzer $formRequestAnalyzer,
        InlineValidationAnalyzer $inlineValidationAnalyzer,
        PaginationAnalyzer $paginationAnalyzer,
        QueryParameterAnalyzer $queryParameterAnalyzer,
        EnumAnalyzer $enumAnalyzer,
        ResponseAnalyzer $responseAnalyzer
    ) {
        $this->formRequestAnalyzer = $formRequestAnalyzer;
        $this->inlineValidationAnalyzer = $inlineValidationAnalyzer;
        $this->paginationAnalyzer = $paginationAnalyzer;
        $this->queryParameterAnalyzer = $queryParameterAnalyzer;
        $this->enumAnalyzer = $enumAnalyzer;
        $this->responseAnalyzer = $responseAnalyzer;
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * コントローラーメソッドを解析してFormRequestとResourceを抽出
     */
    public function analyze(string $controller, string $method): array
    {
        if (! class_exists($controller)) {
            return [];
        }

        $reflection = new ReflectionClass($controller);

        if (! $reflection->hasMethod($method)) {
            return [];
        }

        $methodReflection = $reflection->getMethod($method);

        $result = [
            'formRequest' => null,
            'inlineValidation' => null,
            'resource' => null,
            'returnsCollection' => false,
            'fractal' => null,
            'pagination' => null,
            'queryParameters' => null,
            'enumParameters' => [],
            'response' => null,
        ];

        // パラメータからFormRequestとEnum型を検出
        $result['enumParameters'] = $this->analyzeEnumParameters($methodReflection);

        foreach ($methodReflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type && ! $type->isBuiltin()) {
                $className = $type->getName();
                if (class_exists($className) && is_subclass_of($className, FormRequest::class)) {
                    $result['formRequest'] = $className;
                    break;
                }
            }
        }

        // メソッドのASTを取得してインラインバリデーションを検出
        $methodNode = $this->getMethodNode($reflection, $method);
        if ($methodNode) {
            $inlineValidation = $this->inlineValidationAnalyzer->analyze($methodNode);
            if (! empty($inlineValidation)) {
                $result['inlineValidation'] = $inlineValidation;
            }
        }

        // メソッドのソースコードからResourceを検出（簡易版）
        $source = $this->getMethodSource($methodReflection);

        // Resourceクラスの使用を検出
        if (preg_match('/(\w+Resource)::collection/', $source, $matches)) {
            $resourceClass = $this->resolveClassName($matches[1], $reflection);
            if ($resourceClass && class_exists($resourceClass)) {
                $result['resource'] = $resourceClass;
                $result['returnsCollection'] = true;
            }
        } elseif (preg_match('/new\s+(\w+Resource)/', $source, $matches)) {
            $resourceClass = $this->resolveClassName($matches[1], $reflection);
            if ($resourceClass && class_exists($resourceClass)) {
                $result['resource'] = $resourceClass;
            }
        }

        // Fractal使用を検出
        $this->detectFractalUsage($source, $result, $reflection);

        // Pagination使用を検出
        $paginationInfo = $this->paginationAnalyzer->analyzeMethod($methodReflection);
        if ($paginationInfo) {
            $result['pagination'] = $paginationInfo;
        }

        // Query Parameter使用を検出
        $queryParams = $this->queryParameterAnalyzer->analyze($methodReflection);
        if (! empty($queryParams['parameters'])) {
            // バリデーションルールとマージ
            $validationRules = [];

            // FormRequestからのバリデーションルール
            if ($result['formRequest']) {
                try {
                    $formRequestAnalysis = $this->formRequestAnalyzer->analyze($result['formRequest']);
                    if (isset($formRequestAnalysis['rules'])) {
                        $validationRules = $formRequestAnalysis['rules'];
                    }
                } catch (\Exception $e) {
                    // Ignore errors
                }
            }

            // インラインバリデーションルール
            if ($result['inlineValidation'] && isset($result['inlineValidation']['rules'])) {
                $validationRules = array_merge($validationRules, $result['inlineValidation']['rules']);
            }

            // バリデーションルールがある場合はマージ
            if (! empty($validationRules)) {
                $queryParams = $this->queryParameterAnalyzer->mergeWithValidation($queryParams, $validationRules);
            }

            $result['queryParameters'] = $queryParams['parameters'];
        }

        // レスポンス解析を追加
        $responseInfo = $this->responseAnalyzer->analyze($controller, $method);
        if ($responseInfo && $responseInfo['type'] !== 'unknown') {
            $result['response'] = $responseInfo;
        }

        return $result;
    }

    /**
     * メソッドのソースコードを取得
     */
    protected function getMethodSource(ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine;

        $source = file($filename);

        return implode('', array_slice($source, $startLine, $length));
    }

    /**
     * クラス名を解決（use文を考慮）
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

        // ファイルのuse文をチェック（簡易版）
        $filename = $reflection->getFileName();
        $content = file_get_contents($filename);

        if (preg_match('/use\s+([\w\\\\]+\\\\'.$className.');/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * メソッドのASTノードを取得
     */
    protected function getMethodNode(ReflectionClass $reflection, string $methodName): ?Node\Stmt\ClassMethod
    {
        try {
            $filename = $reflection->getFileName();
            if (! $filename) {
                return null;
            }

            $code = file_get_contents($filename);
            $ast = $this->parser->parse($code);

            if (! $ast) {
                return null;
            }

            // クラスとメソッドを探す
            foreach ($ast as $node) {
                if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Namespace_) {
                    $classNode = $this->findClassNode($node, $reflection->getShortName());
                    if ($classNode) {
                        foreach ($classNode->stmts as $stmt) {
                            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->name === $methodName) {
                                return $stmt;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // パースエラーの場合はnullを返す
        }

        return null;
    }

    /**
     * クラスノードを探す
     */
    protected function findClassNode(Node $node, string $className): ?Node\Stmt\Class_
    {
        if ($node instanceof Node\Stmt\Class_ && $node->name && $node->name->name === $className) {
            return $node;
        }

        if ($node instanceof Node\Stmt\Namespace_) {
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Class_ && $stmt->name && $stmt->name->name === $className) {
                    return $stmt;
                }
            }
        }

        return null;
    }

    /**
     * Fractal使用を検出
     */
    protected function detectFractalUsage(string $source, array &$result, ReflectionClass $reflection): void
    {
        // fractal()->item() パターン
        if (preg_match('/fractal\(\)->item\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $transformerClass = $this->resolveClassName($matches[1], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                $result['fractal'] = [
                    'transformer' => $transformerClass,
                    'collection' => false,
                    'type' => 'item',
                    'hasIncludes' => strpos($source, 'parseIncludes') !== false,
                ];
            }
        }
        // fractal()->collection() パターン
        elseif (preg_match('/fractal\(\)->collection\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $transformerClass = $this->resolveClassName($matches[1], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                $result['fractal'] = [
                    'transformer' => $transformerClass,
                    'collection' => true,
                    'type' => 'collection',
                    'hasIncludes' => strpos($source, 'parseIncludes') !== false,
                ];
            }
        }
        // fractal()をチェーン呼び出しするパターン
        elseif (preg_match('/fractal\(\)\s*->\s*(item|collection)\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $type = $matches[1];
            $transformerClass = $this->resolveClassName($matches[2], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                $result['fractal'] = [
                    'transformer' => $transformerClass,
                    'collection' => $type === 'collection',
                    'type' => $type,
                    'hasIncludes' => strpos($source, 'parseIncludes') !== false,
                ];
            }
        }
    }

    /**
     * メソッドのEnum型パラメータを解析
     */
    protected function analyzeEnumParameters(ReflectionMethod $method): array
    {
        $enumParameters = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type || ! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();

            // FormRequestはスキップ（既に別で処理されている）
            if (is_subclass_of($className, FormRequest::class)) {
                continue;
            }

            // Enum型かチェック
            if (enum_exists($className)) {
                $enumInfo = $this->enumAnalyzer->extractEnumInfo($className);
                if ($enumInfo) {
                    $enumParameters[] = [
                        'name' => $parameter->getName(),
                        'type' => $enumInfo['type'],
                        'enum' => $enumInfo['values'],
                        'required' => ! $type->allowsNull() && ! $parameter->isOptional(),
                        'description' => "Enum parameter of type {$className}",
                        'in' => 'path', // またはルート定義に基づいて判定
                        'enumClass' => $className,
                    ];
                }
            }
        }

        return $enumParameters;
    }
}
