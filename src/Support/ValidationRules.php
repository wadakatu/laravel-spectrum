<?php

namespace LaravelSpectrum\Support;

class ValidationRules
{
    /**
     * バリデーションルールからエラーメッセージのテンプレートを取得
     */
    protected static array $messageTemplates = [
        'required' => 'The :attribute field is required.',
        'required_if' => 'The :attribute field is required when :other is :value.',
        'required_unless' => 'The :attribute field is required unless :other is in :values.',
        'required_with' => 'The :attribute field is required when :values is present.',
        'required_without' => 'The :attribute field is required when :values is not present.',
        'email' => 'The :attribute must be a valid email address.',
        'unique' => 'The :attribute has already been taken.',
        'exists' => 'The selected :attribute is invalid.',
        'string' => 'The :attribute must be a string.',
        'integer' => 'The :attribute must be an integer.',
        'numeric' => 'The :attribute must be a number.',
        'boolean' => 'The :attribute field must be true or false.',
        'array' => 'The :attribute must be an array.',
        'date' => 'The :attribute is not a valid date.',
        'date_format' => 'The :attribute does not match the format :format.',
        'min' => [
            'numeric' => 'The :attribute must be at least :min.',
            'string' => 'The :attribute must be at least :min characters.',
            'array' => 'The :attribute must have at least :min items.',
        ],
        'max' => [
            'numeric' => 'The :attribute may not be greater than :max.',
            'string' => 'The :attribute may not be greater than :max characters.',
            'array' => 'The :attribute may not have more than :max items.',
        ],
        'between' => [
            'numeric' => 'The :attribute must be between :min and :max.',
            'string' => 'The :attribute must be between :min and :max characters.',
            'array' => 'The :attribute must have between :min and :max items.',
        ],
        'in' => 'The selected :attribute is invalid.',
        'not_in' => 'The selected :attribute is invalid.',
        'regex' => 'The :attribute format is invalid.',
        'confirmed' => 'The :attribute confirmation does not match.',
        'same' => 'The :attribute and :other must match.',
        'different' => 'The :attribute and :other must be different.',
        'size' => [
            'numeric' => 'The :attribute must be :size.',
            'string' => 'The :attribute must be :size characters.',
            'array' => 'The :attribute must contain :size items.',
        ],
        'url' => 'The :attribute format is invalid.',
        'ip' => 'The :attribute must be a valid IP address.',
        'json' => 'The :attribute must be a valid JSON string.',
        'alpha' => 'The :attribute may only contain letters.',
        'alpha_num' => 'The :attribute may only contain letters and numbers.',
        'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
    ];

    /**
     * ルール名を抽出（パラメータを除去）
     */
    public static function extractRuleName(string|array|object $rule): string
    {
        // Handle object rules (like Rule::enum() or new Enum())
        if (is_object($rule)) {
            // Handle Laravel's Rule::enum() and new Enum() instances
            if (method_exists($rule, '__toString')) {
                return 'enum';
            }
            return 'unknown';
        }
        
        // Handle array rules (like ['required', 'string'])
        if (is_array($rule)) {
            // Handle empty arrays
            if (empty($rule)) {
                return 'unknown';
            }
            // Get the first element if it's a string
            return is_string($rule[0] ?? null) ? $rule[0] : 'unknown';
        }
        
        $parts = explode(':', $rule);

        return $parts[0];
    }

    /**
     * ルールのパラメータを抽出
     */
    public static function extractRuleParameters(string $rule): array
    {
        if (! str_contains($rule, ':')) {
            return [];
        }

        $parts = explode(':', $rule, 2);

        return explode(',', $parts[1]);
    }

    /**
     * ルールに対応するメッセージテンプレートを取得
     */
    public static function getMessageTemplate(string $ruleName, string $fieldType = 'string'): ?string
    {
        if (! isset(self::$messageTemplates[$ruleName])) {
            return null;
        }

        $template = self::$messageTemplates[$ruleName];

        // 型によって異なるメッセージがある場合
        if (is_array($template)) {
            return $template[$fieldType] ?? $template['string'] ?? null;
        }

        return $template;
    }

    /**
     * フィールドタイプを推測
     */
    public static function inferFieldType(array $rules): string
    {
        foreach ($rules as $rule) {
            $ruleName = self::extractRuleName($rule);

            if (in_array($ruleName, ['integer', 'numeric'])) {
                return 'numeric';
            }
            if ($ruleName === 'array') {
                return 'array';
            }
            if ($ruleName === 'enum') {
                return 'string';  // Enums are typically strings
            }
        }

        return 'string';
    }
}
