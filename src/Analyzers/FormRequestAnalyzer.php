<?php

namespace LaravelPrism\Analyzers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use LaravelPrism\Support\TypeInference;
use ReflectionClass;

class FormRequestAnalyzer
{
    protected TypeInference $typeInference;

    public function __construct(TypeInference $typeInference)
    {
        $this->typeInference = $typeInference;
    }

    /**
     * FormRequestクラスを解析してパラメータ情報を抽出
     */
    public function analyze(string $requestClass): array
    {
        if (! class_exists($requestClass)) {
            return [];
        }

        $reflection = new ReflectionClass($requestClass);

        // FormRequestを継承していない場合はスキップ
        if (! $reflection->isSubclassOf(FormRequest::class)) {
            return [];
        }

        $instance = $reflection->newInstanceWithoutConstructor();

        // rules()メソッドからルールを取得
        $rules = $this->extractRules($instance, $reflection);

        // attributes()メソッドから説明を取得
        $attributes = $this->extractAttributes($instance, $reflection);

        $parameters = [];

        foreach ($rules as $field => $rule) {
            $ruleArray = is_array($rule) ? $rule : explode('|', $rule);

            $parameters[] = [
                'name'        => $field,
                'in'          => 'body',
                'required'    => $this->isRequired($ruleArray),
                'type'        => $this->typeInference->inferFromRules($ruleArray),
                'description' => $attributes[$field] ?? $this->generateDescription($field, $ruleArray),
                'example'     => $this->typeInference->generateExample($field, $ruleArray),
                'validation'  => $ruleArray,
            ];
        }

        return $parameters;
    }

    /**
     * rules()メソッドからルールを抽出
     */
    protected function extractRules($instance, ReflectionClass $reflection): array
    {
        if (! $reflection->hasMethod('rules')) {
            return [];
        }

        $method = $reflection->getMethod('rules');
        $method->setAccessible(true);

        try {
            return $method->invoke($instance) ?: [];
        } catch (\Exception $e) {
            // 依存関係でエラーが出る場合は空配列を返す
            return [];
        }
    }

    /**
     * attributes()メソッドから属性の説明を抽出
     */
    protected function extractAttributes($instance, ReflectionClass $reflection): array
    {
        if (! $reflection->hasMethod('attributes')) {
            return [];
        }

        $method = $reflection->getMethod('attributes');
        $method->setAccessible(true);

        try {
            return $method->invoke($instance) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 必須フィールドかどうかを判定
     */
    protected function isRequired(array $rules): bool
    {
        return in_array('required', $rules, true) ||
               in_array('required_if', $rules, true) ||
               in_array('required_unless', $rules, true);
    }

    /**
     * フィールドの説明を生成
     */
    protected function generateDescription(string $field, array $rules): string
    {
        $description = Str::title(str_replace(['_', '-'], ' ', $field));

        // 特定のルールから追加情報を生成
        foreach ($rules as $rule) {
            if (Str::startsWith($rule, 'max:')) {
                $max = Str::after($rule, 'max:');
                $description .= " (最大{$max}文字)";
            } elseif (Str::startsWith($rule, 'min:')) {
                $min = Str::after($rule, 'min:');
                $description .= " (最小{$min}文字)";
            }
        }

        return $description;
    }
}
