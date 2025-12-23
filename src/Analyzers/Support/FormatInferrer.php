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
            if ($rule === 'datetime') {
                return 'date-time';
            }
            if (Str::startsWith($rule, 'date_format:')) {
                // For specific date formats, we still return 'date' as the OpenAPI format
                // The actual format pattern is captured in the validation rules
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
            if ($rule === 'datetime') {
                return 'date-time';
            }
            if (Str::startsWith($rule, 'date_format:')) {
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
     * @param  string|array  $rules
     */
    private function normalizeRules($rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        return $rules;
    }
}
