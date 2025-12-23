<?php

namespace LaravelSpectrum\Analyzers\Support;

use Illuminate\Support\Str;

/**
 * Infers OpenAPI formats from Laravel validation rules.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 */
class FormatInferrer
{
    /**
     * Infer date format from validation rules.
     *
     * @param  string|array  $rules
     */
    public function inferDateFormat($rules): ?string
    {
        $rules = $this->normalizeRules($rules);

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            if ($rule === 'date') {
                return 'date';
            }
            if (Str::startsWith($rule, 'date_format:')) {
                $format = Str::after($rule, 'date_format:');
                // Check if format includes time components (H, i, s, G, u)
                if (preg_match('/[HisGu]/', $format)) {
                    return 'date-time';
                }

                return 'date';
            }
        }

        return null;
    }

    /**
     * Infer format from validation rules (email, url, uuid, etc.)
     *
     * @param  string|array  $rules
     */
    public function inferFormat($rules): ?string
    {
        $rules = $this->normalizeRules($rules);

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            // Date formats
            if ($rule === 'date') {
                return 'date';
            }
            if (Str::startsWith($rule, 'date_format:')) {
                $format = Str::after($rule, 'date_format:');
                // Check if format includes time components (H, i, s, G, u)
                if (preg_match('/[HisGu]/', $format)) {
                    return 'date-time';
                }

                return 'date';
            }

            // Other formats
            if ($rule === 'email') {
                return 'email';
            }
            if ($rule === 'url') {
                return 'uri';
            }
            if ($rule === 'uuid') {
                return 'uuid';
            }
            if ($rule === 'ip' || $rule === 'ipv4') {
                return 'ipv4';
            }
            if ($rule === 'ipv6') {
                return 'ipv6';
            }
        }

        return null;
    }

    /**
     * Normalize rules to array format.
     *
     * @param  string|array|null  $rules
     */
    private function normalizeRules($rules): array
    {
        if (is_string($rules)) {
            return $rules === '' ? [] : explode('|', $rules);
        }

        if (is_array($rules)) {
            return $rules;
        }

        return [];
    }
}
