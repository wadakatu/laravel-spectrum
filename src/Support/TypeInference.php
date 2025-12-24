<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use Illuminate\Support\Str;

/**
 * Type inference from Laravel validation rules.
 *
 * Provides type inference and example generation based on
 * validation rules and field names.
 */
class TypeInference
{
    protected ValidationRuleTypeMapper $ruleTypeMapper;

    public function __construct(?ValidationRuleTypeMapper $ruleTypeMapper = null)
    {
        $this->ruleTypeMapper = $ruleTypeMapper ?? new ValidationRuleTypeMapper;
    }

    /**
     * Infer type from validation rules.
     *
     * @param  array<mixed>  $rules  Laravel validation rules
     * @return string OpenAPI type (string, integer, number, boolean, array, object)
     */
    public function inferFromRules(array $rules): string
    {
        return $this->ruleTypeMapper->inferType($rules);
    }

    /**
     * Generate an example value based on field name and validation rules.
     *
     * @param  array<mixed>  $rules
     */
    public function generateExample(string $field, array $rules): mixed
    {
        // Type-based examples
        foreach ($rules as $rule) {
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

        // Field name-based examples
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
     * Generate an integer example based on field name and constraints.
     *
     * @param  array<mixed>  $rules
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

        // Field name-based appropriate values
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
     * Generate a date example based on format string.
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

        if (isset($commonFormats[$format])) {
            return $commonFormats[$format];
        }

        try {
            return $now->format($format);
        } catch (\Exception) {
            return '2024-01-01';
        }
    }
}
