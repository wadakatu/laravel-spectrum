<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use Illuminate\Validation\Rules\File;

class FileUploadDetector
{
    private const FILE_RULES = ['file', 'image', 'mimes', 'mimetypes'];

    private const MIME_TYPE_MAPPING = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'json' => 'application/json',
        'xml' => 'application/xml',
    ];

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, array<mixed>>
     */
    public function extractFileRules(array $rules): array
    {
        $fileRules = [];

        foreach ($rules as $field => $fieldRules) {
            if ($this->hasFileRule($fieldRules)) {
                $fileRules[$field] = $this->normalizeRules($fieldRules);
            }

            // Check for nested array patterns (e.g., variants.*.image)
            if (preg_match('/^(.+)\.\*\.(.+)$/', $field, $matches)) {
                if ($this->hasFileRule($fieldRules)) {
                    $fileRules[$field] = $this->normalizeRules($fieldRules);
                }
            }
        }

        return $fileRules;
    }

    /**
     * @return array<string, string>
     */
    public function getMimeTypeMapping(): array
    {
        return self::MIME_TYPE_MAPPING;
    }

    /**
     * @param  array<mixed>  $rules
     * @return array<string, int>
     */
    public function extractSizeConstraints(array $rules): array
    {
        $constraints = [];
        $normalizedRules = $this->flattenRules($rules);

        foreach ($normalizedRules as $rule) {
            if (is_string($rule)) {
                $parts = explode(':', $rule, 2);
                $ruleName = $parts[0];
                $value = $parts[1] ?? '';

                if ($ruleName === 'min' && $value !== '') {
                    $constraints['min'] = (int) $value * 1024; // KB to bytes
                } elseif ($ruleName === 'max' && $value !== '') {
                    $constraints['max'] = (int) $value * 1024; // KB to bytes
                }
            }
        }

        return $constraints;
    }

    /**
     * @param  array<mixed>  $rules
     * @return array<string, mixed>
     */
    public function extractDimensionConstraints(array $rules): array
    {
        $dimensions = [];
        $normalizedRules = $this->flattenRules($rules);

        foreach ($normalizedRules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'dimensions:')) {
                $parameters = substr($rule, 11); // Remove 'dimensions:'
                $pairs = explode(',', $parameters);

                foreach ($pairs as $pair) {
                    $parts = explode('=', $pair, 2);
                    if (count($parts) === 2) {
                        $key = trim($parts[0]);
                        $value = trim($parts[1]);

                        if ($key === 'ratio') {
                            $dimensions[$key] = $value;
                        } else {
                            $dimensions[$key] = (int) $value;
                        }
                    }
                }
            }
        }

        return $dimensions;
    }

    private function hasFileRule(mixed $rules): bool
    {
        $normalizedRules = $this->normalizeRules($rules);

        foreach ($normalizedRules as $rule) {
            if ($this->isFileRule($rule)) {
                return true;
            }
        }

        return false;
    }

    private function isFileRule(mixed $rule): bool
    {
        if (is_string($rule)) {
            $ruleName = explode(':', $rule)[0];

            return in_array($ruleName, self::FILE_RULES, true);
        }

        if ($rule instanceof File) {
            return true;
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    private function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        if (is_array($rules)) {
            return $rules;
        }

        return [];
    }

    /**
     * @param  array<mixed>  $rules
     * @return array<mixed>
     */
    private function flattenRules(array $rules): array
    {
        $flattened = [];

        foreach ($rules as $rule) {
            if (is_string($rule) && str_contains($rule, '|')) {
                $flattened = array_merge($flattened, explode('|', $rule));
            } else {
                $flattened[] = $rule;
            }
        }

        return $flattened;
    }

    /**
     * Detect complex file upload patterns
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, array<string>>
     */
    public function detectFilePatterns(array $rules): array
    {
        $patterns = [
            'single_files' => [],
            'array_files' => [],
            'nested_files' => [],
        ];

        foreach ($rules as $field => $fieldRules) {
            if (! $this->hasFileRule($fieldRules)) {
                continue;
            }

            if (str_contains($field, '.*')) {
                if (substr_count($field, '.*') > 1) {
                    $patterns['nested_files'][] = $field;
                } else {
                    $patterns['array_files'][] = $field;
                }
            } else {
                $patterns['single_files'][] = $field;
            }
        }

        return $patterns;
    }
}
