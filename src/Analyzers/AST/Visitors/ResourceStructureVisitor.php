<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

/**
 * Laravel API Resource の toArray() メソッドから構造情報を抽出するASTビジター
 *
 * JsonResource クラスの toArray() メソッドの返り値配列を解析し、
 * OpenAPI スキーマ生成に必要なプロパティ情報、条件付きフィールド、
 * ネストされたリソースクラスを抽出する。
 *
 * @see \LaravelSpectrum\Analyzers\ResourceAnalyzer
 */
class ResourceStructureVisitor extends NodeVisitorAbstract
{
    private array $structure = [];

    private array $conditionalFields = [];

    private array $nestedResources = [];

    private PrettyPrinter\Standard $printer;

    private int $depth = 0;

    public function __construct(PrettyPrinter\Standard $printer)
    {
        $this->printer = $printer;
    }

    public function enterNode(Node $node)
    {
        // クロージャに入る場合は深さを増やす
        if ($node instanceof Node\Expr\Closure) {
            $this->depth++;
        }

        // return文を検出（最上位レベルのみ）
        if ($node instanceof Node\Stmt\Return_ && $node->expr && $this->depth === 0) {
            // 配列を直接返す場合
            if ($node->expr instanceof Node\Expr\Array_) {
                $this->structure = $this->analyzeArrayStructure($node->expr);
            }
            // array_merge()などの関数呼び出し
            elseif ($node->expr instanceof Node\Expr\FuncCall) {
                $this->structure = $this->analyzeFunctionCall($node->expr);
            }
            // 変数を返す場合
            elseif ($node->expr instanceof Node\Expr\Variable) {
                $this->structure = ['_notice' => 'Dynamic structure detected - manual review required'];
            }
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        // クロージャを出る場合は深さを減らす
        if ($node instanceof Node\Expr\Closure) {
            $this->depth--;
        }

        return null;
    }

    /**
     * 配列構造を解析
     */
    private function analyzeArrayStructure(Node\Expr\Array_ $array): array
    {
        $structure = [];

        foreach ($array->items as $item) {
            $key = $this->getKeyName($item->key);
            if (! $key) {
                continue;
            }

            $structure[$key] = $this->analyzeValue($item->value);
        }

        return $structure;
    }

    /**
     * キー名を取得
     */
    private function getKeyName(Node $expr): ?string
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        // 動的なキーの場合
        return null;
    }

    /**
     * 値の型と構造を解析
     */
    private function analyzeValue(Node $expr): array
    {
        $info = [
            'type' => 'mixed',
            'nullable' => false,
            'source' => null,
            'example' => null,
        ];

        // スカラー値
        if ($expr instanceof Node\Scalar\String_) {
            $info['type'] = 'string';
            $info['example'] = $expr->value;
        } elseif ($expr instanceof Node\Scalar\LNumber) {
            $info['type'] = 'integer';
            $info['example'] = $expr->value;
        } elseif ($expr instanceof Node\Scalar\DNumber) {
            $info['type'] = 'number';
            $info['example'] = $expr->value;
        }

        // $this->property
        elseif ($expr instanceof Node\Expr\PropertyFetch &&
                $expr->var instanceof Node\Expr\Variable &&
                $expr->var->name === 'this') {
            $propertyName = $expr->name->toString();
            $info = $this->analyzePropertyAccess($propertyName);
        }

        // $this->property->method() (プロパティのメソッドチェーン)
        elseif ($expr instanceof Node\Expr\MethodCall &&
                $expr->var instanceof Node\Expr\PropertyFetch &&
                $expr->var->var instanceof Node\Expr\Variable &&
                $expr->var->var->name === 'this') {
            // プロパティに対するメソッド呼び出し
            $methodName = $expr->name->toString();
            if ($methodName === 'pluck') {
                $info = ['type' => 'array'];
            } else {
                $info = $this->analyzeMethodChain($expr);
            }
        }

        // $this->when()
        elseif ($expr instanceof Node\Expr\MethodCall &&
                $expr->var instanceof Node\Expr\Variable &&
                $expr->var->name === 'this' &&
                $expr->name->toString() === 'when') {
            $info = $this->analyzeWhenMethod($expr);
        }

        // $this->whenLoaded()
        elseif ($expr instanceof Node\Expr\MethodCall &&
                $expr->var instanceof Node\Expr\Variable &&
                $expr->var->name === 'this' &&
                $expr->name->toString() === 'whenLoaded') {
            $info = $this->analyzeWhenLoadedMethod($expr);
        }

        // Resource::collection()
        elseif ($expr instanceof Node\Expr\StaticCall) {
            $info = $this->analyzeStaticCall($expr);
        }

        // new Resource()
        elseif ($expr instanceof Node\Expr\New_) {
            $info = $this->analyzeNewResource($expr);
        }

        // プロパティのメソッド/プロパティアクセス (例: $this->status->value)
        elseif ($expr instanceof Node\Expr\PropertyFetch &&
                $expr->var instanceof Node\Expr\PropertyFetch &&
                $expr->var->var instanceof Node\Expr\Variable &&
                $expr->var->var->name === 'this') {
            // $this->property->value のようなパターン
            $propertyName = $expr->name->toString();
            if ($propertyName === 'value') {
                // Enumのvalueアクセス
                $info = [
                    'type' => 'string',
                    'source' => 'enum',
                ];
            } else {
                $info = ['type' => 'mixed'];
            }
        }

        // Null-safe プロパティアクセス (例: $this->status?->value)
        elseif ($expr instanceof Node\Expr\NullsafePropertyFetch) {
            $info = $this->analyzeNullsafePropertyFetch($expr);
        }

        // Null-safe メソッド呼び出し (例: $this->created_at?->toDateTimeString())
        elseif ($expr instanceof Node\Expr\NullsafeMethodCall) {
            $info = $this->analyzeNullsafeMethodCall($expr);
        }

        // メソッドチェーン (例: $this->created_at->format())
        elseif ($expr instanceof Node\Expr\MethodCall) {
            $info = $this->analyzeMethodChain($expr);
        }

        // キャスト (例: (bool) $this->value)
        elseif ($expr instanceof Node\Expr\Cast) {
            $info = $this->analyzeCast($expr);
        }

        // 配列
        elseif ($expr instanceof Node\Expr\Array_) {
            $info['type'] = 'object';
            $info['properties'] = $this->analyzeArrayStructure($expr);
        }

        // 関数呼び出し (例: number_format())
        elseif ($expr instanceof Node\Expr\FuncCall) {
            $info = $this->analyzeFunctionCall($expr);
        }

        // 文字列連結
        elseif ($expr instanceof Node\Expr\BinaryOp\Concat) {
            $info['type'] = 'string';
        }

        // その他の複雑な式
        elseif ($expr instanceof Node\Expr) {
            $info['expression'] = $this->printer->prettyPrintExpr($expr);
        }

        return $info;
    }

    /**
     * プロパティアクセスを解析
     */
    private function analyzePropertyAccess(string $property): array
    {
        $info = ['source' => 'property', 'property' => $property];

        // プロパティ名から型を推論
        $info['type'] = $this->inferTypeFromPropertyName($property);
        $info['example'] = $this->generateExampleFromProperty($property);

        return $info;
    }

    /**
     * when()メソッドを解析
     */
    private function analyzeWhenMethod(Node\Expr\MethodCall $call): array
    {
        $info = [
            'type' => 'mixed',
            'conditional' => true,
            'condition' => 'when',
        ];

        // 第2引数が値の場合
        if (isset($call->args[1])) {
            $valueNode = $call->args[1]->value;

            // クロージャの場合
            if ($valueNode instanceof Node\Expr\Closure) {
                $info['type'] = 'mixed'; // クロージャの戻り値は解析が複雑
            }
            // 直接値の場合
            else {
                $valueInfo = $this->analyzeValue($valueNode);
                $info = array_merge($info, $valueInfo);
            }
        }

        $this->conditionalFields[] = $this->printer->prettyPrintExpr($call);

        return $info;
    }

    /**
     * whenLoaded()メソッドを解析
     */
    private function analyzeWhenLoadedMethod(Node\Expr\MethodCall $call): array
    {
        $info = [
            'type' => 'mixed',
            'conditional' => true,
            'condition' => 'whenLoaded',
        ];

        if (isset($call->args[0])) {
            $relation = $call->args[0]->value;
            if ($relation instanceof Node\Scalar\String_) {
                $info['relation'] = $relation->value;

                // リレーション名から型を推論
                if (str_ends_with($relation->value, 's')) {
                    $info['type'] = 'array';
                } else {
                    $info['type'] = 'object';
                }
            }
        }

        // 第2引数（クロージャ）がある場合
        if (isset($call->args[1])) {
            $info['hasTransformation'] = true;
        }

        $this->conditionalFields[] = $this->printer->prettyPrintExpr($call);

        return $info;
    }

    /**
     * 静的メソッド呼び出しを解析（Resource::collection()など）
     */
    private function analyzeStaticCall(Node\Expr\StaticCall $call): array
    {
        $info = ['type' => 'mixed'];

        if ($call->class instanceof Node\Name) {
            $className = $call->class->toString();

            // ResourceクラスのCollection
            if (str_ends_with($className, 'Resource') &&
                $call->name->toString() === 'collection') {
                $info['type'] = 'array';
                $info['items'] = [
                    'type' => 'object',
                    'resource' => $className,
                ];

                // 引数がwhenLoaded()の場合は条件付きフィールドとして扱う
                if (isset($call->args[0]) &&
                    $call->args[0]->value instanceof Node\Expr\MethodCall &&
                    $call->args[0]->value->name->toString() === 'whenLoaded') {
                    $whenLoadedInfo = $this->analyzeWhenLoadedMethod($call->args[0]->value);
                    $info = array_merge($info, $whenLoadedInfo);
                }

                $this->nestedResources[] = $className;
            }
        }

        return $info;
    }

    /**
     * new Resource()を解析
     */
    private function analyzeNewResource(Node\Expr\New_ $new): array
    {
        $info = ['type' => 'object'];

        if ($new->class instanceof Node\Name) {
            $className = $new->class->toString();

            if (str_ends_with($className, 'Resource')) {
                $info['resource'] = $className;

                // 引数がwhenLoaded()の場合は条件付きフィールドとして扱う
                if (isset($new->args[0]) &&
                    $new->args[0]->value instanceof Node\Expr\MethodCall &&
                    $new->args[0]->value->name->toString() === 'whenLoaded') {
                    $whenLoadedInfo = $this->analyzeWhenLoadedMethod($new->args[0]->value);
                    $info = array_merge($info, $whenLoadedInfo);
                }

                $this->nestedResources[] = $className;
            }
        }

        return $info;
    }

    /**
     * Null-safe プロパティアクセスを解析 (例: $this->status?->value)
     *
     * @return array<string, mixed>
     */
    private function analyzeNullsafePropertyFetch(Node\Expr\NullsafePropertyFetch $expr): array
    {
        // 動的プロパティ名（$this->obj?->$dynamicProperty）は静的解析不可
        if (! ($expr->name instanceof Node\Identifier)) {
            return ['type' => 'mixed', 'nullable' => true];
        }

        $propertyName = $expr->name->toString();

        // $this->property?->value パターン (Enum)
        if ($propertyName === 'value') {
            // var が NullsafePropertyFetch または PropertyFetch で $this-> から始まる場合
            if ($this->isThisPropertyAccess($expr->var)) {
                return [
                    'type' => 'string',
                    'source' => 'enum',
                    'nullable' => true,
                ];
            }
        }

        // $this->relation?->property パターン
        if ($this->isThisPropertyAccess($expr->var)) {
            $info = $this->analyzePropertyAccess($propertyName);
            $info['nullable'] = true;

            return $info;
        }

        return ['type' => 'mixed', 'nullable' => true];
    }

    /**
     * Null-safe メソッド呼び出しを解析 (例: $this->created_at?->toDateTimeString())
     *
     * @return array<string, mixed>
     */
    private function analyzeNullsafeMethodCall(Node\Expr\NullsafeMethodCall $call): array
    {
        // 動的メソッド名（$this->obj?->$dynamicMethod()）は静的解析不可
        if (! ($call->name instanceof Node\Identifier)) {
            return ['type' => 'mixed', 'nullable' => true];
        }

        $methodName = $call->name->toString();

        // 日付フォーマットメソッド
        if ($this->isDateFormattingMethod($methodName)) {
            return [
                'type' => 'string',
                'format' => 'date-time',
                'example' => date('Y-m-d H:i:s'),
                'nullable' => true,
            ];
        }

        // has*, is* メソッド (boolean を返すメソッド)
        if (str_starts_with($methodName, 'has') || str_starts_with($methodName, 'is')) {
            return ['type' => 'boolean', 'nullable' => true];
        }

        return ['type' => 'mixed', 'nullable' => true];
    }

    /**
     * $this->property アクセスかどうかを判定
     */
    private function isThisPropertyAccess(Node $node): bool
    {
        // $this->property
        if ($node instanceof Node\Expr\PropertyFetch &&
            $node->var instanceof Node\Expr\Variable &&
            $node->var->name === 'this') {
            return true;
        }

        // $this?->property (あまり一般的ではないが対応)
        if ($node instanceof Node\Expr\NullsafePropertyFetch &&
            $node->var instanceof Node\Expr\Variable &&
            $node->var->name === 'this') {
            return true;
        }

        return false;
    }

    /**
     * 日付フォーマットメソッドかどうかを判定
     *
     * Carbon の日付フォーマットメソッドはパターンで判定:
     * - format(), isoFormat(), translatedFormat() - 汎用フォーマット
     * - diffForHumans(), ago() - 人間可読形式
     * - to*String() - 各種フォーマットへの変換 (toDateTimeString, toIso8601String 等)
     */
    private function isDateFormattingMethod(string $methodName): bool
    {
        // 明示的な日付フォーマットメソッド
        $dateFormattingMethods = [
            'format',
            'isoFormat',
            'translatedFormat',
            'diffForHumans',
            'ago',
            'calendar',
            'longAbsoluteDiffForHumans',
            'shortAbsoluteDiffForHumans',
        ];

        if (in_array($methodName, $dateFormattingMethods, true)) {
            return true;
        }

        // to*String パターン (toDateString, toIso8601String, toRfc3339String 等)
        return str_starts_with($methodName, 'to') && str_ends_with($methodName, 'String');
    }

    /**
     * メソッドチェーンを解析
     */
    private function analyzeMethodChain(Node\Expr\MethodCall $call): array
    {
        $methodName = $call->name->toString();

        // 日付フォーマット
        if ($this->isDateFormattingMethod($methodName)) {
            return [
                'type' => 'string',
                'format' => 'date-time',
                'example' => date('Y-m-d H:i:s'),
            ];
        }

        // Enumのvalue
        if ($methodName === 'value' && $call->var instanceof Node\Expr\PropertyFetch) {
            return [
                'type' => 'string',
                'source' => 'enum',
            ];
        }

        // count() メソッド
        if ($methodName === 'count') {
            return ['type' => 'integer'];
        }

        // pluck() メソッド
        if ($methodName === 'pluck') {
            return ['type' => 'array'];
        }

        // has*, is* メソッド (boolean を返すメソッド)
        if (str_starts_with($methodName, 'has') || str_starts_with($methodName, 'is')) {
            return ['type' => 'boolean'];
        }

        // $this->relation()->method() パターン
        if ($call->var instanceof Node\Expr\MethodCall &&
            $call->var->var instanceof Node\Expr\Variable &&
            $call->var->var->name === 'this') {
            // リレーションのメソッドチェーン
            if ($methodName === 'count') {
                return ['type' => 'integer'];
            }
        }

        return ['type' => 'mixed'];
    }

    /**
     * キャストを解析
     */
    private function analyzeCast(Node\Expr\Cast $cast): array
    {
        if ($cast instanceof Node\Expr\Cast\Bool_) {
            return ['type' => 'boolean'];
        } elseif ($cast instanceof Node\Expr\Cast\Int_) {
            return ['type' => 'integer'];
        } elseif ($cast instanceof Node\Expr\Cast\Double) {
            return ['type' => 'number'];
        } elseif ($cast instanceof Node\Expr\Cast\String_) {
            return ['type' => 'string'];
        } elseif ($cast instanceof Node\Expr\Cast\Array_) {
            return ['type' => 'array'];
        } elseif ($cast instanceof Node\Expr\Cast\Object_) {
            return ['type' => 'object'];
        }

        return ['type' => 'mixed'];
    }

    /**
     * 関数呼び出しを解析
     */
    private function analyzeFunctionCall(Node\Expr\FuncCall $call): array
    {
        if ($call->name instanceof Node\Name) {
            $functionName = $call->name->toString();

            // 数値フォーマット関数
            if (in_array($functionName, ['number_format', 'round', 'floor', 'ceil'])) {
                return ['type' => 'number'];
            }

            // 文字列関数
            if (in_array($functionName, ['strtoupper', 'strtolower', 'ucfirst', 'trim', 'str_replace', 'substr', 'strlen'])) {
                return ['type' => 'string'];
            }

            // array_merge
            if ($functionName === 'array_merge') {
                return ['type' => 'object', 'merged' => true];
            }
        }

        return ['type' => 'mixed'];
    }

    /**
     * プロパティ名から型を推論
     */
    private function inferTypeFromPropertyName(string $property): string
    {
        $typeMap = [
            'id' => 'integer',
            'uuid' => 'string',
            'name' => 'string',
            'title' => 'string',
            'description' => 'string',
            'email' => 'string',
            'phone' => 'string',
            'url' => 'string',
            'price' => 'number',
            'amount' => 'number',
            'total' => 'number',
            'cost' => 'number',
            'count' => 'integer',
            'quantity' => 'integer',
            'is_' => 'boolean',
            'has_' => 'boolean',
            'can_' => 'boolean',
            '_at' => 'string', // timestamps
            '_date' => 'string',
            'status' => 'string',
            'type' => 'string',
            'image' => 'string',
            'photo' => 'string',
            'avatar' => 'string',
            'data' => 'object',
            'meta' => 'object',
            'metadata' => 'object',
            'settings' => 'object',
            'config' => 'object',
        ];

        // 完全一致
        if (isset($typeMap[$property])) {
            return $typeMap[$property];
        }

        // プレフィックス/サフィックスで判定
        foreach ($typeMap as $pattern => $type) {
            if (str_starts_with($property, $pattern) || str_ends_with($property, $pattern)) {
                return $type;
            }
        }

        // 複数形は配列の可能性
        if (preg_match('/s$/', $property) && ! in_array($property, ['status', 'address'])) {
            return 'array';
        }

        return 'string'; // デフォルト
    }

    /**
     * プロパティ名から例を生成
     */
    private function generateExampleFromProperty(string $property): mixed
    {
        $examples = [
            'id' => 1,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'John Doe',
            'email' => 'user@example.com',
            'phone' => '+1-555-555-5555',
            'price' => 99.99,
            'quantity' => 10,
            'is_active' => true,
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-01T00:00:00Z',
        ];

        return $examples[$property] ?? null;
    }

    /**
     * 解析結果の構造を取得
     *
     * @return array{
     *     properties: array<string, array<string, mixed>>,
     *     conditionalFields: list<string>,
     *     nestedResources: list<string>
     * }
     */
    public function getStructure(): array
    {
        return [
            'properties' => $this->structure,
            'conditionalFields' => array_unique($this->conditionalFields),
            'nestedResources' => array_unique($this->nestedResources),
        ];
    }
}
