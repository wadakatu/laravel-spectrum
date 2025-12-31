<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use LaravelSpectrum\DTO\ResourceFieldInfo;
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
    /** @var array<string, ResourceFieldInfo> */
    private array $structure = [];

    /** @var list<string> */
    private array $conditionalFields = [];

    /** @var list<string> */
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
                $this->structure = $this->analyzeTopLevelFunctionCall($node->expr);
            }
            // 変数を返す場合
            elseif ($node->expr instanceof Node\Expr\Variable) {
                $this->structure = [
                    '_notice' => ResourceFieldInfo::withExpression('Dynamic structure detected - manual review required'),
                ];
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
     *
     * @return array<string, ResourceFieldInfo>
     */
    private function analyzeArrayStructure(Node\Expr\Array_ $array): array
    {
        $structure = [];

        foreach ($array->items as $item) {
            // Skip null items
            if ($item === null) {
                continue;
            }

            // Handle keyless items (like mergeWhen, merge, or spread operator)
            if ($item->key === null) {
                // Check for spread operator
                if ($item->unpack) {
                    // Skip spread operators - we can't determine the structure statically
                    continue;
                }

                // Check for $this->mergeWhen() or $this->merge() pattern
                $mergedProperties = $this->analyzeMergeMethod($item->value);
                if ($mergedProperties !== null) {
                    foreach ($mergedProperties as $key => $value) {
                        $structure[$key] = $value;
                    }
                }

                continue;
            }

            $key = $this->getKeyName($item->key);
            if (! $key) {
                continue;
            }

            $structure[$key] = $this->analyzeValue($item->value);
        }

        return $structure;
    }

    /**
     * Analyze $this->mergeWhen() or $this->merge() method calls
     *
     * @return array<string, ResourceFieldInfo>|null
     */
    private function analyzeMergeMethod(Node $expr): ?array
    {
        // Must be a method call on $this
        if (! $expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (! $expr->var instanceof Node\Expr\Variable || $expr->var->name !== 'this') {
            return null;
        }

        // Get method name
        if (! $expr->name instanceof Node\Identifier) {
            return null;
        }

        $methodName = $expr->name->name;

        // Handle mergeWhen($condition, $array)
        if ($methodName === 'mergeWhen') {
            // Second argument should be the array to merge
            if (count($expr->args) >= 2 && $expr->args[1]->value instanceof Node\Expr\Array_) {
                return $this->extractMergeProperties($expr->args[1]->value, 'mergeWhen');
            }

            return null;
        }

        // Handle merge($array)
        if ($methodName === 'merge') {
            // First argument should be the array to merge
            if (count($expr->args) >= 1 && $expr->args[0]->value instanceof Node\Expr\Array_) {
                return $this->extractMergeProperties($expr->args[0]->value, 'merge');
            }

            return null;
        }

        return null;
    }

    /**
     * Extract properties from a merge/mergeWhen array argument
     *
     * @return array<string, ResourceFieldInfo>
     */
    private function extractMergeProperties(Node\Expr\Array_ $array, string $conditionType): array
    {
        $properties = [];

        foreach ($array->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $key = $this->getKeyName($item->key);
            if (! $key) {
                continue;
            }

            $fieldInfo = $this->analyzeValue($item->value);

            // Mark as conditional for mergeWhen (always conditional)
            // For merge(), it's not conditional but still merged
            if ($conditionType === 'mergeWhen') {
                $fieldInfo = $fieldInfo->withConditional('mergeWhen');
            }

            $properties[$key] = $fieldInfo;
        }

        return $properties;
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
    private function analyzeValue(Node $expr): ResourceFieldInfo
    {
        // スカラー値
        if ($expr instanceof Node\Scalar\String_) {
            return ResourceFieldInfo::string()->withExample($expr->value);
        }
        if ($expr instanceof Node\Scalar\LNumber) {
            return ResourceFieldInfo::integer()->withExample($expr->value);
        }
        if ($expr instanceof Node\Scalar\DNumber) {
            return ResourceFieldInfo::number()->withExample($expr->value);
        }

        // $this->property
        if ($expr instanceof Node\Expr\PropertyFetch &&
            $expr->var instanceof Node\Expr\Variable &&
            $expr->var->name === 'this') {
            $propertyName = $expr->name->toString();

            return $this->analyzePropertyAccess($propertyName);
        }

        // $this->property->method() (プロパティのメソッドチェーン)
        if ($expr instanceof Node\Expr\MethodCall &&
            $expr->var instanceof Node\Expr\PropertyFetch &&
            $expr->var->var instanceof Node\Expr\Variable &&
            $expr->var->var->name === 'this') {
            // プロパティに対するメソッド呼び出し
            $methodName = $expr->name->toString();
            if ($methodName === 'pluck') {
                return ResourceFieldInfo::array();
            }

            return $this->analyzeMethodChain($expr);
        }

        // $this->when()
        if ($expr instanceof Node\Expr\MethodCall &&
            $expr->var instanceof Node\Expr\Variable &&
            $expr->var->name === 'this' &&
            $expr->name->toString() === 'when') {
            return $this->analyzeWhenMethod($expr);
        }

        // $this->whenLoaded()
        if ($expr instanceof Node\Expr\MethodCall &&
            $expr->var instanceof Node\Expr\Variable &&
            $expr->var->name === 'this' &&
            $expr->name->toString() === 'whenLoaded') {
            return $this->analyzeWhenLoadedMethod($expr);
        }

        // Resource::collection()
        if ($expr instanceof Node\Expr\StaticCall) {
            return $this->analyzeStaticCall($expr);
        }

        // new Resource()
        if ($expr instanceof Node\Expr\New_) {
            return $this->analyzeNewResource($expr);
        }

        // プロパティのメソッド/プロパティアクセス (例: $this->status->value)
        if ($expr instanceof Node\Expr\PropertyFetch &&
            $expr->var instanceof Node\Expr\PropertyFetch &&
            $expr->var->var instanceof Node\Expr\Variable &&
            $expr->var->var->name === 'this') {
            // $this->property->value のようなパターン
            $propertyName = $expr->name->toString();
            if ($propertyName === 'value') {
                // Enumのvalueアクセス
                return ResourceFieldInfo::enum();
            }

            return ResourceFieldInfo::mixed();
        }

        // Null-safe プロパティアクセス (例: $this->status?->value)
        if ($expr instanceof Node\Expr\NullsafePropertyFetch) {
            return $this->analyzeNullsafePropertyFetch($expr);
        }

        // Null-safe メソッド呼び出し (例: $this->created_at?->toDateTimeString())
        if ($expr instanceof Node\Expr\NullsafeMethodCall) {
            return $this->analyzeNullsafeMethodCall($expr);
        }

        // メソッドチェーン (例: $this->created_at->format())
        if ($expr instanceof Node\Expr\MethodCall) {
            return $this->analyzeMethodChain($expr);
        }

        // キャスト (例: (bool) $this->value)
        if ($expr instanceof Node\Expr\Cast) {
            return $this->analyzeCast($expr);
        }

        // 配列
        if ($expr instanceof Node\Expr\Array_) {
            $properties = $this->convertToArrayFormat($this->analyzeArrayStructure($expr));

            return ResourceFieldInfo::object()->withProperties($properties);
        }

        // 関数呼び出し (例: number_format())
        if ($expr instanceof Node\Expr\FuncCall) {
            return $this->analyzeFunctionCall($expr);
        }

        // 文字列連結
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return ResourceFieldInfo::string();
        }

        // その他の複雑な式
        if ($expr instanceof Node\Expr) {
            return ResourceFieldInfo::withExpression($this->printer->prettyPrintExpr($expr));
        }

        return ResourceFieldInfo::mixed();
    }

    /**
     * プロパティアクセスを解析
     */
    private function analyzePropertyAccess(string $property): ResourceFieldInfo
    {
        $type = $this->inferTypeFromPropertyName($property);
        $example = $this->generateExampleFromProperty($property);

        return ResourceFieldInfo::property($property, $type, $example);
    }

    /**
     * when()メソッドを解析
     */
    private function analyzeWhenMethod(Node\Expr\MethodCall $call): ResourceFieldInfo
    {
        $type = 'mixed';

        // 第2引数が値の場合
        if (isset($call->args[1])) {
            $valueNode = $call->args[1]->value;

            // クロージャでない場合は値を解析
            if (! ($valueNode instanceof Node\Expr\Closure)) {
                $valueInfo = $this->analyzeValue($valueNode);
                $type = $valueInfo->type;
            }
        }

        $this->conditionalFields[] = $this->printer->prettyPrintExpr($call);

        return ResourceFieldInfo::conditional('when', $type);
    }

    /**
     * whenLoaded()メソッドを解析
     */
    private function analyzeWhenLoadedMethod(Node\Expr\MethodCall $call): ResourceFieldInfo
    {
        $relation = null;
        $type = 'mixed';

        if (isset($call->args[0])) {
            $relationArg = $call->args[0]->value;
            if ($relationArg instanceof Node\Scalar\String_) {
                $relation = $relationArg->value;

                // リレーション名から型を推論
                $type = str_ends_with($relationArg->value, 's') ? 'array' : 'object';
            }
        }

        // 第2引数（クロージャ）がある場合
        $hasTransformation = isset($call->args[1]);

        $this->conditionalFields[] = $this->printer->prettyPrintExpr($call);

        if ($relation !== null) {
            return ResourceFieldInfo::whenLoaded($relation, $type, $hasTransformation);
        }

        return ResourceFieldInfo::conditional('whenLoaded', $type);
    }

    /**
     * 静的メソッド呼び出しを解析（Resource::collection()など）
     */
    private function analyzeStaticCall(Node\Expr\StaticCall $call): ResourceFieldInfo
    {
        if ($call->class instanceof Node\Name) {
            $className = $call->class->toString();

            // ResourceクラスのCollection
            if (str_ends_with($className, 'Resource') &&
                $call->name->toString() === 'collection') {
                $items = ['type' => 'object', 'resource' => $className];

                // 引数がwhenLoaded()の場合は条件付きフィールドとして扱う
                if (isset($call->args[0]) &&
                    $call->args[0]->value instanceof Node\Expr\MethodCall &&
                    $call->args[0]->value->name->toString() === 'whenLoaded') {
                    $whenLoadedCall = $call->args[0]->value;
                    $relation = null;
                    $hasTransformation = isset($whenLoadedCall->args[1]);

                    if (isset($whenLoadedCall->args[0]) &&
                        $whenLoadedCall->args[0]->value instanceof Node\Scalar\String_) {
                        $relation = $whenLoadedCall->args[0]->value->value;
                    }

                    $this->conditionalFields[] = $this->printer->prettyPrintExpr($whenLoadedCall);
                    $this->nestedResources[] = $className;

                    if ($relation !== null) {
                        return ResourceFieldInfo::conditionalResourceCollection(
                            $className,
                            $relation,
                            $items,
                            $hasTransformation,
                        );
                    }
                }

                $this->nestedResources[] = $className;

                return ResourceFieldInfo::resourceCollection($className, $items);
            }
        }

        return ResourceFieldInfo::mixed();
    }

    /**
     * new Resource()を解析
     */
    private function analyzeNewResource(Node\Expr\New_ $new): ResourceFieldInfo
    {
        if ($new->class instanceof Node\Name) {
            $className = $new->class->toString();

            if (str_ends_with($className, 'Resource')) {
                // 引数がwhenLoaded()の場合は条件付きフィールドとして扱う
                if (isset($new->args[0]) &&
                    $new->args[0]->value instanceof Node\Expr\MethodCall &&
                    $new->args[0]->value->name->toString() === 'whenLoaded') {
                    $whenLoadedCall = $new->args[0]->value;
                    $relation = null;
                    $hasTransformation = isset($whenLoadedCall->args[1]);

                    if (isset($whenLoadedCall->args[0]) &&
                        $whenLoadedCall->args[0]->value instanceof Node\Scalar\String_) {
                        $relation = $whenLoadedCall->args[0]->value->value;
                    }

                    $this->conditionalFields[] = $this->printer->prettyPrintExpr($whenLoadedCall);
                    $this->nestedResources[] = $className;

                    if ($relation !== null) {
                        return ResourceFieldInfo::conditionalNestedResource(
                            $className,
                            $relation,
                            $hasTransformation,
                        );
                    }
                }

                $this->nestedResources[] = $className;

                return ResourceFieldInfo::nestedResource($className);
            }
        }

        return ResourceFieldInfo::object();
    }

    /**
     * Null-safe プロパティアクセスを解析 (例: $this->status?->value)
     */
    private function analyzeNullsafePropertyFetch(Node\Expr\NullsafePropertyFetch $expr): ResourceFieldInfo
    {
        // 動的プロパティ名（$this->obj?->$dynamicProperty）は静的解析不可
        if (! ($expr->name instanceof Node\Identifier)) {
            return ResourceFieldInfo::mixed()->withNullable();
        }

        $propertyName = $expr->name->toString();

        // $this->property?->value パターン (Enum)
        if ($propertyName === 'value') {
            // var が NullsafePropertyFetch または PropertyFetch で $this-> から始まる場合
            if ($this->isThisPropertyAccess($expr->var)) {
                return ResourceFieldInfo::enum(nullable: true);
            }
        }

        // $this->relation?->property パターン
        if ($this->isThisPropertyAccess($expr->var)) {
            return $this->analyzePropertyAccess($propertyName)->withNullable();
        }

        return ResourceFieldInfo::mixed()->withNullable();
    }

    /**
     * Null-safe メソッド呼び出しを解析 (例: $this->created_at?->toDateTimeString())
     */
    private function analyzeNullsafeMethodCall(Node\Expr\NullsafeMethodCall $call): ResourceFieldInfo
    {
        // 動的メソッド名（$this->obj?->$dynamicMethod()）は静的解析不可
        if (! ($call->name instanceof Node\Identifier)) {
            return ResourceFieldInfo::mixed()->withNullable();
        }

        $methodName = $call->name->toString();

        // 日付フォーマットメソッド
        if ($this->isDateFormattingMethod($methodName)) {
            return ResourceFieldInfo::dateTime(date('Y-m-d H:i:s'))->withNullable();
        }

        // has*, is* メソッド (boolean を返すメソッド)
        if (str_starts_with($methodName, 'has') || str_starts_with($methodName, 'is')) {
            return ResourceFieldInfo::boolean()->withNullable();
        }

        return ResourceFieldInfo::mixed()->withNullable();
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
    private function analyzeMethodChain(Node\Expr\MethodCall $call): ResourceFieldInfo
    {
        $methodName = $call->name->toString();

        // 日付フォーマット
        if ($this->isDateFormattingMethod($methodName)) {
            return ResourceFieldInfo::dateTime(date('Y-m-d H:i:s'));
        }

        // Enumのvalue
        if ($methodName === 'value' && $call->var instanceof Node\Expr\PropertyFetch) {
            return ResourceFieldInfo::enum();
        }

        // count() メソッド
        if ($methodName === 'count') {
            return ResourceFieldInfo::integer();
        }

        // pluck() メソッド
        if ($methodName === 'pluck') {
            return ResourceFieldInfo::array();
        }

        // has*, is* メソッド (boolean を返すメソッド)
        if (str_starts_with($methodName, 'has') || str_starts_with($methodName, 'is')) {
            return ResourceFieldInfo::boolean();
        }

        // $this->relation()->method() パターン
        if ($call->var instanceof Node\Expr\MethodCall &&
            $call->var->var instanceof Node\Expr\Variable &&
            $call->var->var->name === 'this') {
            // リレーションのメソッドチェーン
            if ($methodName === 'count') {
                return ResourceFieldInfo::integer();
            }
        }

        return ResourceFieldInfo::mixed();
    }

    /**
     * キャストを解析
     */
    private function analyzeCast(Node\Expr\Cast $cast): ResourceFieldInfo
    {
        if ($cast instanceof Node\Expr\Cast\Bool_) {
            return ResourceFieldInfo::boolean();
        }
        if ($cast instanceof Node\Expr\Cast\Int_) {
            return ResourceFieldInfo::integer();
        }
        if ($cast instanceof Node\Expr\Cast\Double) {
            return ResourceFieldInfo::number();
        }
        if ($cast instanceof Node\Expr\Cast\String_) {
            return ResourceFieldInfo::string();
        }
        if ($cast instanceof Node\Expr\Cast\Array_) {
            return ResourceFieldInfo::array();
        }
        if ($cast instanceof Node\Expr\Cast\Object_) {
            return ResourceFieldInfo::object();
        }

        return ResourceFieldInfo::mixed();
    }

    /**
     * 関数呼び出しを解析 (値として使用される場合)
     */
    private function analyzeFunctionCall(Node\Expr\FuncCall $call): ResourceFieldInfo
    {
        if ($call->name instanceof Node\Name) {
            $functionName = $call->name->toString();

            // 数値フォーマット関数
            if (in_array($functionName, ['number_format', 'round', 'floor', 'ceil'])) {
                return ResourceFieldInfo::number();
            }

            // 文字列関数
            if (in_array($functionName, ['strtoupper', 'strtolower', 'ucfirst', 'trim', 'str_replace', 'substr', 'strlen'])) {
                return ResourceFieldInfo::string();
            }

            // array_merge - returns object with merged flag via properties
            if ($functionName === 'array_merge') {
                return ResourceFieldInfo::object()->withProperties(['merged' => true]);
            }
        }

        return ResourceFieldInfo::mixed();
    }

    /**
     * トップレベルの関数呼び出しを解析 (return array_merge(...) など)
     *
     * @return array<string, ResourceFieldInfo>
     */
    private function analyzeTopLevelFunctionCall(Node\Expr\FuncCall $call): array
    {
        if ($call->name instanceof Node\Name && $call->name->toString() === 'array_merge') {
            // array_merge の各引数を解析してマージ
            $merged = [];
            foreach ($call->args as $arg) {
                if ($arg->value instanceof Node\Expr\Array_) {
                    $merged = array_merge($merged, $this->analyzeArrayStructure($arg->value));
                }
            }

            return $merged;
        }

        // その他の関数呼び出しは単一の結果として返す
        return ['_result' => $this->analyzeFunctionCall($call)];
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
     * Convert ResourceFieldInfo array to legacy array format.
     *
     * @param  array<string, ResourceFieldInfo>  $structure
     * @return array<string, array<string, mixed>>
     */
    private function convertToArrayFormat(array $structure): array
    {
        $result = [];
        foreach ($structure as $key => $fieldInfo) {
            $result[$key] = $fieldInfo->toArray();
        }

        return $result;
    }

    /**
     * 解析結果の構造を取得 (DTOとして)
     *
     * @return array{
     *     properties: array<string, ResourceFieldInfo>,
     *     conditionalFields: list<string>,
     *     nestedResources: list<string>
     * }
     */
    public function getStructureAsDto(): array
    {
        return [
            'properties' => $this->structure,
            'conditionalFields' => array_unique($this->conditionalFields),
            'nestedResources' => array_unique($this->nestedResources),
        ];
    }

    /**
     * 解析結果の構造を取得 (後方互換性のため配列として)
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
            'properties' => $this->convertToArrayFormat($this->structure),
            'conditionalFields' => array_unique($this->conditionalFields),
            'nestedResources' => array_unique($this->nestedResources),
        ];
    }
}
