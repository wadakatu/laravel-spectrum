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

        // その他の複雑な式は文字列として保存
        if ($expr instanceof Node\Expr) {
            return $this->printer->prettyPrintExpr($expr);
        }

        return '';
    }

    /**
     * 静的メソッド呼び出しを評価
     */
    private function evaluateStaticCall(Node\Expr\StaticCall $call): string
    {
        // Rule::unique('users')->ignore($id) のような呼び出しを文字列化
        // TODO: より詳細な解析が可能
        return $this->printer->prettyPrintExpr($call);
    }

    public function getRules(): array
    {
        return $this->rules;
    }
}
