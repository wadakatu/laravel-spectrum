<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use Illuminate\Support\Str;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Contracts\Analyzers\ClassAnalyzer;
use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\DTO\FractalTransformerResult;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\AstTypeInferenceEngine;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\FieldNameInference;
use LaravelSpectrum\Support\HasErrorCollection;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Analyzes Fractal Transformer classes to extract schema information.
 *
 * This analyzer processes classes extending League\Fractal\TransformerAbstract
 * to extract transform method properties, available/default includes, and metadata
 * for OpenAPI documentation generation.
 */
class FractalTransformerAnalyzer implements ClassAnalyzer, HasErrors
{
    use HasErrorCollection;

    protected AstHelper $astHelper;

    protected AstTypeInferenceEngine $typeInferenceEngine;

    /**
     * Create a new FractalTransformerAnalyzer instance.
     *
     * @param  AstHelper  $astHelper  AstHelper instance for AST operations
     * @param  ErrorCollector|null  $errorCollector  Optional error collector for logging analysis failures
     * @param  AstTypeInferenceEngine|null  $typeInferenceEngine  Optional type inference engine
     */
    public function __construct(
        AstHelper $astHelper,
        ?ErrorCollector $errorCollector = null,
        ?AstTypeInferenceEngine $typeInferenceEngine = null
    ) {
        $this->initializeErrorCollector($errorCollector);
        $this->astHelper = $astHelper;
        $this->typeInferenceEngine = $typeInferenceEngine ?? new AstTypeInferenceEngine;
    }

    /**
     * Fractal Transformerクラスを解析
     *
     * @return array<string, mixed>
     */
    public function analyze(string $transformerClass): array
    {
        return $this->analyzeToResult($transformerClass)->toArray();
    }

    /**
     * Fractal Transformerクラスを解析してDTOを返す
     *
     * @param  string  $transformerClass  The fully qualified class name of the transformer
     * @return FractalTransformerResult The analysis result (use isValid to check success)
     */
    public function analyzeToResult(string $transformerClass): FractalTransformerResult
    {
        if (! class_exists($transformerClass)) {
            $this->logWarning(
                "Class does not exist: {$transformerClass}",
                AnalyzerErrorType::ClassNotFound,
                ['class' => $transformerClass]
            );

            return FractalTransformerResult::empty();
        }

        try {
            $reflection = new \ReflectionClass($transformerClass);

            // League\Fractal\TransformerAbstractを継承しているか確認
            if (! $reflection->isSubclassOf('League\Fractal\TransformerAbstract')) {
                $this->logWarning(
                    "Class {$transformerClass} does not extend League\\Fractal\\TransformerAbstract",
                    AnalyzerErrorType::InvalidParentClass,
                    ['class' => $transformerClass]
                );

                return FractalTransformerResult::empty();
            }

            $filePath = $reflection->getFileName();
            if (! $filePath) {
                $this->logWarning(
                    "Could not determine file path for class: {$transformerClass}",
                    AnalyzerErrorType::FileNotFound,
                    ['class' => $transformerClass]
                );

                return FractalTransformerResult::empty();
            }

            $ast = $this->astHelper->parseFile($filePath);
            if (! $ast) {
                // AstHelper already logs parse errors
                return FractalTransformerResult::empty();
            }

            $classNode = $this->astHelper->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                $this->logWarning(
                    "Could not find class node for {$reflection->getShortName()} in {$filePath}",
                    AnalyzerErrorType::ClassNodeNotFound,
                    [
                        'class' => $transformerClass,
                        'short_name' => $reflection->getShortName(),
                        'file_path' => $filePath,
                    ]
                );

                return FractalTransformerResult::empty();
            }

            return new FractalTransformerResult(
                properties: $this->extractTransformMethod($classNode),
                availableIncludes: $this->extractAvailableIncludes($classNode),
                defaultIncludes: $this->extractDefaultIncludes($classNode),
                meta: $this->extractMetaData($classNode),
            );
        } catch (\ReflectionException $e) {
            $this->logException($e, AnalyzerErrorType::ReflectionError, [
                'class' => $transformerClass,
            ]);

            return FractalTransformerResult::empty();
        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::UnexpectedError, [
                'class' => $transformerClass,
            ]);

            return FractalTransformerResult::empty();
        }
    }

    /**
     * transform()メソッドからプロパティを抽出
     */
    protected function extractTransformMethod(Node\Stmt\Class_ $class): array
    {
        $transformMethod = $this->astHelper->findMethodNode($class, 'transform');
        if (! $transformMethod) {
            return [];
        }

        $properties = [];

        // return文を探す
        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var Node\Expr\Array_|null */
            public ?\PhpParser\Node\Expr\Array_ $returnArray = null;

            /** @var array<string, Node\Expr\Array_> */
            public array $arrayAssignments = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Expr\Assign
                    && $node->var instanceof Node\Expr\Variable
                    && is_string($node->var->name)
                    && $node->expr instanceof Node\Expr\Array_) {
                    $this->arrayAssignments[$node->var->name] = $node->expr;
                }

                if ($node instanceof Node\Stmt\Return_) {
                    if ($node->expr instanceof Node\Expr\Array_) {
                        $this->returnArray = $node->expr;
                    }

                    if ($node->expr instanceof Node\Expr\Variable
                        && is_string($node->expr->name)
                        && isset($this->arrayAssignments[$node->expr->name])) {
                        $this->returnArray = $this->arrayAssignments[$node->expr->name];
                    }
                }

                return null;
            }
        };

        $this->astHelper->traverse([$transformMethod], $visitor);

        if ($visitor->returnArray) {
            $properties = $this->parseArrayNode($visitor->returnArray);
        }

        return $properties;
    }

    /**
     * 配列ノードを解析
     */
    protected function parseArrayNode(Node\Expr\Array_ $array): array
    {
        $properties = [];

        /** @var array<int, Node\Expr\ArrayItem|null> $items */
        $items = $array->items;

        foreach ($items as $item) {
            if (! $item || ! isset($item->key)) {
                continue;
            }

            $key = $this->getNodeValue($item->key);
            if ($key === null || $key === '') {
                continue;
            }

            $value = $item->value;
            $fieldName = (string) $key;
            $typeInfo = $this->typeInferenceEngine->inferFromNode($value);
            $resolvedType = $this->refineTypeFromFieldName($fieldName, $value, $typeInfo);

            $properties[$fieldName] = [
                'type' => $resolvedType['type'] ?? 'string',
                'example' => $this->generateExampleFromNode($fieldName, $value, $resolvedType['type'] ?? null),
                'nullable' => $this->isNullable($value),
            ];

            if (isset($resolvedType['format'])) {
                $properties[$fieldName]['format'] = $resolvedType['format'];
            }

            // ネストした配列の場合
            if ($value instanceof Node\Expr\Array_) {
                $properties[$fieldName]['properties'] = $this->parseArrayNode($value);
                $properties[$fieldName]['type'] = 'object';
            }
        }

        return $properties;
    }

    /**
     * @param  array{type?: string, format?: string, properties?: array<string, mixed>}  $typeInfo
     * @return array{type?: string, format?: string, properties?: array<string, mixed>}
     */
    protected function refineTypeFromFieldName(string $fieldName, Node $value, array $typeInfo): array
    {
        // Keep explicit/stronger inference as-is.
        if (($typeInfo['type'] ?? 'string') !== 'string' || isset($typeInfo['format'])) {
            return $typeInfo;
        }

        // Only refine ambiguous getter/property patterns.
        if (! $value instanceof Node\Expr\MethodCall && ! $value instanceof Node\Expr\PropertyFetch) {
            return $typeInfo;
        }

        $fieldType = $this->inferTypeFromFieldName($fieldName);
        if (($fieldType['type'] ?? 'string') === 'string' && ! isset($fieldType['format'])) {
            return $typeInfo;
        }

        return array_merge($typeInfo, [
            'type' => $fieldType['type'] ?? ($typeInfo['type'] ?? 'string'),
            'format' => $fieldType['format'] ?? ($typeInfo['format'] ?? null),
        ]);
    }

    /**
     * @return array{type: string, format?: string}
     */
    protected function inferTypeFromFieldName(string $fieldName): array
    {
        $inference = (new FieldNameInference)->inferFieldType($fieldName);
        $semanticType = $inference['type'] ?? 'string';
        $semanticFormat = $inference['format'] ?? null;

        $typeMapping = [
            'id' => 'integer',
            'age' => 'integer',
            'quantity' => 'integer',
            'score' => 'integer',
            'money' => 'number',
            'rating' => 'number',
            'location' => 'number',
            'boolean' => 'boolean',
        ];

        $openApiType = $typeMapping[$semanticType] ?? 'string';
        $result = ['type' => $openApiType];

        if ($openApiType !== 'string' || $semanticFormat === null || $semanticFormat === 'text' || $semanticFormat === 'boolean') {
            return $result;
        }

        $formatMapping = [
            'datetime' => 'date-time',
            'date' => 'date',
            'time' => 'time',
            'email' => 'email',
            'url' => 'uri',
            'image_url' => 'uri',
            'avatar_url' => 'uri',
            'uuid' => 'uuid',
        ];

        if (isset($formatMapping[$semanticFormat])) {
            $result['format'] = $formatMapping[$semanticFormat];
        }

        return $result;
    }

    /**
     * ノードから値を取得
     */
    protected function getNodeValue(Node $node): string|int|null
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }
        if ($node instanceof Node\Scalar\LNumber) {
            return $node->value;
        }
        if ($node instanceof Node\Identifier) {
            return $node->toString();
        }

        return null;
    }

    /**
     * nullable判定
     */
    protected function isNullable(Node $node): bool
    {
        // 三項演算子で null を返す場合
        if ($node instanceof Node\Expr\Ternary) {
            if ($node->else instanceof Node\Expr\ConstFetch) {
                $name = $node->else->name->toString();

                return strtolower($name) === 'null';
            }
        }

        // null合体演算子
        if ($node instanceof Node\Expr\BinaryOp\Coalesce) {
            return true;
        }

        return false;
    }

    /**
     * プロパティ名から例を生成
     */
    protected function generateExampleFromNode(string $key, Node $node, ?string $resolvedType = null): int|bool|array|\stdClass|string
    {
        $type = $resolvedType ?? $this->typeInferenceEngine->inferTypeString($node);

        switch ($type) {
            case 'integer':
                if (strpos($key, 'id') !== false) {
                    return 1;
                }
                if (strpos($key, 'count') !== false) {
                    return 100;
                }

                return 42;

            case 'boolean':
                return true;

            case 'array':
                return [];

            case 'object':
                return new \stdClass;

            default:
                // 文字列の場合、キー名から適切な例を生成
                if ($key === 'email') {
                    return 'user@example.com';
                }
                if ($key === 'name') {
                    return 'John Doe';
                }
                if ($key === 'title') {
                    return 'Sample Title';
                }
                if ($key === 'body') {
                    return 'Sample body text';
                }
                if ($key === 'status') {
                    return 'active';
                }
                if ($key === 'type') {
                    return 'default';
                }
                if (strpos($key, 'url') !== false) {
                    return 'https://example.com';
                }
                if (strpos($key, '_at') !== false) {
                    return '2024-01-01T00:00:00+00:00';
                }

                return 'string';
        }
    }

    /**
     * availableIncludesプロパティを抽出
     */
    protected function extractAvailableIncludes(Node\Stmt\Class_ $class): array
    {
        $defaultValue = $this->getPropertyDefaultArray($class, 'availableIncludes');
        if (! $defaultValue) {
            return [];
        }

        $includes = [];
        /** @var array<int, Node\Expr\ArrayItem|null> $items */
        $items = $defaultValue->items;
        foreach ($items as $item) {
            if ($item && isset($item->value) && $item->value instanceof Node\Scalar\String_) {
                $includeName = $item->value->value;
                $includes[$includeName] = $this->analyzeIncludeMethod($class, $includeName);
            }
        }

        return $includes;
    }

    /**
     * defaultIncludesプロパティを抽出
     */
    protected function extractDefaultIncludes(Node\Stmt\Class_ $class): array
    {
        $defaultValue = $this->getPropertyDefaultArray($class, 'defaultIncludes');
        if (! $defaultValue) {
            return [];
        }

        $includes = [];
        /** @var array<int, Node\Expr\ArrayItem|null> $items */
        $items = $defaultValue->items;
        foreach ($items as $item) {
            if ($item && isset($item->value) && $item->value instanceof Node\Scalar\String_) {
                $includes[] = $item->value->value;
            }
        }

        return $includes;
    }

    /**
     * プロパティのデフォルト配列値を取得
     *
     * @param  Node\Stmt\Class_  $class  The class node to search within
     * @param  string  $propertyName  The name of the property to find
     * @return Node\Expr\Array_|null The default array value or null if not found
     */
    protected function getPropertyDefaultArray(Node\Stmt\Class_ $class, string $propertyName): ?Node\Expr\Array_
    {
        $propertyStmt = $this->astHelper->findPropertyNode($class, $propertyName);
        if (! $propertyStmt) {
            return null;
        }

        // Find the property item with the matching name and array default value
        foreach ($propertyStmt->props as $prop) {
            if ($prop->name->toString() === $propertyName && $prop->default instanceof Node\Expr\Array_) {
                return $prop->default;
            }
        }

        return null;
    }

    /**
     * include{Name}メソッドを解析
     */
    protected function analyzeIncludeMethod(Node\Stmt\Class_ $class, string $includeName): array
    {
        $methodName = 'include'.Str::studly($includeName);
        $method = $this->astHelper->findMethodNode($class, $methodName);

        if (! $method) {
            return ['type' => 'unknown'];
        }

        // メソッドの戻り値を解析
        $returnType = $this->analyzeIncludeReturnType($method);

        return [
            'type' => $returnType['type'] ?? 'object',
            'transformer' => $returnType['transformer'] ?? null,
            'collection' => $returnType['collection'] ?? false,
        ];
    }

    /**
     * includeメソッドの戻り値を解析
     */
    protected function analyzeIncludeReturnType(Node\Stmt\ClassMethod $method): array
    {
        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var array<string, mixed> */
            public array $returnInfo = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\Return_ && $node->expr instanceof Node\Expr\MethodCall) {
                    $methodName = $node->expr->name instanceof Node\Identifier ?
                        $node->expr->name->toString() : '';

                    if ($methodName === 'item') {
                        $this->returnInfo['type'] = 'object';
                        $this->returnInfo['collection'] = false;
                    } elseif ($methodName === 'collection') {
                        $this->returnInfo['type'] = 'array';
                        $this->returnInfo['collection'] = true;
                    } elseif ($methodName === 'null') {
                        $this->returnInfo['type'] = 'null';
                        $this->returnInfo['collection'] = false;
                    }

                    // Transformerクラスを取得
                    if (isset($node->expr->args[1]) &&
                        $node->expr->args[1]->value instanceof Node\Expr\New_) {
                        $class = $node->expr->args[1]->value->class;
                        if ($class instanceof Node\Name) {
                            $this->returnInfo['transformer'] = $class->getLast();
                        }
                    }
                }

                return null;
            }
        };

        $this->astHelper->traverse([$method], $visitor);

        return $visitor->returnInfo;
    }

    /**
     * メタデータを抽出（将来の拡張用）
     */
    protected function extractMetaData(Node\Stmt\Class_ $class): array
    {
        // 現在は空配列を返す
        // 将来的にはメタデータ関連のメソッドを解析
        return [];
    }
}
