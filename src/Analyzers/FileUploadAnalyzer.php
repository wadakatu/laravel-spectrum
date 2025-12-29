<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use Illuminate\Validation\Rules\File;
use LaravelSpectrum\DTO\FileDimensions;
use LaravelSpectrum\DTO\FileUploadInfo;

class FileUploadAnalyzer
{
    private const FILE_RULES = ['file', 'image', 'mimes', 'mimetypes'];

    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/svg+xml',
        'image/webp',
    ];

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
     * Analyze validation rules and return FileUploadInfo DTOs.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, FileUploadInfo>
     */
    public function analyzeRulesToResult(array $rules): array
    {
        $fileFields = [];

        foreach ($rules as $field => $fieldRules) {
            $info = $this->analyzeFieldRulesToDto($field, $fieldRules);

            if ($info !== null) {
                $fileFields[$field] = $info;
            }
        }

        return $fileFields;
    }

    /**
     * Analyze validation rules and return arrays (backward compatible).
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, array<string, mixed>>
     */
    public function analyzeRules(array $rules): array
    {
        $result = [];

        foreach ($this->analyzeRulesToResult($rules) as $field => $info) {
            $result[$field] = $info->toArray();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $fileRules
     * @return array<string>
     */
    public function inferMimeTypes(array $fileRules): array
    {
        if (isset($fileRules['mime_types']) && ! empty($fileRules['mime_types'])) {
            return $fileRules['mime_types'];
        }

        if (isset($fileRules['is_image']) && $fileRules['is_image']) {
            return self::IMAGE_MIME_TYPES;
        }

        if (isset($fileRules['mimes']) && ! empty($fileRules['mimes'])) {
            return array_map(
                fn ($ext) => self::MIME_TYPE_MAPPING[$ext] ?? 'application/octet-stream',
                $fileRules['mimes']
            );
        }

        return [];
    }

    public function isMultipleFiles(string $field): bool
    {
        return str_contains($field, '.*');
    }

    /**
     * Analyze field rules and return FileUploadInfo DTO.
     */
    private function analyzeFieldRulesToDto(string $field, mixed $fieldRules): ?FileUploadInfo
    {
        $rulesArray = $this->normalizeRules($fieldRules);

        if (! $this->hasFileRule($rulesArray)) {
            return null;
        }

        $analysis = [
            'is_image' => false,
            'mimes' => [],
            'mime_types' => [],
            'max_size' => null,
            'min_size' => null,
            'dimensions' => [],
            'multiple' => $this->isMultipleFiles($field),
        ];

        foreach ($rulesArray as $rule) {
            $this->processRule($rule, $analysis);
        }

        if (! empty($analysis['mimes'])) {
            $analysis['mime_types'] = $this->inferMimeTypes($analysis);
        } elseif ($analysis['is_image'] && empty($analysis['mime_types'])) {
            $analysis['mime_types'] = self::IMAGE_MIME_TYPES;
        }

        // Convert dimensions array to FileDimensions DTO
        $dimensions = null;
        if (! empty($analysis['dimensions'])) {
            $dimensions = FileDimensions::fromArray($analysis['dimensions']);
        }

        return new FileUploadInfo(
            isImage: $analysis['is_image'],
            mimes: $analysis['mimes'],
            mimeTypes: $analysis['mime_types'],
            maxSize: $analysis['max_size'],
            minSize: $analysis['min_size'],
            dimensions: $dimensions,
            multiple: $analysis['multiple'],
        );
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
     */
    private function hasFileRule(array $rules): bool
    {
        foreach ($rules as $rule) {
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
     * @param  array<string, mixed>  $analysis
     */
    private function processRule(mixed $rule, array &$analysis): void
    {
        if (is_string($rule)) {
            $this->processStringRule($rule, $analysis);
        } elseif ($rule instanceof File) {
            $this->processFileRuleObject($rule, $analysis);
        }
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function processStringRule(string $rule, array &$analysis): void
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = $parts[1] ?? '';

        switch ($ruleName) {
            case 'file':
                break;
            case 'image':
                $analysis['is_image'] = true;
                break;
            case 'mimes':
                $analysis['mimes'] = explode(',', $parameters);
                break;
            case 'mimetypes':
                $analysis['mime_types'] = explode(',', $parameters);
                break;
            case 'max':
                $analysis['max_size'] = (int) $parameters * 1024; // KB to bytes
                break;
            case 'min':
                $analysis['min_size'] = (int) $parameters * 1024; // KB to bytes
                break;
            case 'dimensions':
                $analysis['dimensions'] = $this->parseDimensions($parameters);
                break;
        }
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function processFileRuleObject(File $rule, array &$analysis): void
    {
        $reflection = new \ReflectionClass($rule);

        try {
            $property = $reflection->getProperty('allowedMimetypes');
            $property->setAccessible(true);
            $mimetypes = $property->getValue($rule);
            if (! empty($mimetypes)) {
                // If these look like file extensions rather than MIME types
                if (isset($mimetypes[0]) && ! str_contains($mimetypes[0], '/')) {
                    $analysis['mimes'] = $mimetypes;
                } else {
                    $analysis['mime_types'] = $mimetypes;
                }
            }
        } catch (\ReflectionException $e) {
            // Property not found
        }

        try {
            $property = $reflection->getProperty('allowedExtensions');
            $property->setAccessible(true);
            $extensions = $property->getValue($rule);
            if (! empty($extensions)) {
                $analysis['mimes'] = $extensions;
            }
        } catch (\ReflectionException $e) {
            // Property not found
        }

        try {
            $property = $reflection->getProperty('minimumFileSize');
            $property->setAccessible(true);
            $minSize = $property->getValue($rule);
            if ($minSize !== null) {
                $analysis['min_size'] = $minSize * 1024; // KB to bytes
            }
        } catch (\ReflectionException $e) {
            // Property not found
        }

        try {
            $property = $reflection->getProperty('maximumFileSize');
            $property->setAccessible(true);
            $maxSize = $property->getValue($rule);
            if ($maxSize !== null) {
                $analysis['max_size'] = $maxSize * 1024; // KB to bytes
            }
        } catch (\ReflectionException $e) {
            // Property not found
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDimensions(string $parameters): array
    {
        $dimensions = [];
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

        return $dimensions;
    }
}
