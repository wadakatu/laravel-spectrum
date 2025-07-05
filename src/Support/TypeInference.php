<?php

namespace LaravelPrism\Support;

use Illuminate\Support\Str;

class TypeInference
{
    /**
     * バリデーションルールから型を推論
     */
    public function inferFromRules(array $rules): string
    {
        foreach ($rules as $rule) {
            if ($rule === 'integer' || $rule === 'int') {
                return 'integer';
            }
            if ($rule === 'numeric' || $rule === 'decimal') {
                return 'number';
            }
            if ($rule === 'boolean' || $rule === 'bool') {
                return 'boolean';
            }
            if ($rule === 'array') {
                return 'array';
            }
            if ($rule === 'date' || $rule === 'datetime') {
                return 'string'; // date-time format
            }
            if ($rule === 'email' || $rule === 'url' || $rule === 'uuid') {
                return 'string';
            }
            if ($rule === 'file' || $rule === 'image') {
                return 'string'; // binary format
            }
        }
        
        return 'string'; // デフォルト
    }
    
    /**
     * フィールド名とルールからサンプル値を生成
     */
    public function generateExample(string $field, array $rules): mixed
    {
        // 型別のサンプル
        foreach ($rules as $rule) {
            if ($rule === 'integer' || $rule === 'int') {
                return $this->generateIntegerExample($field, $rules);
            }
            if ($rule === 'numeric' || $rule === 'decimal') {
                return 19.99;
            }
            if ($rule === 'boolean' || $rule === 'bool') {
                return true;
            }
            if ($rule === 'array') {
                return [];
            }
            if ($rule === 'date') {
                return '2024-01-01';
            }
            if ($rule === 'datetime') {
                return '2024-01-01T00:00:00Z';
            }
            if ($rule === 'email') {
                return 'user@example.com';
            }
            if ($rule === 'url') {
                return 'https://example.com';
            }
            if ($rule === 'uuid') {
                return '550e8400-e29b-41d4-a716-446655440000';
            }
        }
        
        // フィールド名ベースのサンプル
        if (Str::contains($field, ['name'])) {
            return 'John Doe';
        }
        if (Str::contains($field, ['email'])) {
            return 'user@example.com';
        }
        if (Str::contains($field, ['phone'])) {
            return '+1234567890';
        }
        if (Str::contains($field, ['address'])) {
            return '123 Main Street';
        }
        if (Str::contains($field, ['password'])) {
            return 'password123';
        }
        if (Str::contains($field, ['age'])) {
            return 25;
        }
        if (Str::contains($field, ['price', 'amount', 'cost'])) {
            return 99.99;
        }
        
        return 'string';
    }
    
    /**
     * 整数型のサンプルを生成
     */
    private function generateIntegerExample(string $field, array $rules): int
    {
        $min = 1;
        $max = 100;
        
        foreach ($rules as $rule) {
            if (Str::startsWith($rule, 'min:')) {
                $min = (int) Str::after($rule, 'min:');
            }
            if (Str::startsWith($rule, 'max:')) {
                $max = (int) Str::after($rule, 'max:');
            }
        }
        
        // フィールド名に基づいた適切な値
        if (Str::contains($field, ['id'])) {
            return 1;
        }
        if (Str::contains($field, ['age'])) {
            return min(25, $max);
        }
        if (Str::contains($field, ['count', 'quantity'])) {
            return min(10, $max);
        }
        
        return min($min + 1, $max);
    }
}