<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use PhpParser\Node;
use PhpParser\NodeTraverser;

class CollectionAnalyzer
{
    private ModelSchemaExtractor $modelExtractor;

    private AstTypeInferenceEngine $typeInferenceEngine;

    public function __construct(
        ?ModelSchemaExtractor $modelExtractor = null,
        ?AstTypeInferenceEngine $typeInferenceEngine = null
    ) {
        $this->modelExtractor = $modelExtractor ?? new ModelSchemaExtractor;
        $this->typeInferenceEngine = $typeInferenceEngine ?? new AstTypeInferenceEngine;
    }

    /**
     * Analyze a collection method chain and determine the resulting schema.
     *
     * @return array<string, mixed>
     */
    public function analyzeCollectionChain(Node $node): array
    {
        $operations = [];

        // メソッドチェーンを逆順に辿る
        $current = $node;
        while ($current instanceof Node\Expr\MethodCall) {
            if ($current->name instanceof Node\Identifier) {
                $methodName = $current->name->toString();

                $operation = [
                    'method' => $methodName,
                    'args' => $this->extractArguments($current->args),
                ];

                array_unshift($operations, $operation);
            }
            $current = $current->var;
        }

        // 基となるコレクションの型を推測
        $baseType = $this->inferBaseType($current);

        // 各操作を適用してスキーマを変換
        $schema = $baseType;
        foreach ($operations as $operation) {
            $schema = $this->applyOperation($schema, $operation);
        }

        return $schema;
    }

    /**
     * Infer the base type of a collection expression.
     *
     * @return array<string, mixed>
     */
    private function inferBaseType(Node $node): array
    {
        // User::all() や User::get() などのパターン
        if ($node instanceof Node\Expr\StaticCall) {
            if ($node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                $className = $node->class->toString();
                $methodName = $node->name->toString();

                if (in_array($methodName, ['all', 'get'])) {
                    // モデルクラスからスキーマを抽出
                    $modelSchema = $this->modelExtractor->extractSchema($className);
                    if ($modelSchema['type'] === 'object' && ! empty($modelSchema['properties'])) {
                        return [
                            'type' => 'array',
                            'items' => $modelSchema,
                        ];
                    }
                }
            }
        }

        // デフォルトは配列
        return [
            'type' => 'array',
            'items' => ['type' => 'object'],
        ];
    }

    /**
     * Apply a collection operation to a schema.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyOperation(array $schema, array $operation): array
    {
        switch ($operation['method']) {
            case 'map':
                return $this->applyMapOperation($schema, $operation['args']);

            case 'only':
                return $this->applyOnlyOperation($schema, $operation['args']);

            case 'except':
                return $this->applyExceptOperation($schema, $operation['args']);

            case 'pluck':
                return $this->applyPluckOperation($schema, $operation['args']);

            case 'first':
            case 'firstOrFail':
                // コレクションから単一オブジェクトへ
                return $schema['items'] ?? $schema;

            case 'toArray':
                // 既にarray形式なのでそのまま返す
                return $schema;

            case 'values':
                // キーをリセット（配列のまま）
                return $schema;

            case 'keyBy':
                // オブジェクト形式に変換
                if ($schema['type'] === 'array' && isset($schema['items'])) {
                    return [
                        'type' => 'object',
                        'additionalProperties' => $schema['items'],
                    ];
                }

                return $schema;

            default:
                return $schema;
        }
    }

    /**
     * Apply a map operation to transform schema items.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<int, array<string, mixed>>  $args
     * @return array<string, mixed>
     */
    private function applyMapOperation(array $schema, array $args): array
    {
        // mapのコールバック関数を解析
        if (isset($args[0]) && $args[0]['type'] === 'closure') {
            $closure = $args[0]['node'];

            // クロージャのreturn文を解析
            $visitor = new \LaravelSpectrum\Analyzers\AST\Visitors\CollectionMapVisitor;

            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse([$closure]);

            if ($visitor->getReturnStructure()) {
                // return文の構造から新しいスキーマを生成
                $itemSchema = $this->extractStructureFromNode($visitor->getReturnStructure());

                return [
                    'type' => 'array',
                    'items' => $itemSchema,
                ];
            }
        }

        return $schema;
    }

    /**
     * Apply an only operation to filter schema properties.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<int, array<string, mixed>>  $args
     * @return array<string, mixed>
     */
    private function applyOnlyOperation(array $schema, array $args): array
    {
        if ($schema['type'] === 'array' && isset($schema['items']['properties']) && ! empty($args)) {
            $fields = $this->extractStringArray($args);
            $newProperties = [];

            foreach ($fields as $field) {
                if (isset($schema['items']['properties'][$field])) {
                    $newProperties[$field] = $schema['items']['properties'][$field];
                }
            }

            $schema['items']['properties'] = $newProperties;
        }

        return $schema;
    }

    /**
     * Apply an except operation to exclude schema properties.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<int, array<string, mixed>>  $args
     * @return array<string, mixed>
     */
    private function applyExceptOperation(array $schema, array $args): array
    {
        if ($schema['type'] === 'array' && isset($schema['items']['properties']) && ! empty($args)) {
            $excludeFields = $this->extractStringArray($args);

            foreach ($excludeFields as $field) {
                unset($schema['items']['properties'][$field]);
            }
        }

        return $schema;
    }

    /**
     * Apply a pluck operation to extract a single field.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<int, array<string, mixed>>  $args
     * @return array<string, mixed>
     */
    private function applyPluckOperation(array $schema, array $args): array
    {
        if (! empty($args)) {
            $field = $this->extractString($args[0]);

            // 単一フィールドを抽出した配列
            if ($field && isset($schema['items']['properties'][$field])) {
                return [
                    'type' => 'array',
                    'items' => $schema['items']['properties'][$field],
                ];
            }
        }

        return [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ];
    }

    /**
     * Extract arguments from AST nodes.
     *
     * @param  array<int, Node\Arg>  $args
     * @return array<int, array<string, mixed>>
     */
    private function extractArguments(array $args): array
    {
        $result = [];
        foreach ($args as $arg) {
            if ($arg instanceof Node\Arg) {
                $result[] = $this->extractArgumentValue($arg->value);
            }
        }

        return $result;
    }

    /**
     * Extract the value from an argument node.
     *
     * @return array<string, mixed>
     */
    private function extractArgumentValue(Node $node): array
    {
        if ($node instanceof Node\Expr\Closure) {
            return ['type' => 'closure', 'node' => $node];
        }

        if ($node instanceof Node\Expr\Array_) {
            $items = [];
            foreach ($node->items as $item) {
                if ($item && $item->value instanceof Node\Scalar\String_) {
                    $items[] = $item->value->value;
                }
            }

            return ['type' => 'array', 'value' => $items];
        }

        if ($node instanceof Node\Scalar\String_) {
            return ['type' => 'string', 'value' => $node->value];
        }

        return ['type' => 'unknown'];
    }

    /**
     * Extract an array of strings from arguments.
     *
     * @param  array<int, array<string, mixed>>  $args
     * @return array<int, string>
     */
    private function extractStringArray(array $args): array
    {
        if (isset($args[0]) && $args[0]['type'] === 'array') {
            return $args[0]['value'];
        }

        return [];
    }

    /**
     * Extract a string value from an argument.
     *
     * @param  array<string, mixed>  $arg
     */
    private function extractString(array $arg): ?string
    {
        if ($arg['type'] === 'string') {
            return $arg['value'];
        }

        return null;
    }

    /**
     * Extract schema structure from a node.
     *
     * @return array<string, mixed>
     */
    private function extractStructureFromNode(Node $node): array
    {
        if ($node instanceof Node\Expr\Array_) {
            $properties = [];

            foreach ($node->items as $item) {
                if ($item && $item->key instanceof Node\Scalar\String_) {
                    $key = $item->key->value;
                    $properties[$key] = $this->typeInferenceEngine->inferFromNode($item->value);
                }
            }

            return [
                'type' => 'object',
                'properties' => $properties,
            ];
        }

        return ['type' => 'object'];
    }
}
