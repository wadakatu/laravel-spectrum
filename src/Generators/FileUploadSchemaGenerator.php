<?php

declare(strict_types=1);

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\Generators\Support\SchemaPropertyMapper;
use LaravelSpectrum\Support\FileSizeFormatter;

class FileUploadSchemaGenerator
{
    protected SchemaPropertyMapper $propertyMapper;

    public function __construct(?SchemaPropertyMapper $propertyMapper = null)
    {
        $this->propertyMapper = $propertyMapper ?? new SchemaPropertyMapper;
    }

    /**
     * @param  array<string, mixed>  $fileField
     * @return array<string, mixed>
     */
    public function generate(array $fileField): array
    {
        $schema = [
            'type' => 'string',
            'format' => 'binary',
        ];

        $description = $this->generateDescription($fileField);
        if ($description !== '') {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $fileFields
     * @return array<string, mixed>
     */
    public function generateMultipartSchema(array $fields, array $fileFields): array
    {
        $properties = $this->mergeProperties($fields, $fileFields);
        $required = $this->extractRequired($fields, $fileFields);

        return [
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $fileField
     */
    private function generateDescription(array $fileField): string
    {
        $parts = [];

        // Allowed types
        if (! empty($fileField['mimes'])) {
            $parts[] = 'Allowed types: '.implode(', ', $fileField['mimes']);
        }

        // File size constraints
        if (isset($fileField['max_size'])) {
            $parts[] = 'Max size: '.FileSizeFormatter::format($fileField['max_size']);
        }

        if (isset($fileField['min_size'])) {
            $parts[] = 'Min size: '.FileSizeFormatter::format($fileField['min_size']);
        }

        // Dimension constraints
        if (! empty($fileField['dimensions'])) {
            $dimensionParts = $this->formatDimensions($fileField['dimensions']);
            $parts = array_merge($parts, $dimensionParts);
        }

        return implode('. ', $parts);
    }

    /**
     * @param  array<string, mixed>  $dimensions
     * @return array<string>
     */
    private function formatDimensions(array $dimensions): array
    {
        $parts = [];

        // Exact dimensions
        if (isset($dimensions['width']) && isset($dimensions['height'])) {
            $parts[] = sprintf('Required dimensions: %dx%d', $dimensions['width'], $dimensions['height']);
        }

        // Min dimensions
        if (isset($dimensions['min_width']) && isset($dimensions['min_height'])) {
            $parts[] = sprintf('Min dimensions: %dx%d', $dimensions['min_width'], $dimensions['min_height']);
        } elseif (isset($dimensions['min_width'])) {
            $parts[] = sprintf('Min width: %d', $dimensions['min_width']);
        } elseif (isset($dimensions['min_height'])) {
            $parts[] = sprintf('Min height: %d', $dimensions['min_height']);
        }

        // Max dimensions
        if (isset($dimensions['max_width']) && isset($dimensions['max_height'])) {
            $parts[] = sprintf('Max dimensions: %dx%d', $dimensions['max_width'], $dimensions['max_height']);
        } elseif (isset($dimensions['max_width'])) {
            $parts[] = sprintf('Max width: %d', $dimensions['max_width']);
        } elseif (isset($dimensions['max_height'])) {
            $parts[] = sprintf('Max height: %d', $dimensions['max_height']);
        }

        // Aspect ratio
        if (isset($dimensions['ratio'])) {
            $parts[] = sprintf('Aspect ratio: %s', $dimensions['ratio']);
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $fileFields
     * @return array<string, mixed>
     */
    private function mergeProperties(array $fields, array $fileFields): array
    {
        $properties = [];

        // Add regular fields using the property mapper
        foreach ($fields as $name => $field) {
            $property = $this->propertyMapper->mapType($field);
            $property = $this->propertyMapper->mapAll($field, $property);
            $properties[$name] = $property;
        }

        // Add file fields
        foreach ($fileFields as $name => $field) {
            $properties[$name] = $field;
        }

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $fileFields
     * @return array<string>
     */
    private function extractRequired(array $fields, array $fileFields): array
    {
        $required = [];

        foreach ($fields as $name => $field) {
            if (isset($field['required']) && $field['required']) {
                $required[] = $name;
            }
        }

        foreach ($fileFields as $name => $field) {
            if (isset($field['required']) && $field['required']) {
                $required[] = $name;
            }
        }

        return array_values(array_unique($required));
    }
}
