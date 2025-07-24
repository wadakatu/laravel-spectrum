<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

class RulesExtractorVisitor extends NodeVisitorAbstract
{
    private array $rules = [];

    private PrettyPrinter\Standard $printer;

    private array $variables = [];

    public function __construct(PrettyPrinter\Standard $printer)
    {
        $this->printer = $printer;
    }

    public function enterNode(Node $node): ?int
    {
        // 変数代入を追跡
        if ($node instanceof Node\Expr\Assign &&
            $node->var instanceof Node\Expr\Variable &&
            $node->expr instanceof Node\Expr\Array_) {
            $this->variables[$node->var->name] = $this->extractArrayRules($node->expr);
        }

        // return文を検出
        if ($node instanceof Node\Stmt\Return_ && $node->expr) {

            // 直接配列を返す場合
            if ($node->expr instanceof Node\Expr\Array_) {
                $this->rules = $this->extractArrayRules($node->expr);
            }
            // 変数を返す場合
            elseif ($node->expr instanceof Node\Expr\Variable) {
                // TODO: 変数の定義を追跡する必要がある
                $this->rules = ['_notice' => 'Dynamic rules detected - manual review required'];
            }
            // array_merge呼び出しを処理
            elseif ($node->expr instanceof Node\Expr\FuncCall &&
                    $node->expr->name instanceof Node\Name &&
                    $node->expr->name->toString() === 'array_merge') {
                $this->rules = $this->extractArrayMergeRules($node->expr);
            }
            // その他のメソッド呼び出しの結果を返す場合
            elseif ($node->expr instanceof Node\Expr\MethodCall ||
                    $node->expr instanceof Node\Expr\FuncCall) {
                $this->rules = ['_notice' => 'Complex rules detected - '.$this->printer->prettyPrintExpr($node->expr)];
            }
            // match式（PHP 8）
            elseif ($node->expr instanceof Node\Expr\Match_) {
                $this->rules = $this->extractMatchRules($node->expr);
            }
        }

        return null;
    }

    /**
     * 配列形式のルールを抽出
     */
    private function extractArrayRules(Node\Expr\Array_ $array): array
    {
        $rules = [];

        foreach ($array->items as $item) {
            $key = $this->evaluateKey($item->key);
            $value = $this->evaluateValue($item->value);

            if ($value !== null) {
                $rules[$key] = $value;
            }
        }

        return $rules;
    }

    /**
     * match式からルールを抽出（PHP 8）
     */
    private function extractMatchRules(Node\Expr\Match_ $match): array
    {
        // 最初のアームのルールを取得（簡易実装）
        foreach ($match->arms as $arm) {
            if ($arm->body instanceof Node\Expr\Array_) {
                return $this->extractArrayRules($arm->body);
            }
        }

        return ['_notice' => 'Match expression detected - manual review required'];
    }

    /**
     * array_merge呼び出しからルールを抽出
     */
    private function extractArrayMergeRules(Node\Expr\FuncCall $call): array
    {
        $mergedRules = [];

        foreach ($call->args as $arg) {
            if ($arg->value instanceof Node\Expr\Array_) {
                $rules = $this->extractArrayRules($arg->value);
                $mergedRules = array_merge($mergedRules, $rules);
            } elseif ($arg->value instanceof Node\Expr\Variable) {
                // 変数の場合は保存された値を使用
                $varName = $arg->value->name;
                if (isset($this->variables[$varName])) {
                    $mergedRules = array_merge($mergedRules, $this->variables[$varName]);
                }
            }
        }

        return $mergedRules ?: ['_notice' => 'Dynamic array_merge rules detected'];
    }

    /**
     * キーを評価
     */
    private function evaluateKey(Node $expr): string
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Scalar\LNumber) {
            return (string) $expr->value;
        }

        // その他の式は文字列として保存
        if ($expr instanceof Node\Expr) {
            return $this->printer->prettyPrintExpr($expr);
        }

        return '';
    }

    /**
     * 値を評価
     */
    private function evaluateValue(Node $expr)
    {
        // 文字列リテラル
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        // 配列
        if ($expr instanceof Node\Expr\Array_) {
            $result = [];
            foreach ($expr->items as $item) {
                $value = $this->evaluateValue($item->value);
                if ($value !== null) {
                    $result[] = $value;
                }
            }

            return $result;
        }

        // 連結演算子（'required|string' のような形式）
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            $left = $this->evaluateValue($expr->left);
            $right = $this->evaluateValue($expr->right);

            if (is_string($left) && is_string($right)) {
                return $left.$right;
            }
        }

        // Ruleクラスの静的メソッド呼び出し
        if ($expr instanceof Node\Expr\StaticCall) {
            return $this->evaluateStaticCall($expr);
        }

        // new演算子による Enumルールのインスタンス生成
        if ($expr instanceof Node\Expr\New_) {
            return $this->evaluateNewExpression($expr);
        }

        // その他の複雑な式は文字列として保存
        if ($expr instanceof Node\Expr) {
            return $this->printer->prettyPrintExpr($expr);
        }

        return '';
    }

    /**
     * 静的メソッド呼び出しを評価
     */
    private function evaluateStaticCall(Node\Expr\StaticCall $call)
    {
        // Ruleクラスのメソッドを解析
        if ($call->class instanceof Node\Name) {
            $className = $call->class->toString();
            if ($className === 'Rule' || $className === 'Illuminate\\Validation\\Rule') {
                $methodName = $call->name->toString();

                switch ($methodName) {
                    case 'in':
                        return $this->evaluateInRule($call);
                    case 'unique':
                        return $this->evaluateUniqueRule($call);
                    case 'exists':
                        return $this->evaluateExistsRule($call);
                    case 'requiredIf':
                        return $this->evaluateRequiredIfRule($call);
                    case 'enum':
                        return $this->evaluateEnumRule($call);
                    default:
                        // その他のRuleメソッドは簡略化された形式で返す
                        return $methodName;
                }
            }
        }

        // その他の静的呼び出しは文字列化
        return $this->printer->prettyPrintExpr($call);
    }

    /**
     * Rule::in() を評価
     */
    private function evaluateInRule(Node\Expr\StaticCall $call): string
    {
        if (! isset($call->args[0])) {
            return 'in:';
        }

        $values = [];
        $arg = $call->args[0]->value;

        if ($arg instanceof Node\Expr\Array_) {
            foreach ($arg->items as $item) {
                if ($item && $item->value instanceof Node\Scalar\String_) {
                    $values[] = $item->value->value;
                } elseif ($item && $item->value instanceof Node\Scalar\LNumber) {
                    $values[] = (string) $item->value->value;
                }
            }
        }

        return 'in:'.implode(',', $values);
    }

    /**
     * Rule::unique() を評価
     */
    private function evaluateUniqueRule(Node\Expr\StaticCall $call): string
    {
        $parts = ['unique'];

        // テーブル名
        if (isset($call->args[0]) && $call->args[0]->value instanceof Node\Scalar\String_) {
            $parts[] = $call->args[0]->value->value;
        }

        // カラム名（オプション）
        if (isset($call->args[1]) && $call->args[1]->value instanceof Node\Scalar\String_) {
            $parts[] = $call->args[1]->value->value;
        }

        return implode(':', $parts);
    }

    /**
     * Rule::exists() を評価
     */
    private function evaluateExistsRule(Node\Expr\StaticCall $call): string
    {
        $parts = ['exists'];

        // テーブル名
        if (isset($call->args[0]) && $call->args[0]->value instanceof Node\Scalar\String_) {
            $parts[] = $call->args[0]->value->value;
        }

        // カラム名（オプション）
        if (isset($call->args[1]) && $call->args[1]->value instanceof Node\Scalar\String_) {
            $parts[] = $call->args[1]->value->value;
        }

        return implode(':', $parts);
    }

    /**
     * Rule::requiredIf() を評価
     */
    private function evaluateRequiredIfRule(Node\Expr\StaticCall $call): string
    {
        $parts = ['required_if'];

        // フィールド名
        if (isset($call->args[0]) && $call->args[0]->value instanceof Node\Scalar\String_) {
            $parts[] = $call->args[0]->value->value;
        }

        // 値
        if (isset($call->args[1])) {
            $value = $call->args[1]->value;
            if ($value instanceof Node\Scalar\String_) {
                $parts[] = $value->value;
            } elseif ($value instanceof Node\Scalar\LNumber) {
                $parts[] = (string) $value->value;
            }
        }

        return implode(':', $parts);
    }

    /**
     * Rule::enum() を評価
     */
    private function evaluateEnumRule(Node\Expr\StaticCall $call)
    {
        // Rule::enum(StatusEnum::class) のような呼び出し
        if (isset($call->args[0]) && $call->args[0]->value instanceof Node\Expr\ClassConstFetch) {
            $classConstFetch = $call->args[0]->value;
            if ($classConstFetch->class instanceof Node\Name && $classConstFetch->name instanceof Node\Identifier && $classConstFetch->name->name === 'class') {
                $enumClassName = $classConstFetch->class->toString();

                return [
                    'type' => 'enum',
                    'class' => $enumClassName,
                ];
            }
        }

        return '__enum__';
    }

    /**
     * new式を評価
     */
    private function evaluateNewExpression(Node\Expr\New_ $new)
    {
        // new Enum(StatusEnum::class) のような呼び出し
        if ($new->class instanceof Node\Name) {
            $className = $new->class->toString();
            if ($className === 'Enum' || $className === 'Illuminate\\Validation\\Rules\\Enum') {
                if (isset($new->args[0]) && $new->args[0]->value instanceof Node\Expr\ClassConstFetch) {
                    $classConstFetch = $new->args[0]->value;
                    if ($classConstFetch->class instanceof Node\Name && $classConstFetch->name instanceof Node\Identifier && $classConstFetch->name->name === 'class') {
                        $enumClassName = $classConstFetch->class->toString();

                        return [
                            'type' => 'enum',
                            'class' => $enumClassName,
                        ];
                    }
                }

                return '__enum__';
            }
        }

        // その他のnew式は文字列化
        return $this->printer->prettyPrintExpr($new);
    }

    public function getRules(): array
    {
        return $this->rules;
    }
}
