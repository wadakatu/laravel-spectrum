<?php

namespace LaravelSpectrum\Analyzers\Support;

use Illuminate\Support\Str;
use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Support\FileSizeFormatter;

/**
 * Generates human-readable descriptions for validation fields.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 */
class ValidationDescriptionGenerator
{
    protected EnumAnalyzer $enumAnalyzer;

    public function __construct(?EnumAnalyzer $enumAnalyzer = null)
    {
        $this->enumAnalyzer = $enumAnalyzer ?? new EnumAnalyzer;
    }

    /**
     * Generate description for a field based on its validation rules.
     *
     * @param  string  $field  The field name
     * @param  array  $rules  The validation rules
     * @param  string|null  $namespace  The namespace for enum resolution
     * @param  array  $useStatements  Use statements for enum resolution
     */
    public function generateDescription(string $field, array $rules, ?string $namespace = null, array $useStatements = []): string
    {
        $description = $this->formatFieldName($field);

        $conditionalInfo = [];
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $conditionalInfo = array_merge($conditionalInfo, $this->extractRuleInfo($rule));
            }

            // Check for enum rule and add enum class name to description
            $enumResult = $this->enumAnalyzer->analyzeValidationRule($rule, $namespace, $useStatements);
            if ($enumResult && $enumResult->class !== '') {
                $enumClassName = class_basename($enumResult->class);
                $description .= " ({$enumClassName})";
            }
        }

        // Add conditional information to description
        if (! empty($conditionalInfo)) {
            $description .= '. '.implode('. ', $conditionalInfo);
        }

        return $description;
    }

    /**
     * Generate description for a file field.
     *
     * This is a convenience method that delegates to generateFileDescriptionWithAttribute
     * with a null attribute, using the formatted field name as the description base.
     */
    public function generateFileDescription(string $field, array $fileInfo): string
    {
        return $this->generateFileDescriptionWithAttribute($field, $fileInfo, null);
    }

    /**
     * Generate description for a file field with custom attribute name.
     */
    public function generateFileDescriptionWithAttribute(string $field, array $fileInfo, ?string $attribute = null): string
    {
        $fieldName = $attribute ?? $this->formatFieldName($field);
        $parts = $this->buildFileInfoParts($fileInfo);

        return $fieldName.(! empty($parts) ? ' ('.implode('. ', $parts).')' : '');
    }

    /**
     * Generate description for a conditional field.
     */
    public function generateConditionalDescription(string $field, array $fieldInfo): string
    {
        $description = $this->formatFieldName($field);

        if (isset($fieldInfo['rules_by_condition']) && count($fieldInfo['rules_by_condition']) > 1) {
            $description .= ' (条件により異なるルールが適用されます)';
        }

        return $description;
    }

    /**
     * Format field name to human-readable title.
     */
    protected function formatFieldName(string $field): string
    {
        return Str::title(str_replace(['_', '-'], ' ', $field));
    }

    /**
     * Extract information from a validation rule for description.
     *
     * @return array<string>
     */
    protected function extractRuleInfo(string $rule): array
    {
        $info = [];

        if (str_starts_with($rule, 'max:')) {
            $max = Str::after($rule, 'max:');

            return ["(最大{$max}文字)"];
        }

        if (str_starts_with($rule, 'min:')) {
            $min = Str::after($rule, 'min:');

            return ["(最小{$min}文字)"];
        }

        if (str_starts_with($rule, 'required_if:')) {
            $params = Str::after($rule, 'required_if:');
            $parts = explode(',', $params);
            if (count($parts) >= 2) {
                $field = $parts[0];
                $value = implode(',', array_slice($parts, 1));
                $info[] = "Required when {$field} is {$value}";
            }
        } elseif (str_starts_with($rule, 'required_unless:')) {
            $params = Str::after($rule, 'required_unless:');
            $parts = explode(',', $params);
            if (count($parts) >= 2) {
                $field = $parts[0];
                $value = implode(',', array_slice($parts, 1));
                $info[] = "Required unless {$field} is {$value}";
            }
        } elseif (str_starts_with($rule, 'required_with:')) {
            $fields = Str::after($rule, 'required_with:');
            $info[] = "Required when any of these fields are present: {$fields}";
        } elseif (str_starts_with($rule, 'required_without:')) {
            $fields = Str::after($rule, 'required_without:');
            $info[] = "Required when any of these fields are not present: {$fields}";
        } elseif (str_starts_with($rule, 'prohibited_if:')) {
            $params = Str::after($rule, 'prohibited_if:');
            $parts = explode(',', $params);
            if (count($parts) >= 2) {
                $field = $parts[0];
                $value = implode(',', array_slice($parts, 1));
                $info[] = "Prohibited when {$field} is {$value}";
            }
        } elseif (str_starts_with($rule, 'after:')) {
            $after = Str::after($rule, 'after:');
            $info[] = "Date must be after {$after}";
        } elseif (str_starts_with($rule, 'after_or_equal:')) {
            $after = Str::after($rule, 'after_or_equal:');
            $info[] = "Date must be after or equal to {$after}";
        } elseif (str_starts_with($rule, 'before:')) {
            $before = Str::after($rule, 'before:');
            $info[] = "Date must be before {$before}";
        } elseif (str_starts_with($rule, 'before_or_equal:')) {
            $before = Str::after($rule, 'before_or_equal:');
            $info[] = "Date must be before or equal to {$before}";
        } elseif (str_starts_with($rule, 'date_equals:')) {
            $equals = Str::after($rule, 'date_equals:');
            $info[] = "Date must be equal to {$equals}";
        } elseif (str_starts_with($rule, 'date_format:')) {
            $format = Str::after($rule, 'date_format:');
            $info[] = "Format: {$format}";
        } elseif ($rule === 'timezone' || str_starts_with($rule, 'timezone:')) {
            $info[] = 'Must be a valid timezone';
        }

        return $info;
    }

    /**
     * Build file info parts for description.
     *
     * @return array<string>
     */
    protected function buildFileInfoParts(array $fileInfo): array
    {
        $parts = [];

        if (! empty($fileInfo['mimes'])) {
            $parts[] = 'Allowed types: '.implode(', ', $fileInfo['mimes']);
        }

        if (isset($fileInfo['max_size'])) {
            $maxSize = FileSizeFormatter::format($fileInfo['max_size']);
            $parts[] = "Max size: {$maxSize}";
        }

        if (isset($fileInfo['min_size'])) {
            $minSize = FileSizeFormatter::format($fileInfo['min_size']);
            $parts[] = "Min size: {$minSize}";
        }

        if (! empty($fileInfo['dimensions'])) {
            if (isset($fileInfo['dimensions']['min_width']) && isset($fileInfo['dimensions']['min_height'])) {
                $parts[] = "Min dimensions: {$fileInfo['dimensions']['min_width']}x{$fileInfo['dimensions']['min_height']}";
            }
            if (isset($fileInfo['dimensions']['max_width']) && isset($fileInfo['dimensions']['max_height'])) {
                $parts[] = "Max dimensions: {$fileInfo['dimensions']['max_width']}x{$fileInfo['dimensions']['max_height']}";
            }
            if (isset($fileInfo['dimensions']['ratio'])) {
                $parts[] = "Aspect ratio: {$fileInfo['dimensions']['ratio']}";
            }
        }

        return $parts;
    }
}
