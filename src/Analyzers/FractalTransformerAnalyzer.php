<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use Illuminate\Support\Str;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\HasErrorCollection;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Analyzes Fractal Transformer classes to extract schema information.
 *
 * This analyzer processes classes extending League\Fractal\TransformerAbstract
 * to extract transform method properties, available/default includes, and metadata
 * for OpenAPI documentation generation.
 */
class FractalTransformerAnalyzer
{
    use HasErrorCollection;

    protected AstHelper $astHelper;

    /**
     * Create a new FractalTransformerAnalyzer instance.
     *
     * @param  AstHelper  $astHelper  AstHelper instance for AST operations
     * @param  ErrorCollector|null  $errorCollector  Optional error collector for logging analysis failures
     */
    public function __construct(
        AstHelper $astHelper,
        ?ErrorCollector $errorCollector = null
    ) {
        $this->initializeErrorCollector($errorCollector);
        $this->astHelper = $astHelper;
    }

    /**
     * Fractal Transformerクラスを解析
     */
    public function analyze(string $transformerClass): array
    {
        if (! class_exists($transformerClass)) {
            $this->logWarning(
                "Class does not exist: {$transformerClass}",
                AnalyzerErrorType::CLASS_NOT_FOUND,
                ['class' => $transformerClass]
            );

            return [];
        }

        try {
            $reflection = new \ReflectionClass($transformerClass);

            // League\Fractal\TransformerAbstractを継承しているか確認
            if (! $reflection->isSubclassOf('League\Fractal\TransformerAbstract')) {
                $this->logWarning(
                    "Class {$transformerClass} does not extend League\\Fractal\\TransformerAbstract",
                    AnalyzerErrorType::INVALID_PARENT_CLASS,
                    ['class' => $transformerClass]
                );

                return [];
            }

            $filePath = $reflection->getFileName();
            if (! $filePath) {
                $this->logWarning(
                    "Could not determine file path for class: {$transformerClass}",
                    AnalyzerErrorType::FILE_NOT_FOUND,
                    ['class' => $transformerClass]
                );

                return [];
            }

            $ast = $this->astHelper->parseFile($filePath);
            if (! $ast) {
                // AstHelper already logs parse errors
                return [];
            }

            $classNode = $this->astHelper->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                $this->logWarning(
                    "Could not find class node for {$reflection->getShortName()} in {$filePath}",
                    AnalyzerErrorType::CLASS_NODE_NOT_FOUND,
                    [
                        'class' => $transformerClass,
                        'short_name' => $reflection->getShortName(),
                        'file_path' => $filePath,
                    ]
                );

                return [];
            }

            return [
                'type' => 'fractal',
                'properties' => $this->extractTransformMethod($classNode),
                'availableIncludes' => $this->extractAvailableIncludes($classNode),
                'defaultIncludes' => $this->extractDefaultIncludes($classNode),
                'meta' => $this->extractMetaData($classNode),
            ];
        } catch (\ReflectionException $e) {
            $this->logException($e, AnalyzerErrorType::REFLECTION_ERROR, [
                'class' => $transformerClass,
            ]);

            return [];
        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::UNEXPECTED_ERROR, [
                'class' => $transformerClass,
            ]);

            return [];
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
            public $returnArray = null;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Return_ && $node->expr instanceof Node\Expr\Array_) {
                    $this->returnArray = $node->expr;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$transformMethod]);

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
            if (! $key) {
                continue;
            }

            $value = $item->value;

            $properties[$key] = [
                'type' => $this->inferTypeFromNode($value),
                'example' => $this->generateExampleFromNode($key, $value),
                'nullable' => $this->isNullable($value),
            ];

            // ネストした配列の場合
            if ($value instanceof Node\Expr\Array_) {
                $properties[$key]['properties'] = $this->parseArrayNode($value);
                $properties[$key]['type'] = 'object';
            }
        }

        return $properties;
    }

    /**
     * ノードから値を取得
     */
    protected function getNodeValue(Node $node)
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
     * ノードから型を推測
     */
    protected function inferTypeFromNode(Node $node): string
    {
        // キャスト演算子のチェック
        if ($node instanceof Node\Expr\Cast\Int_) {
            return 'integer';
        }
        if ($node instanceof Node\Expr\Cast\String_) {
            return 'string';
        }
        if ($node instanceof Node\Expr\Cast\Bool_) {
            return 'boolean';
        }
        if ($node instanceof Node\Expr\Cast\Array_) {
            return 'array';
        }

        // 関数呼び出しのチェック
        if ($node instanceof Node\Expr\FuncCall) {
            $funcName = $node->name instanceof Node\Name ? $node->name->toString() : '';
            if ($funcName === 'json_decode') {
                return 'array';
            }
        }

        // 配列
        if ($node instanceof Node\Expr\Array_) {
            // 連想配列かどうかチェック
            $hasStringKeys = false;
            /** @var array<int, Node\Expr\ArrayItem|null> $items */
            $items = $node->items;
            foreach ($items as $item) {
                if ($item && isset($item->key) && $item->key instanceof Node\Scalar\String_) {
                    $hasStringKeys = true;
                    break;
                }
            }

            return $hasStringKeys ? 'object' : 'array';
        }

        // プロパティアクセス
        if ($node instanceof Node\Expr\PropertyFetch) {
            $propName = $node->name instanceof Node\Identifier ? $node->name->toString() : '';

            // 一般的なプロパティ名から型を推測
            if (in_array($propName, ['id', 'count', 'view_count', 'like_count', 'share_count'])) {
                return 'integer';
            }
            if (in_array($propName, ['is_active', 'is_featured', 'is_archived'])) {
                return 'boolean';
            }
            if (in_array($propName, ['created_at', 'updated_at', 'published_at', 'processed_at'])) {
                return 'string';
            }
            if (in_array($propName, ['attributes', 'settings', 'data'])) {
                return 'object';
            }
        }

        // 三項演算子
        if ($node instanceof Node\Expr\Ternary) {
            // 三項演算子の場合: condition ? if : else
            // $node->if が null の場合は省略形: condition ?: else
            if ($node->if !== null) {
                return $this->inferTypeFromNode($node->if);
            }

            // 省略形の場合は条件式の型を返す
            return $this->inferTypeFromNode($node->cond);
        }

        // メソッド呼び出し
        if ($node instanceof Node\Expr\MethodCall) {
            $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : '';
            if (in_array($methodName, ['toIso8601String', 'toString', 'toDateTimeString'])) {
                return 'string';
            }
            if (in_array($methodName, ['toArray'])) {
                return 'array';
            }
        }

        return 'string'; // デフォルト
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
    protected function generateExampleFromNode(string $key, Node $node)
    {
        $type = $this->inferTypeFromNode($node);

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
            public $returnInfo = [];

            public function enterNode(Node $node)
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

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$method]);

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
