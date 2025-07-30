<?php

namespace LaravelSpectrum\Support;

use Illuminate\Support\Str;

class TypeInference
{
    /**
     * バリデーションルールから型を推論
     */
    public function inferFromRules(array $rules): string
    {
        foreach ($rules as $rule) {
            // Skip non-string rules (e.g., Rule::enum() objects)
            if (! is_string($rule)) {
                continue;
            }

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
            if ($rule === 'date' || $rule === 'datetime' || Str::startsWith($rule, 'date_format:') ||
                Str::startsWith($rule, 'after:') || Str::startsWith($rule, 'before:') ||
                Str::startsWith($rule, 'after_or_equal:') || Str::startsWith($rule, 'before_or_equal:') ||
                Str::startsWith($rule, 'date_equals:')) {
                return 'string'; // date-time format
            }
            if ($rule === 'email' || $rule === 'url' || $rule === 'uuid') {
                return 'string';
            }
            if ($rule === 'file' || $rule === 'image') {
                return 'string'; // binary format
            }
            if ($rule === 'timezone' || Str::startsWith($rule, 'timezone:')) {
                return 'string';
            }
            if ($rule === 'json') {
                return 'object';
            }
            if ($rule === 'ip' || $rule === 'ipv4' || $rule === 'ipv6' || $rule === 'mac_address') {
                return 'string';
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
            // Skip non-string rules
            if (! is_string($rule)) {
                continue;
            }

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
            if (Str::startsWith($rule, 'date_format:')) {
                return $this->generateDateFormatExample(Str::after($rule, 'date_format:'));
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
            if ($rule === 'timezone' || Str::startsWith($rule, 'timezone:')) {
                return 'Asia/Tokyo';
            }
            if ($rule === 'ip' || $rule === 'ipv4') {
                return '192.168.1.1';
            }
            if ($rule === 'ipv6') {
                return '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
            }
            if ($rule === 'mac_address') {
                return '00:11:22:33:44:55';
            }
            if ($rule === 'json') {
                return ['key' => 'value'];
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
        if (Str::contains($field, ['date', 'time'])) {
            return '2024-01-01';
        }
        if (Str::contains($field, ['timezone'])) {
            return 'UTC';
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
            if (is_string($rule)) {
                if (Str::startsWith($rule, 'min:')) {
                    $min = (int) Str::after($rule, 'min:');
                }
                if (Str::startsWith($rule, 'max:')) {
                    $max = (int) Str::after($rule, 'max:');
                }
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

    /**
     * 日付フォーマットに基づいたサンプルを生成
     */
    private function generateDateFormatExample(string $format): string
    {
        $now = new \DateTime('2024-01-01 14:30:00');

        // Handle escaped characters in format
        $format = stripslashes($format);

        // Common date formats
        $commonFormats = [
            'Y-m-d' => '2024-01-01',
            'Y-m-d H:i:s' => '2024-01-01 14:30:00',
            'd/m/Y' => '01/01/2024',
            'm/d/Y' => '01/01/2024',
            'Y-m-d\TH:i:sP' => '2024-01-01T14:30:00+00:00',
            'Y-m-d\TH:i:s\Z' => '2024-01-01T14:30:00Z',
            'c' => '2024-01-01T14:30:00+00:00',
            'U' => '1704116400',
            'H:i:s' => '14:30:00',
            'H:i' => '14:30',
            'F Y' => 'January 2024',
            'd/m/Y g:i A' => '01/01/2024 2:30 PM',
        ];

        // Check if it's a common format
        if (isset($commonFormats[$format])) {
            return $commonFormats[$format];
        }

        // Try to format using DateTime
        try {
            return $now->format($format);
        } catch (\Exception $e) {
            // Fallback for invalid formats
            return '2024-01-01';
        }
    }
}
