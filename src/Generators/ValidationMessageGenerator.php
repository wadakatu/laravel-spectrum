<?php

namespace LaravelSpectrum\Generators;

use Illuminate\Support\Str;
use LaravelSpectrum\DTO\Collections\ValidationRuleCollection;
use LaravelSpectrum\Support\ValidationRules;

class ValidationMessageGenerator
{
    /**
     * バリデーションルールから可能なエラーメッセージを生成
     */
    public function generateMessages(array $rules, array $customMessages = []): array
    {
        $messages = [];

        foreach ($rules as $field => $fieldRules) {
            $messages[$field] = $this->generateFieldMessages($field, $fieldRules, $customMessages);
        }

        return $messages;
    }

    /**
     * フィールドごとのエラーメッセージを生成
     */
    protected function generateFieldMessages(string $field, string|array $rules, array $customMessages): array
    {
        $messages = [];

        // ルールを配列に正規化
        $ruleCollection = ValidationRuleCollection::from($rules);

        $fieldType = ValidationRules::inferFieldType($ruleCollection->all());
        $humanField = $this->humanizeFieldName($field);

        foreach ($ruleCollection as $rule) {
            // Rule::unique() のようなオブジェクトの場合はスキップ
            if (! is_string($rule)) {
                continue;
            }

            $ruleName = ValidationRules::extractRuleName($rule);
            $parameters = ValidationRules::extractRuleParameters($rule);

            // カスタムメッセージがある場合
            $customKey = $field.'.'.$ruleName;
            if (isset($customMessages[$customKey])) {
                $messages[] = $customMessages[$customKey];

                continue;
            }

            // デフォルトメッセージテンプレートを使用
            $template = ValidationRules::getMessageTemplate($ruleName, $fieldType);
            if ($template) {
                $message = $this->replacePlaceholders($template, $field, $humanField, $parameters);
                $messages[] = $message;
            }
        }

        return array_unique($messages);
    }

    /**
     * フィールド名を人間が読みやすい形式に変換
     */
    protected function humanizeFieldName(string $field): string
    {
        return Str::title(str_replace(['_', '.'], ' ', $field));
    }

    /**
     * メッセージテンプレートのプレースホルダーを置換
     */
    protected function replacePlaceholders(string $template, string $field, string $humanField, array $parameters): string
    {
        $replacements = [
            ':attribute' => $humanField,
            ':Attribute' => Str::ucfirst($humanField),
        ];

        // パラメータの置換
        if (! empty($parameters)) {
            $replacements[':min'] = $parameters[0] ?? '';
            $replacements[':max'] = $parameters[0] ?? '';
            $replacements[':size'] = $parameters[0] ?? '';
            $replacements[':value'] = $parameters[0] ?? '';
            $replacements[':other'] = $this->humanizeFieldName($parameters[0] ?? '');
            $replacements[':format'] = $parameters[0] ?? '';
            $replacements[':values'] = implode(', ', $parameters);
        }

        return strtr($template, $replacements);
    }

    /**
     * フィールドごとのサンプルメッセージを1つ生成（OpenAPIの例として使用）
     */
    public function generateSampleMessage(string $field, string|array $rules): string
    {
        $messages = $this->generateFieldMessages($field, $rules, []);

        // 最も一般的なエラー（required）を優先
        foreach ($messages as $message) {
            if (str_contains($message, 'required')) {
                return $message;
            }
        }

        // それ以外は最初のメッセージを返す
        return $messages[0] ?? "The {$field} field is invalid.";
    }
}
